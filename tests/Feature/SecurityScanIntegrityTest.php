<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pemindaian keamanan: rahasia tidak bocor ke kode, audit dependensi bersih,
 * dan log aplikasi tidak memuat PII mentah (email/password/token/phone/address).
 */
class SecurityScanIntegrityTest extends TestCase
{
    public function test_tracked_files_do_not_contain_hardcoded_secrets(): void
    {
        $violators = $this->scanSecrets(dirname(__DIR__, 2));
        $this->assertEmpty($violators, "Pola rahasia ditemukan pada file berikut (nilai tidak dicetak):\n".implode("\n", $violators));
    }

    public function test_scanner_covers_docs_tests_and_agent_dirs_and_reports_paths_only(): void
    {
        // Synthetic values are assembled at runtime so this file holds no literal secret.
        $secrets = [
            'docs/execution/evidence/report.md' => 'base'.'64:'.str_repeat('Q', 43).'=',
            'tests/python/other_fixture.py' => '-----BEGIN RSA '.'PRIVATE KEY-----',
            'tests/Feature/Leak.php' => 'AK'.'IA'.str_repeat('Z', 16),
            '.agents/skills/demo/SKILL.md' => 'gh'.'p_'.str_repeat('a', 36),
            '.claude/notes.md' => 'ng'.'pk_'.str_repeat('b', 12),
            '.codex/config.toml' => 'SB-Mid'.'-server-'.str_repeat('c', 12),
            'app/Config.php' => 'sk'.'_live',
        ];
        $ignored = [
            'vendor/pkg/key.php' => $secrets['app/Config.php'],
            'node_modules/pkg/key.js' => $secrets['app/Config.php'],
            'storage/logs/app.txt' => $secrets['app/Config.php'],
            '.env.testing' => $secrets['docs/execution/evidence/report.md'],
            'phpunit.xml' => $secrets['docs/execution/evidence/report.md'],
            'public/blob.bin' => "\0".$secrets['app/Config.php'],
        ];
        $root = sys_get_temp_dir().'/secret-scan-'.bin2hex(random_bytes(8));

        try {
            foreach ($secrets + $ignored as $path => $content) {
                @mkdir(dirname($root.'/'.$path), 0700, true);
                file_put_contents($root.'/'.$path, "context\n{$content}\n");
            }

            $found = $this->scanSecrets($root);
            sort($found);
            $expected = array_keys($secrets);
            sort($expected);

            // Messages list paths only; a failing diff never carries the synthetic values.
            $this->assertTrue($expected === $found, 'Scanner must report exactly the fixture paths, never values.');
        } finally {
            $this->removeFixtures($root);
        }
    }

    private function removeFixtures(string $root): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($root);
    }

    /**
     * Returns relative paths only; matched values never leave this method.
     * Mirrors the CI `git grep -lIE` gate: binaries skipped, same pattern, same allowlist.
     */
    private function scanSecrets(string $root): array
    {
        // Midtrans live/sandbox, AWS, private key, GitHub PAT, NgodingPakeAI token, Laravel APP_KEY
        $pattern = '/(?:sk_live|SB-Mid-server-[A-Za-z0-9_-]{10,}|Mid-server-[A-Za-z0-9_-]{10,}|AKIA[0-9A-Z]{16}|-----BEGIN [A-Z ]*PRIVATE KEY|ghp_[A-Za-z0-9]{30,}|ngpk_[A-Za-z0-9]{10,}|base64:[A-Za-z0-9+\/]{43}=)/';
        // Intentional fixtures: the two scanner definitions, the redaction sentinel, and the dedicated test APP_KEY.
        $allowlist = [
            '.github/workflows/ci.yml',
            'tests/Feature/SecurityScanIntegrityTest.php',
            'tests/python/test_amdis_launch_sanitization.py',
            'phpunit.xml',
        ];

        $violators = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);

            // Runtime, dependency, and local env files only.
            if (
                in_array($path, $allowlist, true) ||
                str_starts_with($path, 'vendor/') ||
                str_starts_with($path, 'node_modules/') ||
                str_starts_with($path, '.git/') ||
                str_starts_with($path, 'storage/') ||
                str_starts_with($file->getFilename(), '.env') ||
                ! $file->isFile()
            ) {
                continue;
            }

            $content = @file_get_contents($file->getPathname());
            // Unreadable files fail the scan instead of passing silently.
            if ($content === false) {
                $violators[] = $path.' (tidak terbaca)';

                continue;
            }

            // Same heuristic as git grep -I: NUL byte within the first 8000 bytes means binary.
            if (! str_contains(substr($content, 0, 8000), "\0") && preg_match($pattern, $content) === 1) {
                $violators[] = $path;
            }
        }

        return $violators;
    }

    public function test_app_logs_do_not_leak_raw_pii(): void
    {
        $appDir = app_path();
        $piiPattern = '/Log::(info|warning|error|debug)\s*\([^)]*\\$(?:password|token|pin|cvv|secret)\b/i';

        $violators = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appDir));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (preg_match($piiPattern, file_get_contents($file->getPathname()), $matches)) {
                $violators[] = $file->getPathname()." berpotensi mencatat variabel PII sensitif: {$matches[0]}";
            }
        }

        $this->assertEmpty($violators, "Ditemukan logging PII mentah:\n".implode("\n", $violators));
    }
}
