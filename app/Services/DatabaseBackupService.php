<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PDO;
use RuntimeException;

/**
 * Cadangan basis data terjadwal: dump terkompresi + checksum SHA-256,
 * retensi N cadangan terakhir, dan uji pemulihan ke basis data sementara.
 */
class DatabaseBackupService
{
    public function __construct(private ?string $connection = null) {}

    public function directory(): string
    {
        return config('backup.path', storage_path('app/backups'));
    }

    /** @return string path berkas cadangan yang dibuat */
    public function backup(): string
    {
        $config = $this->config();
        File::ensureDirectoryExists($this->directory(), 0700);
        $base = $this->directory().'/'.$this->connectionName().'-'.now()->format('Ymd-His-u');

        $path = match ($config['driver']) {
            'sqlite' => $this->backupSqlite($base.'.sqlite'),
            'mysql', 'mariadb' => $this->backupMysql($config, $base.'.sql'),
            default => throw new RuntimeException("Driver {$config['driver']} belum didukung untuk cadangan."),
        };

        $gz = $path.'.gz';
        file_put_contents($gz, gzencode(file_get_contents($path), 9));
        unlink($path);
        chmod($gz, 0600);
        file_put_contents($gz.'.sha256', hash_file('sha256', $gz).'  '.basename($gz)."\n");

        return $gz;
    }

    /** Hapus cadangan lama; simpan $keep terbaru. @return int jumlah dihapus */
    public function prune(int $keep): int
    {
        $files = $this->backups();
        $old = array_slice($files, $keep);
        foreach ($old as $file) {
            File::delete([$file, $file.'.sha256']);
        }

        return count($old);
    }

    /** @return list<string> cadangan milik koneksi ini, terbaru dulu */
    public function backups(): array
    {
        $files = glob($this->directory().'/'.$this->connectionName().'-*.gz') ?: [];
        rsort($files);

        return $files;
    }

    /**
     * Pulihkan cadangan ke basis data SQLite sementara dan cocokkan jumlah baris
     * setiap tabel dengan sumber. Basis data produksi tidak disentuh.
     *
     * @return array<string, int> jumlah baris per tabel hasil pemulihan
     */
    public function verify(string $backup): array
    {
        $this->assertChecksum($backup);
        $config = $this->config();
        if ($config['driver'] !== 'sqlite') {
            throw new RuntimeException('Verifikasi pemulihan MySQL/MariaDB belum didukung.');
        }

        $scratch = tempnam(sys_get_temp_dir(), 'restore-');
        try {
            file_put_contents($scratch, gzdecode(file_get_contents($backup)));
            $restored = $this->rowCounts(new PDO('sqlite:'.$scratch));
            $source = $this->rowCounts(DB::connection($this->connection)->getPdo());
            if ($restored !== $source) {
                throw new RuntimeException('Jumlah baris hasil pemulihan berbeda dari sumber.');
            }

            return $restored;
        } finally {
            @unlink($scratch);
        }
    }

    private function assertChecksum(string $backup): void
    {
        $line = @file_get_contents($backup.'.sha256');
        if ($line === false || ! hash_equals(strtok($line, ' '), hash_file('sha256', $backup))) {
            throw new RuntimeException('Checksum cadangan tidak cocok atau hilang: '.basename($backup));
        }
    }

    /** @return array<string, int> */
    private function rowCounts(PDO $pdo): array
    {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "'.str_replace('"', '""', $table).'"')->fetchColumn();
        }

        return $counts;
    }

    private function backupSqlite(string $target): string
    {
        // VACUUM INTO menghasilkan salinan konsisten tanpa mengunci penulis lama.
        DB::connection($this->connection)->statement('VACUUM INTO ?', [$target]);

        return $target;
    }

    private function backupMysql(array $config, string $target): string
    {
        $result = Process::env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
            ->timeout(3600)
            ->run([
                'mysqldump', '--single-transaction', '--routines', '--no-tablespaces',
                '--host='.$config['host'], '--port='.$config['port'],
                '--user='.$config['username'], '--result-file='.$target, $config['database'],
            ]);
        if (! $result->successful()) {
            throw new RuntimeException('mysqldump gagal: '.trim($result->errorOutput()));
        }

        return $target;
    }

    private function config(): array
    {
        return config('database.connections.'.$this->connectionName());
    }

    private function connectionName(): string
    {
        return $this->connection ?? config('database.default');
    }
}
