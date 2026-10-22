<?php

namespace App\Services;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PDO;
use RuntimeException;
use Throwable;

/** Cadangan streaming dengan publikasi gzip terakhir dan checksum legacy SHA-256. */
class DatabaseBackupService
{
    private const CHUNK = 65536;

    public function __construct(private ?string $connection = null) {}

    public function directory(): string
    {
        return config('backup.path', storage_path('app/backups'));
    }

    public function backup(): string
    {
        // Periksa nilai asli sebelum caller console berkesempatan melakukan cast.
        $this->retention(config('backup.keep', 14));
        $config = $this->config();
        $driver = $config['driver'] ?? null;
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException('Driver belum didukung untuk cadangan.');
        }
        $arguments = $driver === 'sqlite' ? [] : $this->mysqlArguments($config);
        File::ensureDirectoryExists($this->directory(), 0700);
        $stage = $this->directory().'/.backup-'.bin2hex(random_bytes(12));
        if (! @mkdir($stage, 0700)) {
            throw new RuntimeException('Tidak dapat membuat staging cadangan.');
        }
        $extension = $driver === 'sqlite' ? '.sqlite' : '.sql';
        $raw = $stage.'/dump'.$extension;
        $gzip = $stage.'/dump.gz';
        $final = $this->directory().'/'.$this->connectionName().'-'.now()->format('Ymd-His-u').'-'.bin2hex(random_bytes(4)).$extension.'.gz';
        $publishedChecksum = false;
        $published = false;
        try {
            if ($driver === 'sqlite') {
                DB::connection($this->connection)->statement('VACUUM INTO ?', [$raw]);
            } else {
                $this->backupMysql($config, $arguments, $raw);
            }
            $sourceHash = $this->compress($raw, $gzip);
            if (! hash_equals($sourceHash, $this->decode($gzip))) {
                throw new RuntimeException('Integritas kompresi cadangan tidak cocok.');
            }
            $hash = @hash_file('sha256', $gzip);
            if ($hash === false || ! @chmod($gzip, 0600)) {
                throw new RuntimeException('Tidak dapat menyelesaikan cadangan.');
            }
            $line = $hash.'  '.basename($final)."\n";
            if (@file_put_contents($stage.'/checksum', $line) !== strlen($line)
                || ! @chmod($stage.'/checksum', 0600)) {
                throw new RuntimeException('Tidak dapat menulis checksum cadangan.');
            }
            // Dua file tidak dapat di-rename atomik bersama. Gzip diterbitkan terakhir;
            // kegagalan biasa membersihkan sidecar, crash dapat meninggalkan sidecar saja.
            if (! @rename($stage.'/checksum', $final.'.sha256')) {
                throw new RuntimeException('Tidak dapat menerbitkan checksum cadangan.');
            }
            $publishedChecksum = true;
            if (! @rename($gzip, $final)) {
                throw new RuntimeException('Tidak dapat menerbitkan cadangan.');
            }
            $published = true;

            return $final;
        } catch (Throwable) {
            // Jangan sertakan exception asal: driver/proses dapat membawa kredensial.
            throw new RuntimeException('Pembuatan cadangan gagal; tidak ada cadangan baru yang diterbitkan.');
        } finally {
            if ($publishedChecksum && ! $published) {
                @unlink($final.'.sha256');
            }
            File::deleteDirectory($stage);
        }
    }

    /** Nilai environment berupa string integer desimal diterima tanpa coercion longgar. */
    private function retention(mixed $keep): int
    {
        if ((! is_int($keep) && (! is_string($keep) || ! preg_match('/^[1-9][0-9]*$/D', $keep)))
            || filter_var($keep, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new RuntimeException('Retensi cadangan harus integer positif.');
        }

        return (int) $keep;
    }

    public function prune(mixed $keep): int
    {
        $keep = $this->retention($keep);
        $sqlite = ($this->config()['driver'] ?? null) === 'sqlite';
        $valid = [];
        foreach ($this->backups() as $file) {
            try {
                if ($sqlite) {
                    $this->verify($file);
                } else {
                    // MySQL: integritas transport saja, bukan bukti restore SQL.
                    $this->assertChecksum($file);
                    $this->decode($file);
                }
                $valid[] = $file;
            } catch (RuntimeException) {
                // Artefak rusak tidak boleh menggusur cadangan baik terakhir.
            }
        }
        $old = array_slice($valid, $keep);
        foreach ($old as $file) {
            if (! File::delete($file)) {
                throw new RuntimeException('Penghapusan cadangan lama gagal.');
            }
            File::delete($file.'.sha256');
        }

        return count($old);
    }

    /** @return list<string> */
    public function backups(): array
    {
        $files = glob($this->directory().'/'.$this->connectionName().'-*.gz') ?: [];
        $files = array_values(array_filter($files, fn ($file) => is_file($file) && is_file($file.'.sha256')));
        rsort($files);

        return $files;
    }

    /** @return array<string, int> jumlah baris snapshot, bukan sumber hidup */
    public function verify(string $backup): array
    {
        $this->assertChecksum($backup);
        if (($this->config()['driver'] ?? null) !== 'sqlite') {
            throw new RuntimeException('Verifikasi pemulihan MySQL/MariaDB belum didukung.');
        }
        $scratch = tempnam(sys_get_temp_dir(), 'restore-');
        if ($scratch === false) {
            throw new RuntimeException('Tidak dapat membuat staging pemulihan.');
        }
        $pdo = null;
        try {
            $this->decode($backup, $scratch);
            $header = @file_get_contents($scratch, false, null, 0, 16);
            if ($header !== "SQLite format 3\0") {
                throw new RuntimeException('Snapshot bukan basis data SQLite.');
            }
            $pdo = new PDO('sqlite:'.$scratch, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('PRAGMA query_only = ON');
            if ($pdo->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN) !== ['ok']
                || $pdo->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
                throw new RuntimeException('Integritas snapshot SQLite tidak valid.');
            }

            return $this->rowCounts($pdo);
        } catch (Throwable) {
            throw new RuntimeException('Verifikasi integritas snapshot cadangan gagal.');
        } finally {
            $pdo = null;
            @unlink($scratch);
        }
    }

    private function assertChecksum(string $backup): void
    {
        $line = @file_get_contents($backup.'.sha256', false, null, 0, 4096);
        $hash = is_file($backup) ? @hash_file('sha256', $backup) : false;
        if (! is_string($line) || ! preg_match('/^([a-f0-9]{64})  /', $line, $match)
            || $hash === false || ! hash_equals($match[1], $hash)) {
            throw new RuntimeException('Checksum cadangan tidak cocok atau hilang.');
        }
    }

    private function compress(string $source, string $target): string
    {
        $input = @fopen($source, 'rb');
        $output = @gzopen($target, 'wb9');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                gzclose($output);
            }
            throw new RuntimeException('Tidak dapat membuka stream kompresi.');
        }
        $hash = hash_init('sha256');
        try {
            while (! feof($input)) {
                $chunk = fread($input, self::CHUNK);
                if ($chunk === false || ($chunk === '' && ! feof($input))) {
                    throw new RuntimeException('Pembacaan dump gagal.');
                }
                hash_update($hash, $chunk);
                for ($offset = 0; $offset < strlen($chunk); $offset += $written) {
                    $written = @gzwrite($output, substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        throw new RuntimeException('Penulisan kompresi gagal.');
                    }
                }
            }
        } finally {
            fclose($input);
            $closed = @gzclose($output);
        }
        if (! $closed) {
            throw new RuntimeException('Penyelesaian kompresi gagal.');
        }

        return hash_final($hash);
    }

    /** Inflate memeriksa trailer CRC/ISIZE dan akhir stream; gzread saja menerima truncation. */
    private function decode(string $source, ?string $target = null): string
    {
        $input = @fopen($source, 'rb');
        $output = $target === null ? null : @fopen($target, 'wb');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            throw new RuntimeException('Tidak dapat membuka stream pemulihan.');
        }
        $context = inflate_init(ZLIB_ENCODING_GZIP);
        $hash = hash_init('sha256');
        $read = 0;
        try {
            while (! feof($input)) {
                // Chunk kompresi kecil membatasi ekspansi sementara pada gzip sangat padat.
                $chunk = fread($input, 1024);
                if ($chunk === false || ($chunk === '' && ! feof($input))) {
                    throw new RuntimeException('Pembacaan gzip gagal.');
                }
                if ($chunk === '') {
                    break;
                }
                $read += strlen($chunk);
                $decoded = @inflate_add($context, $chunk, ZLIB_SYNC_FLUSH);
                if ($decoded === false) {
                    throw new RuntimeException('Stream gzip rusak.');
                }
                hash_update($hash, $decoded);
                if ($output !== null) {
                    for ($offset = 0; $offset < strlen($decoded); $offset += $written) {
                        $written = @fwrite($output, substr($decoded, $offset));
                        if ($written === false || $written === 0) {
                            throw new RuntimeException('Penulisan pemulihan gagal.');
                        }
                    }
                }
                if (inflate_get_status($context) === ZLIB_STREAM_END) {
                    if (inflate_get_read_len($context) !== $read || fread($input, 1) !== '') {
                        throw new RuntimeException('Data tambahan setelah stream gzip.');
                    }
                    break;
                }
            }
            if (inflate_get_status($context) !== ZLIB_STREAM_END) {
                throw new RuntimeException('Stream gzip tidak lengkap.');
            }
            if ($output !== null) {
                $flushed = fflush($output);
                $closed = fclose($output);
                $output = null;
                if (! $flushed || ! $closed) {
                    throw new RuntimeException('Penyelesaian pemulihan gagal.');
                }
            }
        } finally {
            fclose($input);
            if (is_resource($output)) {
                fclose($output);
            }
        }

        return hash_final($hash);
    }

    private function rowCounts(PDO $pdo): array
    {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "'.str_replace('"', '""', $table).'"')->fetchColumn();
        }

        return $counts;
    }

    private function mysqlArguments(array $config): array
    {
        // Flag TLS berbeda antara mysqldump dan mariadb-dump. Tanpa profil
        // client terverifikasi, tolak opsi PDO daripada menurunkan transport.
        if (! empty($config['options']) || isset($config['read']) || isset($config['write'])) {
            throw new RuntimeException('Opsi PDO/TLS atau koneksi split belum didukung untuk cadangan.');
        }
        foreach ($config as $key => $value) {
            if (preg_match('/ssl|tls/i', (string) $key) && $value !== null) {
                throw new RuntimeException('Konfigurasi TLS belum didukung untuk cadangan.');
            }
        }
        foreach (['database', 'username', 'password', 'unix_socket'] as $key) {
            if (isset($config[$key]) && (! is_string($config[$key]) || str_contains($config[$key], "\0"))) {
                throw new RuntimeException('Target cadangan tidak valid.');
            }
        }
        if (empty($config['database']) || str_starts_with($config['database'], '-')) {
            throw new RuntimeException('Target cadangan tidak valid.');
        }
        $args = ['mysqldump', '--no-defaults', '--single-transaction', '--routines', '--no-tablespaces'];
        if (! empty($config['unix_socket'])) {
            $args[] = '--protocol=SOCKET';
            $args[] = '--socket='.$config['unix_socket'];
        } else {
            if (! is_string($config['host'] ?? null) || $config['host'] === '' || str_contains($config['host'], "\0")
                || filter_var($config['port'] ?? 3306, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
                throw new RuntimeException('Transport cadangan tidak valid.');
            }
            $args[] = '--protocol=TCP';
            $args[] = '--host='.$config['host'];
            $args[] = '--port='.($config['port'] ?? 3306);
        }
        $args[] = '--user='.($config['username'] ?? '');

        return $args;
    }

    private function backupMysql(array $config, array $arguments, string $target): void
    {
        $arguments[] = '--result-file='.$target;
        $arguments[] = $config['database'];
        try {
            $result = Process::env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
                ->timeout(3600)->run($arguments);
            if (! $result->successful()) {
                throw new RuntimeException('Dump gagal.');
            }
        } catch (Throwable) {
            throw new RuntimeException('Dump basis data gagal.');
        }
    }

    private function config(): array
    {
        try {
            return (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.$this->connectionName()));
        } catch (Throwable) {
            throw new RuntimeException('Konfigurasi koneksi cadangan tidak valid.');
        }
    }

    private function connectionName(): string
    {
        $name = $this->connection ?? config('database.default');
        if (! is_string($name) || ! preg_match('/^[A-Za-z0-9_-]+$/D', $name)) {
            throw new RuntimeException('Nama koneksi cadangan tidak valid.');
        }

        return $name;
    }
}
