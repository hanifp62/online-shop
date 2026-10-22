<?php

namespace Tests\Support;

use Closure;
use PHPUnit\Framework\Assert;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** NFR-CICD-004: real PDF extraction; dependency/runtime failures must not become skips. */
final class PdfTextExtractor
{
    private readonly Closure $processFactory;

    private ?string $cacheDirectory = null;

    private ?string $cacheHash = null;

    private bool $shutdownRegistered = false;

    public function __construct(
        private readonly ExecutableFinder $executables = new ExecutableFinder,
        private readonly string $osFamily = PHP_OS_FAMILY,
        private readonly ?bool $ci = null,
        ?Closure $processFactory = null,
    ) {
        $this->processFactory = $processFactory ?? static fn (array $command): Process => new Process($command);
    }

    /** Return UTF-8 text with form feeds only BETWEEN pages, on either backend. */
    public function extract(string $pdf): string
    {
        if (! in_array($this->osFamily, ['Linux', 'Darwin'], true)) {
            if ($this->isCi()) {
                Assert::fail('PDF text extraction is unsupported on this CI platform.');
            }
            Assert::markTestSkipped('OPTIONAL LOCAL SKIP: PDF extraction supports Linux (pdftotext) or macOS (pdftotext/PDFKit). CI must not skip.');
        }

        $poppler = $this->executables->find('pdftotext');
        $swiftc = null;
        if ($poppler === null && $this->osFamily === 'Darwin') {
            $swiftc = $this->executables->find('swiftc', null, ['/usr/bin']);
        }
        if ($poppler === null && $swiftc === null) {
            Assert::fail($this->osFamily === 'Linux'
                ? 'PDF extraction requires pdftotext (poppler-utils) on Linux.'
                : 'PDF extraction requires pdftotext or swiftc/PDFKit on macOS.');
        }

        $binary = $poppler === null ? $this->pdfKitBinary($swiftc) : null;
        $directory = sys_get_temp_dir().'/zafira-pdf-'.bin2hex(random_bytes(16));
        Assert::assertTrue(@mkdir($directory, 0700), 'Cannot create private PDF extraction directory.');
        try {
            $input = $directory.'/input.pdf';
            Assert::assertSame(strlen($pdf), @file_put_contents($input, $pdf), 'Cannot write PDF extraction input.');
            if ($poppler !== null) {
                // Raw content order preserves report lines across the Latin/CJK font runs.
                $text = $this->run([$poppler, '-enc', 'UTF-8', '-raw', $input, '-'], 30, 'pdftotext');
            } else {
                $text = $this->run([$binary, $input], 30, 'PDFKit extraction');
            }

            $text = str_replace(["\r\n", "\r"], "\n", $text);
            if ($poppler !== null) {
                // Poppler terminates the final page too; PDFKit emits only separators.
                $text = preg_replace('/\f[ \t\n]*\z/', '', $text);
            }
            Assert::assertSame(1, preg_match('//u', $text), 'PDF extractor returned invalid UTF-8.');
            Assert::assertNotSame('', trim(str_replace("\f", '', $text)), 'PDF extractor returned no text.');

            return rtrim($text, "\n");
        } finally {
            Assert::assertTrue($this->removeDirectory($directory), 'Cannot clean up PDF extraction temporary files.');
        }
    }

    /** Release only this instance's cache; failed cleanup invalidates it and fails the test. */
    public function close(): void
    {
        $this->cacheHash = null;
        if ($this->cacheDirectory === null) {
            return;
        }
        if (! $this->removeDirectory($this->cacheDirectory)) {
            Assert::fail('Cannot clean up PDFKit compilation cache.');
        }
        $this->cacheDirectory = null;
    }

    private function pdfKitBinary(string $swiftc): string
    {
        if ($this->cacheDirectory !== null && $this->cacheHash !== null) {
            $binary = $this->cacheDirectory.'/pdf-text';
            Assert::assertTrue(hash_equals($this->cacheHash, $this->binaryHash($binary)), 'PDFKit cached executable changed.');

            return $binary;
        }

        $this->close();
        $directory = sys_get_temp_dir().'/zafira-pdfkit-'.bin2hex(random_bytes(16));
        Assert::assertTrue(@mkdir($directory, 0700), 'Cannot create private PDFKit compilation cache.');
        $this->cacheDirectory = $directory;
        if (! $this->shutdownRegistered) {
            register_shutdown_function(function (): void {
                try {
                    $this->close();
                } catch (\Throwable) {
                    fwrite(STDERR, "PDFKit cache cleanup failed.\n");
                    exit(1);
                }
            });
            $this->shutdownRegistered = true;
        }

        $binary = $directory.'/pdf-text';
        try {
            $this->run([
                $swiftc, '-O', '-module-cache-path', $directory.'/swift-module-cache',
                __DIR__.'/pdf-text.swift', '-o', $binary,
            ], 120, 'PDFKit compilation');
            // Never discover/reuse a disk cache: trust only this successful compilation.
            $this->cacheHash = $this->binaryHash($binary);

            return $binary;
        } catch (\Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    private function binaryHash(string $binary): string
    {
        $directory = dirname($binary);
        clearstatcache(true, $directory);
        clearstatcache(true, $binary);
        Assert::assertTrue(! is_link($directory) && is_dir($directory) && (fileperms($directory) & 0777) === 0700, 'PDFKit cache is not private.');
        Assert::assertTrue(! is_link($binary) && is_file($binary) && is_executable($binary), 'PDFKit compiler did not produce a regular executable.');
        $hash = @hash_file('sha256', $binary);
        Assert::assertIsString($hash, 'Cannot validate PDFKit cached executable.');

        return $hash;
    }

    private function isCi(): bool
    {
        if ($this->ci !== null) {
            return $this->ci;
        }
        foreach (['CI', 'GITHUB_ACTIONS'] as $key) {
            foreach ([getenv($key), $_SERVER[$key] ?? '', $_ENV[$key] ?? ''] as $value) {
                if (! in_array(strtolower(trim((string) $value)), ['', '0', 'false', 'no', 'off'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<string> $command */
    private function run(array $command, float $timeout, string $stage): string
    {
        try {
            $process = ($this->processFactory)($command);
            $process->setTimeout($timeout);
            $process->run();
        } catch (ExceptionInterface) {
            // Process exception messages can contain argv, paths, stderr or PDF data.
            Assert::fail('PDF '.$stage.' failed to start or finish within its timeout.');
        }
        if (! $process->isSuccessful()) {
            Assert::fail('PDF '.$stage.' failed (nonzero process exit).');
        }

        return $process->getOutput();
    }

    private function removeDirectory(string $directory): bool
    {
        if (is_link($directory)) {
            return @unlink($directory);
        }
        $entries = @scandir($directory);
        if ($entries === false) {
            return false;
        }
        $removed = true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory.'/'.$entry;
            $success = is_dir($path) && ! is_link($path) ? $this->removeDirectory($path) : @unlink($path);
            $removed = $success && $removed;
        }

        return @rmdir($directory) && $removed;
    }
}
