<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Memastikan konfigurasi lingkungan seragam dan reproducible (PHP, Node, Composer).
 */
class EnvironmentUniformityTest extends TestCase
{
    public function test_node_version_is_pinned_in_nvmrc_and_package(): void
    {
        $root = dirname(__DIR__, 2);

        $this->assertFileExists($root.'/.nvmrc');
        $nvmrc = trim(file_get_contents($root.'/.nvmrc'));
        $this->assertSame('22', $nvmrc, '.nvmrc harus menargetkan Node 22 LTS');

        $package = json_decode(file_get_contents($root.'/package.json'), true);
        $this->assertSame('^22.12.0 || >=24.0.0', $package['engines']['node'] ?? null, 'Minimum Node harus sesuai Vite/plugin');
        $lock = json_decode(file_get_contents($root.'/package-lock.json'), true);
        $this->assertSame($package['engines']['node'], $lock['packages']['']['engines']['node'] ?? null);

        $ci = file_get_contents($root.'/.github/workflows/ci.yml');
        $this->assertStringContainsString('node-version-file: .nvmrc', $ci, 'CI harus membaca .nvmrc');
    }

    public function test_php_version_is_pinned_in_composer(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode(file_get_contents($root.'/composer.json'), true);
        $this->assertSame('^8.4.1', $composer['require']['php'] ?? null, 'composer.json harus menuntut PHP ^8.4.1');

        $ci = file_get_contents($root.'/.github/workflows/ci.yml');
        $this->assertStringContainsString("php-version: '8.4'", $ci, 'CI harus memakai PHP 8.4');
    }

    public function test_branch_protection_script_is_syntactically_valid(): void
    {
        $script = dirname(__DIR__, 2).'/scripts/ci/protect-main.sh';
        $this->assertFileExists($script);
        $content = file_get_contents($script);
        $this->assertStringContainsString('"checks": [{"context": "php", "app_id": 15368}]', $content);
        $this->assertStringContainsString('gh api -X PUT', $content);
    }
}
