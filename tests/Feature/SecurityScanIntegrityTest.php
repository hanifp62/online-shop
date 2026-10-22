<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * NFR-CICD-003: adapter PHPUnit untuk scanner kanonis CI.
 * Regression sintetis Python menjaga fail-closed, cakupan, dan keluaran aman.
 * Tidak memerlukan bootstrap aplikasi atau database.
 */
class SecurityScanIntegrityTest extends TestCase
{
    public function test_tracked_files_do_not_contain_hardcoded_secrets(): void
    {
        $this->assertCanonicalCommandSucceeds([
            'python3', '-B', 'scripts/ci/security_scan.py', '--mode', 'secrets',
        ]);
    }

    public function test_scanner_covers_docs_tests_and_agent_dirs_and_reports_paths_only(): void
    {
        $this->assertCanonicalCommandSucceeds([
            'python3', '-B', '-m', 'unittest', 'discover',
            '-s', 'tests/python', '-p', 'test_ci_security_scan.py', '-v',
        ]);
    }

    public function test_app_logs_do_not_leak_raw_pii(): void
    {
        $this->assertCanonicalCommandSucceeds([
            'python3', '-B', 'scripts/ci/security_scan.py', '--mode', 'pii',
        ]);
    }

    private function assertCanonicalCommandSucceeds(array $command): void
    {
        $process = new Process($command, dirname(__DIR__, 2));
        $process->setTimeout(60);

        $this->assertSame(
            0,
            $process->run(),
            "Gerbang scanner kanonis gagal:\n".$process->getOutput().$process->getErrorOutput(),
        );
    }
}
