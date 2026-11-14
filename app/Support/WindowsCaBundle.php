<?php

namespace App\Support;

final class WindowsCaBundle
{
    private const CANDIDATES = [
        'C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt',
        'C:/Program Files/Git/usr/ssl/certs/ca-bundle.crt',
    ];

    public static function resolve(): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows' || trim((string) ini_get('curl.cainfo')) !== '' || trim((string) ini_get('openssl.cafile')) !== '') {
            return null;
        }

        foreach (self::CANDIDATES as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}

