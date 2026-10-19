<?php

namespace Tests\Feature;

use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

class AdminTableCssTouchTargetTest extends TestCase
{
    private const MOBILE_BLOCK = '/@media\s*\(max-width:\s*900px\)\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s';

    public function test_admin_table_controls_have_touch_targets_and_mobile_safe_font_sizes(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.admin-table\s+:is\(a,\s*button,\s*\.admin-Switch,\s*\.admin-danger,\s*\.btn-sm\)\s*\{[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;/s',
            $css
        );
        $this->assertGlobalSelectContract($css);
        $this->assertMobileFontContract($css);
    }

    public function test_mobile_font_assertions_reject_in_memory_css_regressions(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);
        $mutations = [
            'mobile blocks removed' => preg_replace(self::MOBILE_BLOCK, '', $css),
            'admin input removed' => str_replace('.admin-table :is(select, input:', '.admin-table :is(select, button:', $css),
            'admin select removed' => str_replace('.admin-table :is(select,', '.admin-table :is(button,', $css),
            'status input removed' => str_replace('.status-form :is(select, input:', '.status-form :is(select, button:', $css),
            'status select removed' => str_replace('.status-form :is(select,', '.status-form :is(button,', $css),
            'global status scope narrowed' => str_replace(', .status-form :is(', ', .admin-table .status-form :is(', $css),
            'font reduced' => str_replace('font-size: 16px;', 'font-size: 15px;', $css),
            'global font declaration removed' => str_replace('min-height: 44px; font-size: 16px;', 'min-height: 44px;', $css),
            'global min-height declaration removed' => str_replace('min-height: 44px; font-size: 16px;', 'font-size: 16px;', $css),
            'global select scope narrowed' => str_replace('.admin-table select, .admin-table .status-form select', '.admin-table select.disabled, .admin-table .status-form select', $css),
            'mobile font declaration removed' => str_replace(
                '.status-form :is(select, input:not([type="checkbox"]):not([type="hidden"])) { font-size: 16px; }',
                '.status-form :is(select, input:not([type="checkbox"]):not([type="hidden"])) { line-height: 1.5; }',
                $css
            ),
        ];

        foreach ($mutations as $label => $mutated) {
            $this->assertIsString($mutated);
            $this->assertNotSame($css, $mutated, "Mutation did not apply: {$label}");

            try {
                $this->assertGlobalSelectContract($mutated);
                $this->assertMobileFontContract($mutated);
            } catch (ExpectationFailedException) {
                continue;
            }

            $this->fail("Admin select font contract accepted regression: {$label}");
        }
    }

    public function test_contracts_tolerate_comments_and_declaration_reorder(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);
        $equivalent = str_replace(
            [
                '.admin-table select, .admin-table .status-form select { min-height: 44px; font-size: 16px; }',
                '.admin-table :is(select, input:not([type="checkbox"]):not([type="hidden"]), textarea), .status-form :is(select, input:not([type="checkbox"]):not([type="hidden"])) { font-size: 16px; }',
            ],
            [
                "/* note */ .admin-table select, /* scoped */ .admin-table .status-form select {\n    font-size: 16px; /* iOS */\n    min-height: 44px;\n}",
                "/* mobile */ .admin-table :is(select, input:not([type=\"checkbox\"]):not([type=\"hidden\"]), textarea),\n    .status-form :is(select, input:not([type=\"checkbox\"]):not([type=\"hidden\"])) {\n    /* auto-zoom */\n    line-height: 1.5;\n    font-size: 16px;\n}",
            ],
            $css
        );

        $this->assertNotSame($css, $equivalent);
        $this->assertGlobalSelectContract($equivalent);
        $this->assertMobileFontContract($equivalent);
    }

    /** Global admin select rule: exact selector list at a rule boundary, with both declarations in any order. */
    private function assertGlobalSelectContract(string $css): void
    {
        $css = $this->stripComments($css);
        $selector = '\.admin-table\s+select\s*,\s*\.admin-table\s+\.status-form\s+select';

        $this->assertMatchesRegularExpression(
            '/(?:^|[{}])\s*'.$selector.'\s*\{([^{}]*)\}/s',
            $css,
            'Global admin select rule must target exactly ".admin-table select, .admin-table .status-form select".'
        );
        preg_match('/(?:^|[{}])\s*'.$selector.'\s*\{([^{}]*)\}/s', $css, $rule);
        $this->assertMatchesRegularExpression('/(?:^|;)\s*min-height:\s*44px\s*(?:;|$)/', trim($rule[1]), 'Global admin select rule lacks min-height: 44px.');
        $this->assertMatchesRegularExpression('/(?:^|;)\s*font-size:\s*16px\s*(?:;|$)/', trim($rule[1]), 'Global admin select rule lacks font-size: 16px.');
    }

    private function stripComments(string $css): string
    {
        return preg_replace('/\/\*.*?\*\//s', '', $css) ?? $css;
    }

    private function assertMobileFontContract(string $css): void
    {
        preg_match_all(self::MOBILE_BLOCK, $this->stripComments($css), $blocks);
        $mobileCss = implode("\n", $blocks[1]);
        $input = 'input:not\(\[type="checkbox"\]\):not\(\[type="hidden"\]\)';
        $admin = '\.admin-table\s+:is\(select,\s*'.$input.',\s*textarea\)';
        $status = '\.status-form\s+:is\(select,\s*'.$input.'\)';

        $pattern = '/(?:^|[{}])\s*'.$admin.'\s*,\s*'.$status.'\s*\{([^{}]*)\}/s';
        $this->assertMatchesRegularExpression(
            $pattern,
            $mobileCss,
            'The 900px mobile block must protect admin select/input and globally scoped status-form select/input.'
        );
        preg_match($pattern, $mobileCss, $rule);
        $this->assertMatchesRegularExpression(
            '/(?:^|;)\s*font-size:\s*16px\s*(?:;|$)/',
            trim($rule[1] ?? ''),
            'Mobile rule for admin controls and status-form controls lacks font-size: 16px.'
        );
    }
}
