<?php

namespace Tests\Unit;

use App\Support\ComposerHooks;
use PHPUnit\Framework\TestCase;

final class ComposerHooksTest extends TestCase
{
    public function test_composer_scripts_route_through_guarded_adapter(): void
    {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $hooks = file_get_contents(dirname(__DIR__, 2).'/app/Support/ComposerHooks.php');

        self::assertSame(['App\\Support\\ComposerHooks::postAutoloadDump'], $composer['scripts']['post-autoload-dump']);
        self::assertSame(['App\\Support\\ComposerHooks::postUpdate'], $composer['scripts']['post-update-cmd']);
        self::assertSame(['App\\Support\\ComposerHooks::prePackageUninstall'], $composer['scripts']['pre-package-uninstall']);
        self::assertStringContainsString("self::runAmadis('package:discover', '--ansi')", $hooks);
        self::assertStringContainsString("self::runAmadis('vendor:publish', '--tag=laravel-assets', '--ansi', '--force')", $hooks);
        self::assertStringContainsString('Package uninstall is refused in isolation.', $hooks);
        self::assertStringContainsString('Isolation repository identity mismatch.', $hooks);
    }

    public function test_cache_override_without_amadis_markers_fails_closed_before_framework_callback(): void
    {
        $saved = [];
        foreach (getenv() as $name => $value) {
            if (str_starts_with($name, 'AMADIS_') || $name === 'APP_CONFIG_CACHE') {
                $saved[$name] = $value;
                putenv($name);
            }
        }
        putenv('APP_CONFIG_CACHE=/private/var/tmp/elsewhere/config.php');
        try {
            ComposerHooks::prePackageUninstall(null);
            self::fail('Partial isolation must not fall through to the normal path.');
        } catch (\RuntimeException $e) {
            self::assertStringStartsWith('Isolation rejected', $e->getMessage());
        } finally {
            putenv('APP_CONFIG_CACHE');
            foreach ($saved as $name => $value) {
                putenv($name.'='.$value);
            }
        }
    }

    public function test_guard_accepts_the_launcher_root_and_fails_closed_on_a_moved_cache(): void
    {
        $root = getenv('AMADIS_ROOT');
        if (! $root) {
            self::markTestSkipped('Run through scripts/amadis/launch.sh test.');
        }

        self::assertSame($root, ComposerHooks::guard());

        $original = getenv('APP_CONFIG_CACHE');
        putenv('APP_CONFIG_CACHE='.$root.'/bootstrap/moved.php');
        try {
            $this->expectException(\RuntimeException::class);
            ComposerHooks::guard();
        } finally {
            putenv('APP_CONFIG_CACHE='.$original);
        }
    }
}
