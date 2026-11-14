<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fallback static asset server for `/prototype/*` when Git symlinks in `public/`
 * are checked out as plain-text stubs (e.g. Windows without Developer Mode).
 */
final class PrototypeAssetController extends Controller
{
    private const MIME_TYPES = [
        'avif' => 'image/avif',
        'css' => 'text/css; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'ico' => 'image/x-icon',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain; charset=UTF-8',
        'webmanifest' => 'application/manifest+json; charset=UTF-8',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function __invoke(Request $request, string $path = 'index.html'): Response
    {
        $relative = trim($path, '/');
        if ($relative === '') {
            $relative = 'index.html';
        }

        if (str_contains($relative, "\0") || str_contains($relative, '\\')) {
            return $this->notFound();
        }

        $segments = explode('/', $relative);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
                return $this->notFound();
            }
        }

        $root = realpath(base_path('prototype'));
        if ($root === false) {
            return $this->notFound();
        }

        $candidate = $root.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
        if (is_link($candidate)) {
            return $this->notFound();
        }

        if (is_dir($candidate)) {
            $candidate .= DIRECTORY_SEPARATOR.'index.html';
            if (is_link($candidate)) {
                return $this->notFound();
            }
        }

        $real = realpath($candidate);
        if ($real === false || ! is_file($real) || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            return $this->notFound();
        }

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (! isset(self::MIME_TYPES[$ext])) {
            return $this->notFound();
        }

        $mtime = filemtime($real) ?: time();
        $size = (int) filesize($real);
        $etag = '"'.dechex($mtime).'-'.dechex($size).'"';

        $response = new BinaryFileResponse(
            $real,
            200,
            ['Content-Type' => self::MIME_TYPES[$ext]],
            $ext !== 'html',
            null,
            false,
            true,
        );

        if ($ext === 'html') {
            $response->headers->set('Cache-Control', 'no-cache');
        } else {
            $response->setPublic();
            $response->setMaxAge(3600);
        }

        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }

    private function notFound(): Response
    {
        return response('', 404, ['Cache-Control' => 'no-store']);
    }
}
