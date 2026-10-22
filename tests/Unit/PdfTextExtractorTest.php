<?php

namespace Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\SkippedWithMessageException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Support\PdfTextExtractor;

/** NFR-CICD-004: process doubles only; real PDF content remains covered by AdminOrderReportTest. */
final class PdfTextExtractorTest extends TestCase
{
    private function finder(?string $poppler = null, ?string $swiftc = null): ExecutableFinder
    {
        $finder = $this->createMock(ExecutableFinder::class);
        $finder->method('find')->willReturnCallback(static fn (string $name): ?string => match ($name) {
            'pdftotext' => $poppler,
            'swiftc' => $swiftc,
            default => null,
        });

        return $finder;
    }

    private function process(float $timeout, string $output = '', bool $successful = true): Process
    {
        $process = $this->createMock(Process::class);
        $process->expects(self::once())->method('setTimeout')->with($timeout)->willReturnSelf();
        $process->method('isSuccessful')->willReturn($successful);
        $process->method('getOutput')->willReturn($output);

        return $process;
    }

    public static function supportedPlatforms(): array
    {
        return ['Linux' => ['Linux'], 'macOS' => ['Darwin']];
    }

    #[DataProvider('supportedPlatforms')]
    public function test_poppler_uses_argument_array_utf8_timeout_and_removes_only_final_page_break(string $os): void
    {
        $input = null;
        $pdf = "%PDF- synthetic input with 'quotes' and ; punctuation";
        $process = $this->process(30, "Lumière 中文\r\n\fPage 2\r\n\f\n");
        $process->expects(self::once())->method('run')->willReturn(0);
        $finder = $this->createMock(ExecutableFinder::class);
        $finder->expects(self::once())->method('find')->with('pdftotext')->willReturn('/tools with spaces/pdftotext');
        $extractor = new PdfTextExtractor($finder, $os, true, function (array $command) use (&$input, $pdf, $process): Process {
            $input = $command[4];
            self::assertSame(['/tools with spaces/pdftotext', '-enc', 'UTF-8', '-raw', $input, '-'], $command);
            self::assertSame($pdf, file_get_contents($input));

            return $process;
        });

        self::assertSame("Lumière 中文\n\fPage 2", $extractor->extract($pdf));
        self::assertNotNull($input);
        self::assertFileDoesNotExist($input);
        self::assertDirectoryDoesNotExist(dirname($input));
    }

    public function test_pdfkit_compiles_once_for_repeated_extraction_and_cleans_only_owned_cache(): void
    {
        $directory = null;
        $compilations = 0;
        $inputs = [];
        $pdfs = ['%PDF- first', '%PDF- second'];
        $compile = $this->process(120);
        $compile->expects(self::once())->method('run')->willReturn(0);
        $extracts = [$this->process(30, "One\fDeux café\f中文"), $this->process(30, "One\fDeux café\f中文")];
        foreach ($extracts as $extract) {
            $extract->expects(self::once())->method('run')->willReturn(0);
        }
        $foreign = tempnam(sys_get_temp_dir(), 'pdf-unowned-');
        file_put_contents($foreign, 'unrelated file');
        $helper = new PdfTextExtractor($this->finder(null, '/usr/bin/swiftc'), 'Darwin', true,
            function (array $command) use (&$directory, &$compilations, &$inputs, $pdfs, $compile, $extracts): Process {
                if ($command[0] === '/usr/bin/swiftc') {
                    $compilations++;
                    $directory = dirname($command[3]);
                    self::assertSame([
                        '/usr/bin/swiftc', '-O', '-module-cache-path', $directory.'/swift-module-cache',
                        dirname(__DIR__).'/Support/pdf-text.swift', '-o', $directory.'/pdf-text',
                    ], $command);
                    mkdir($directory.'/swift-module-cache/nested', 0700, true);
                    file_put_contents($directory.'/swift-module-cache/nested/module', 'synthetic cache');
                    file_put_contents($directory.'/pdf-text', 'synthetic binary');
                    chmod($directory.'/pdf-text', 0700);

                    return $compile;
                }
                $index = count($inputs);
                $inputs[] = $command[1];
                self::assertSame([$directory.'/pdf-text', $command[1]], $command);
                self::assertNotSame($directory, dirname($command[1]));
                self::assertSame($pdfs[$index], file_get_contents($command[1]));

                return $extracts[$index];
            });

        try {
            foreach ($pdfs as $index => $pdf) {
                self::assertSame("One\fDeux café\f中文", $helper->extract($pdf));
                self::assertDirectoryDoesNotExist(dirname($inputs[$index]));
                self::assertFileExists($directory.'/pdf-text');
            }
            self::assertSame(1, $compilations);
            self::assertNotSame($inputs[0], $inputs[1]);
            self::assertSame(0700, fileperms($directory) & 0777);
            $helper->close();
            $helper->close(); // Explicit cleanup is idempotent; shutdown is also safe after it.
            self::assertDirectoryDoesNotExist($directory);
            self::assertSame('unrelated file', file_get_contents($foreign));
        } finally {
            unlink($foreign);
            $helper->close();
        }
    }

    #[DataProvider('supportedPlatforms')]
    public function test_missing_dependency_fails_on_supported_platforms_in_ci_and_locally(string $os): void
    {
        foreach ([true, false] as $ci) {
            $helper = new PdfTextExtractor($this->finder(), $os, $ci, static function (): Process {
                self::fail('No process may start without an extractor.');
            });
            try {
                $helper->extract('%PDF- synthetic');
                self::fail('Missing dependency must fail.');
            } catch (AssertionFailedError $error) {
                self::assertNotInstanceOf(SkippedWithMessageException::class, $error);
                self::assertStringStartsWith('PDF extraction requires pdftotext', $error->getMessage());
            }
        }
    }

    public function test_unsupported_ci_platform_fails_instead_of_skipping(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('PDF text extraction is unsupported on this CI platform.');
        (new PdfTextExtractor($this->finder(), 'Windows', true))->extract('%PDF- synthetic');
    }

    public function test_only_unsupported_local_platform_may_explicitly_skip(): void
    {
        try {
            (new PdfTextExtractor($this->finder(), 'Windows', false))->extract('%PDF- synthetic');
            self::fail('Unsupported local platform must be reported explicitly.');
        } catch (SkippedWithMessageException $error) {
            self::assertStringStartsWith('OPTIONAL LOCAL SKIP:', $error->getMessage());
        }
    }

    public function test_ci_detection_honours_github_actions_without_explicit_override(): void
    {
        $existed = array_key_exists('GITHUB_ACTIONS', $_SERVER);
        $saved = $_SERVER['GITHUB_ACTIONS'] ?? null;
        $_SERVER['GITHUB_ACTIONS'] = 'true';
        try {
            $this->expectException(AssertionFailedError::class);
            $this->expectExceptionMessage('PDF text extraction is unsupported on this CI platform.');
            (new PdfTextExtractor($this->finder(), 'Windows'))->extract('%PDF- synthetic');
        } finally {
            if ($existed) {
                $_SERVER['GITHUB_ACTIONS'] = $saved;
            } else {
                unset($_SERVER['GITHUB_ACTIONS']);
            }
        }
    }

    public static function processFailures(): array
    {
        return ['exit' => ['exit'], 'timeout' => ['timeout'], 'startup' => ['startup']];
    }

    #[DataProvider('processFailures')]
    public function test_poppler_runtime_failure_is_not_a_skip_or_fallback_and_cleans_input(string $failure): void
    {
        $input = null;
        $process = $this->process(30, 'synthetic private output', false);
        if ($failure === 'timeout') {
            $process->method('getCommandLine')->willReturn('synthetic private command');
            $process->method('getTimeout')->willReturn(30.0);
            $process->expects(self::once())->method('run')->willThrowException(new ProcessTimedOutException($process, ProcessTimedOutException::TYPE_GENERAL));
        } elseif ($failure === 'startup') {
            $process->expects(self::once())->method('run')->willThrowException(new RuntimeException('synthetic private error'));
        } else {
            $process->expects(self::once())->method('run')->willReturn(2);
        }
        $calls = 0;
        $helper = new PdfTextExtractor($this->finder('/tools/pdftotext', '/usr/bin/swiftc'), 'Darwin', true,
            function (array $command) use (&$input, &$calls, $process): Process {
                $calls++;
                $input = $command[4];

                return $process;
            });
        try {
            $helper->extract('%PDF- synthetic');
            self::fail('Broken extraction must fail.');
        } catch (AssertionFailedError $error) {
            self::assertNotInstanceOf(SkippedWithMessageException::class, $error);
            self::assertStringStartsWith('PDF pdftotext failed', $error->getMessage());
            self::assertStringNotContainsString('synthetic private', $error->getMessage());
            self::assertStringNotContainsString($input, $error->getMessage());
        } finally {
            self::assertSame(1, $calls);
            self::assertNotNull($input);
            self::assertDirectoryDoesNotExist(dirname($input));
        }
    }

    public function test_failed_compile_is_cleaned_before_retry_and_partial_binary_is_never_reused(): void
    {
        $directories = [];
        $input = null;
        $failed = $this->process(120, 'synthetic private compiler error', false);
        $failed->expects(self::once())->method('run')->willReturn(1);
        $compiled = $this->process(120);
        $compiled->expects(self::once())->method('run')->willReturn(0);
        $extract = $this->process(30, 'Fresh text');
        $extract->expects(self::once())->method('run')->willReturn(0);
        $helper = new PdfTextExtractor($this->finder(null, '/usr/bin/swiftc'), 'Darwin', true,
            function (array $command) use (&$directories, &$input, $failed, $compiled, $extract): Process {
                if ($command[0] === '/usr/bin/swiftc') {
                    $directory = dirname($command[3]);
                    $directories[] = $directory;
                    mkdir($command[3]);
                    file_put_contents($command[3].'/partial', 'synthetic cache');
                    $first = count($directories) === 1;
                    file_put_contents($directory.'/pdf-text', $first ? 'stale partial binary' : 'fresh binary');
                    chmod($directory.'/pdf-text', 0700);

                    return $first ? $failed : $compiled;
                }
                self::assertCount(2, $directories);
                self::assertSame($directories[1].'/pdf-text', $command[0]);
                self::assertSame('fresh binary', file_get_contents($command[0]));
                $input = $command[1];

                return $extract;
            });
        try {
            try {
                $helper->extract('%PDF- first');
                self::fail('Expected compilation failure.');
            } catch (AssertionFailedError $error) {
                self::assertStringStartsWith('PDF PDFKit compilation failed (nonzero process exit).', $error->getMessage());
            }
            self::assertCount(1, $directories);
            self::assertDirectoryDoesNotExist($directories[0]);
            self::assertSame('Fresh text', $helper->extract('%PDF- retry'));
            self::assertCount(2, $directories);
            self::assertNotSame($directories[0], $directories[1]);
            self::assertDirectoryDoesNotExist(dirname($input));
        } finally {
            $helper->close();
        }
        foreach ($directories as $directory) {
            self::assertDirectoryDoesNotExist($directory);
        }
    }

    public function test_changed_cached_executable_fails_before_another_process_runs(): void
    {
        $binary = null;
        $calls = 0;
        $compile = $this->process(120);
        $compile->expects(self::once())->method('run')->willReturn(0);
        $extract = $this->process(30, 'First extraction');
        $extract->expects(self::once())->method('run')->willReturn(0);
        $helper = new PdfTextExtractor($this->finder(null, '/usr/bin/swiftc'), 'Darwin', true,
            function (array $command) use (&$binary, &$calls, $compile, $extract): Process {
                $calls++;
                if ($command[0] === '/usr/bin/swiftc') {
                    $binary = $command[6];
                    file_put_contents($binary, 'trusted synthetic binary');
                    chmod($binary, 0700);

                    return $compile;
                }

                return $extract;
            });
        try {
            self::assertSame('First extraction', $helper->extract('%PDF- first'));
            file_put_contents($binary, 'untrusted replacement');
            $this->expectException(AssertionFailedError::class);
            $this->expectExceptionMessage('PDFKit cached executable changed.');
            $helper->extract('%PDF- second');
        } finally {
            self::assertSame(2, $calls);
            $helper->close();
            self::assertDirectoryDoesNotExist(dirname($binary));
        }
    }

    public function test_successful_compiler_exit_without_executable_cannot_populate_cache(): void
    {
        $directory = null;
        $compile = $this->process(120);
        $compile->expects(self::once())->method('run')->willReturn(0);
        $helper = new PdfTextExtractor($this->finder(null, '/usr/bin/swiftc'), 'Darwin', true,
            function (array $command) use (&$directory, $compile): Process {
                $directory = dirname($command[3]);

                return $compile;
            });
        try {
            $this->expectException(AssertionFailedError::class);
            $this->expectExceptionMessage('PDFKit compiler did not produce a regular executable.');
            $helper->extract('%PDF- synthetic');
        } finally {
            $helper->close();
            self::assertNotNull($directory);
            self::assertDirectoryDoesNotExist($directory);
        }
    }

    public static function invalidOutput(): array
    {
        return ['empty' => [''], 'blank pages' => [" \n\f\n\f"], 'invalid UTF-8' => ["\xff"]];
    }

    #[DataProvider('invalidOutput')]
    public function test_empty_or_invalid_extracted_text_fails_and_cleans_input(string $output): void
    {
        $input = null;
        $process = $this->process(30, $output);
        $process->expects(self::once())->method('run')->willReturn(0);
        $helper = new PdfTextExtractor($this->finder('/tools/pdftotext'), 'Linux', true,
            function (array $command) use (&$input, $process): Process {
                $input = $command[4];

                return $process;
            });
        try {
            $this->expectException(AssertionFailedError::class);
            $this->expectExceptionMessage('PDF extractor returned');
            $helper->extract('%PDF- synthetic');
        } finally {
            self::assertNotNull($input);
            self::assertDirectoryDoesNotExist(dirname($input));
        }
    }
}
