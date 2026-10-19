<?php

namespace App\Support;

use Illuminate\Foundation\ComposerScripts;
use Symfony\Component\Process\Process;

final class ComposerHooks
{
    public static function postAutoloadDump($event): void
    {
        if (! self::isolated()) {
            ComposerScripts::postAutoloadDump($event);
            (new Process([self::phpBinary(), 'artisan', 'package:discover', '--ansi'], null, null, null, 120))->mustRun(function ($type, $buffer) {
                fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
            });

            return;
        }

        self::guard();
        ComposerScripts::postAutoloadDump($event); // clears the root-local caches named by APP_*_CACHE
        self::runAmadis('amadis:cache-config');
        self::runAmadis('package:discover', '--ansi');
    }

    public static function postUpdate($event): void
    {
        if (! self::isolated()) {
            (new Process([self::phpBinary(), 'artisan', 'vendor:publish', '--tag=laravel-assets', '--ansi', '--force'], null, null, null, 120))->mustRun(function ($type, $buffer) {
                fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
            });

            return;
        }

        self::guard();
        self::runAmadis('vendor:publish', '--tag=laravel-assets', '--ansi', '--force');
    }

    public static function prePackageUninstall($event): void
    {
        if (self::isolated()) {
            self::guard();
            throw new \RuntimeException('Package uninstall is refused in isolation.');
        }

        ComposerScripts::prePackageUninstall($event);
    }

    private static function isolated(): bool
    {
        // Any AMADIS_* variable or root-local cache path means isolation was intended: guard() must then pass or fail closed.
        foreach (array_keys(getenv()) as $name) {
            if (str_starts_with((string) $name, 'AMADIS_')) {
                return true;
            }
        }

        // Any cache/storage override is isolation intent; the normal workflow never sets these, so guard() decides.
        foreach (['APP_CONFIG_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'LARAVEL_STORAGE_PATH', 'VIEW_COMPILED_PATH'] as $name) {
            $value = getenv($name);
            if ($value !== false && $value !== '') {
                return true;
            }
        }

        return false;
    }

    // Composer exports PHP_BINARY for scripts, matching its own @php resolution.
    private static function phpBinary(): string
    {
        $binary = getenv('PHP_BINARY');

        return is_string($binary) && $binary !== '' ? $binary : PHP_BINARY;
    }

    public static function guard(): string
    {
        require_once dirname(__DIR__, 2).'/scripts/amadis/guard.php';
        $root = amadisGuard();
        $identity = json_decode(file_get_contents($root.'/identity.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($identity['repo'] ?? null) !== dirname(__DIR__, 2)) {
            throw new \RuntimeException('Isolation repository identity mismatch.');
        }
        foreach (['LARAVEL_STORAGE_PATH' => 'storage', 'APP_CONFIG_CACHE' => 'bootstrap/config.php', 'APP_SERVICES_CACHE' => 'bootstrap/services.php', 'APP_PACKAGES_CACHE' => 'bootstrap/packages.php', 'APP_ROUTES_CACHE' => 'bootstrap/routes.php', 'APP_EVENTS_CACHE' => 'bootstrap/events.php', 'VIEW_COMPILED_PATH' => 'storage/framework/views'] as $key => $relative) {
            $path = $root.'/'.$relative;
            if (getenv($key) !== $path) {
                throw new \RuntimeException("Isolation rejected: invalid $key.");
            }
            $cursor = $root;
            foreach (explode('/', $relative) as $part) {
                $cursor .= '/'.$part;
                if (is_link($cursor)) {
                    throw new \RuntimeException("Isolation rejected: linked $key path.");
                }
            }
        }
        foreach (['bootstrap', 'storage', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $relative) {
            $path = $root.'/'.$relative;
            if (! is_dir($path) || realpath($path) !== $path) {
                throw new \RuntimeException('Isolation requires canonical cache and storage directories.');
            }
        }
        foreach (['bootstrap', 'storage'] as $relative) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$relative, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $entry) {
                if ($entry->isLink() || (! $entry->isDir() && (! $entry->isFile() || lstat($entry->getPathname())['nlink'] !== 1))) {
                    throw new \RuntimeException('Isolation refuses linked or special cache/storage resources.');
                }
            }
        }
        if (getcwd() !== dirname(__DIR__, 2) || file_exists($root.'/.env') || is_link($root.'/.env')) {
            throw new \RuntimeException('Isolation requires repository cwd and no environment file.');
        }

        return $root;
    }

    private static function runAmadis(string ...$arguments): void
    {
        $process = new Process([PHP_BINARY, 'scripts/amadis/artisan.php', ...$arguments], getcwd(), null, null, 120);
        $process->mustRun();
    }
}
