<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfDocumentService
{
    private const LINES_PER_PAGE = 44;

    private const MAX_LINE_LENGTH = 90;

    /** @param list<string> $lines */
    public function render(string $title, array $lines, string $pagination = 'Halaman {PAGE_NUM} dari {PAGE_COUNT}'): string
    {
        $cacheDir = storage_path('app/private/pdf');
        if (! is_dir($cacheDir) && ! mkdir($cacheDir, 0700, true) && ! is_dir($cacheDir)) {
            throw new \RuntimeException('PDF storage directory unavailable.');
        }
        $storage = realpath(storage_path());
        if ($storage === false || ! str_starts_with(realpath($cacheDir).DIRECTORY_SEPARATOR, $storage.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('PDF cache must remain inside application storage.');
        }

        $fontDir = resource_path('fonts');
        $font = $fontDir.'/NotoSansSC-Regular.ttf';
        if (! is_file($font) || ! is_readable($font)) {
            throw new \RuntimeException('PDF font unavailable.');
        }

        // Noto SC lacks Latin Extended-A glyphs such as Ĳ/ĳ/Ł/ł; DejaVu Sans (bundled with the pinned Dompdf) covers them.
        $fallbackDir = dirname((new \ReflectionClass(Dompdf::class))->getFileName(), 2).'/lib/fonts';
        $fallback = $fallbackDir.'/DejaVuSans.ttf';
        if (! is_file($fallback) || ! is_readable($fallback)) {
            throw new \RuntimeException('PDF fallback font unavailable.');
        }

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setAllowedProtocols(['file://']);
        $options->setChroot([$fontDir, $fallbackDir, $cacheDir]);
        $options->setFontDir($cacheDir);
        $options->setFontCache($cacheDir);
        $options->setTempDir($cacheDir);

        // Noto's CJK cmap needs more than PHP's default 128 MB during font parsing/subsetting.
        $memoryLimit = ini_get('memory_limit');
        $raised = false;
        if ($memoryLimit !== false && $memoryLimit !== '-1' && ini_parse_quantity($memoryLimit) < 268435456) {
            $raised = ini_set('memory_limit', '256M') !== false;
        }
        try {
            $dompdf = new Dompdf($options);
            $metrics = $dompdf->getFontMetrics();
            if (! $metrics->registerFont(['family' => 'Report', 'style' => 'normal', 'weight' => 'normal'], $font)) {
                throw new \RuntimeException('PDF font registration failed.');
            }
            if (! $metrics->registerFont(['family' => 'ReportLatin', 'style' => 'normal', 'weight' => 'normal'], $fallback)) {
                throw new \RuntimeException('PDF fallback font registration failed.');
            }
            $reportFont = $metrics->getFont('Report', 'normal');
            $textWidth = static function (string $text) use ($metrics, $reportFont): float {
                $width = 0;
                foreach ($metrics->mapTextToFonts($text, ['Report', 'ReportLatin']) as $start => $run) {
                    $part = mb_substr($text, $start, $run['length'] ?: null);
                    $width += $metrics->getTextWidth($part, $run['font'] ?? $reportFont, 10);
                }

                return $width;
            };
            $wrapped = [];
            foreach ($lines as $line) {
                $line = $this->clean($line);
                do {
                    $length = min(mb_strlen($line), self::MAX_LINE_LENGTH);
                    while ($length > 1 && $textWidth(mb_substr($line, 0, $length)) > 483) {
                        $length--;
                    }
                    if ($length < mb_strlen($line)) {
                        $space = mb_strrpos(mb_substr($line, 0, $length + 1), ' ');
                        if ($space !== false && $space > 0) {
                            $length = $space;
                        }
                    }
                    $wrapped[] = rtrim(mb_substr($line, 0, $length));
                    $line = ltrim(mb_substr($line, $length));
                } while ($line !== '');
            }
            $pages = array_chunk($wrapped, self::LINES_PER_PAGE) ?: [[]];
            $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
                .'@page{margin:56pt 56pt 50pt;}'
                .'body{font-family:Report,ReportLatin;font-size:10pt;line-height:14pt;margin:0;}'
                .'h1{font-size:15pt;font-weight:normal;line-height:20pt;margin:0 0 6pt;overflow-wrap:break-word;}'
                .'.line{height:14pt;white-space:pre;}'
                .'</style></head><body>';
            foreach ($pages as $number => $page) {
                if ($number > 0) {
                    $html .= '<div style="page-break-before:always"></div>';
                }
                $html .= '<h1>'.$this->escape($this->clean($title)).'</h1>';
                foreach ($page as $line) {
                    $html .= '<div class="line">'.$this->escape($line).'</div>';
                }
            }
            $html .= '</body></html>';

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4');
            $dompdf->render();
            $dompdf->getCanvas()->page_text(56, 794, $pagination, $reportFont, 8);

            return $dompdf->output();
        } finally {
            unset($dompdf, $metrics, $reportFont);
            gc_collect_cycles();
            // Restore only when the original limit is still above current usage; PHP rejects lower limits otherwise.
            if ($raised && memory_get_usage(true) < ini_parse_quantity($memoryLimit)) {
                ini_set('memory_limit', $memoryLimit);
            }
        }
    }

    private function clean(string $text): string
    {
        $previous = mb_substitute_character();
        try {
            mb_substitute_character(0x3F);
            $text = mb_scrub($text, 'UTF-8');
        } finally {
            mb_substitute_character($previous);
        }
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        return preg_replace('/\p{Cc}/u', '?', $text) ?? '';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
