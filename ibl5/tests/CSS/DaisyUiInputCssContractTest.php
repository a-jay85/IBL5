<?php

declare(strict_types=1);

namespace Tests\CSS;

use PHPUnit\Framework\TestCase;

/**
 * Pins the daisyUI wiring in design/input.css: the d- prefix, the excluded global
 * base styles, the token-backed ibl theme, and the unnested utilities override block.
 */
class DaisyUiInputCssContractTest extends TestCase
{
    /**
     * Mutation: change `prefix: "d-"` to `prefix: ""`.
     */
    public function testDaisyUiPluginUsesDPrefix(): void
    {
        $plugins = self::blocksWithPrelude('@plugin "daisyui"');
        $this->assertCount(1, $plugins, 'Expected exactly one @plugin "daisyui" block');
        $body = $plugins[0];

        $this->assertMatchesRegularExpression('/prefix:\s*"d-";/', $body);
        $this->assertMatchesRegularExpression('/themes:\s*ibl\s+--default;/', $body);
        $this->assertMatchesRegularExpression('/logs:\s*false;/', $body);
    }

    /**
     * Mutation: drop `scrollbar` from the exclude list.
     */
    public function testDaisyUiPluginExcludesGlobalBaseStyles(): void
    {
        $body = self::pluginBody();
        $this->assertSame(1, preg_match('/exclude:\s*([^;]*);/', $body, $m), 'Plugin block has no exclude list');

        $excluded = array_map('trim', explode(',', $m[1]));
        sort($excluded);
        $expected = ['properties', 'rootcolor', 'rootscrollgutter', 'scrollbar'];

        $this->assertSame($expected, $excluded);
    }

    /**
     * Mutation: move the plugin blocks below `@theme`.
     */
    public function testDaisyUiPluginBlocksFollowTailwindImport(): void
    {
        $css = self::css();
        $import = strpos($css, "@import 'tailwindcss';");
        $plugin = strpos($css, '@plugin "daisyui" {');
        $theme = strpos($css, '@plugin "daisyui/theme" {');
        $twTheme = strpos($css, '@theme {');

        $this->assertIsInt($import, "Missing @import 'tailwindcss';");
        $this->assertIsInt($plugin, 'Missing @plugin "daisyui" block');
        $this->assertIsInt($theme, 'Missing @plugin "daisyui/theme" block');
        $this->assertIsInt($twTheme, 'Missing @theme block');

        $this->assertLessThan($plugin, $import);
        $this->assertLessThan($theme, $plugin);
        $this->assertLessThan($twTheme, $theme);
    }

    /**
     * Mutation: rename the theme to `"light"`.
     */
    public function testDaisyUiThemeIsNamedIblAndDefault(): void
    {
        $body = self::themeBody();

        $this->assertMatchesRegularExpression('/name:\s*"ibl";/', $body);
        $this->assertMatchesRegularExpression('/default:\s*true;/', $body);
        $this->assertMatchesRegularExpression('/color-scheme:\s*light;/', $body);
    }

    /**
     * Mutation: restore `--color-primary: #111827;`.
     */
    public function testDaisyUiThemeReferencesTokensWithoutHex(): void
    {
        $body = self::themeBody();

        $this->assertSame(0, preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $body), 'Theme block must not repeat hex values');
        foreach (['--color-primary', '--color-error', '--color-base-100'] as $var) {
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($var, '/') . ':\s*var\(--color-/',
                $body,
                "$var must reference a @theme token"
            );
        }
    }

    /**
     * Mutation: wrap the rules in `@layer daisyui.override { ... }` or move the block.
     */
    public function testBrandOverridesSitInUnnestedUtilitiesLayer(): void
    {
        $body = self::overrideBody();

        $this->assertStringNotContainsString('@layer', $body, 'Override block must not nest a @layer');
    }

    /**
     * Mutation: move the override block into `@layer components`.
     */
    public function testBrandOverridesAreAbsentFromComponentsLayer(): void
    {
        foreach (self::topLevelBlocks(self::css()) as $block) {
            if (str_starts_with($block['prelude'], '@layer components')) {
                $this->assertStringNotContainsString('.d-', $block['body'], 'daisyUI overrides lose inside @layer components');
            }
        }
    }

    /**
     * Mutation: swap `var(--font-display)` for a literal font stack.
     */
    public function testBrandFontRuleUsesDisplayTokens(): void
    {
        $body = self::overrideBody();

        $this->assertStringContainsString('font-family: var(--font-display)', $body);
        $this->assertStringContainsString('text-transform: uppercase', $body);
        $this->assertStringContainsString('letter-spacing: var(--tracking-display)', $body);
        $this->assertStringNotContainsString('var(--color-', $body);
    }

    /**
     * Mutation: replace `:where(.d-label)` with `.d-label`.
     */
    public function testLabelOverrideUsesZeroSpecificityWhere(): void
    {
        $body = self::overrideBody();

        $this->assertSame(1, preg_match('/:where\(\.d-label\)\s*\{([^}]*)\}/', $body, $m), 'Missing :where(.d-label) rule');
        $this->assertStringContainsString('white-space: normal', $m[1]);
        $this->assertStringContainsString('font-size: 1rem', $m[1]);

        preg_match_all('/(?<![\w(-])\.d-label\s*\{([^}]*)\}/', $body, $bare);
        foreach ($bare[1] as $rule) {
            $this->assertStringNotContainsString('font-size', $rule, 'A bare .d-label rule must not set font-size');
        }
    }

    /**
     * Mutation: add an `.ibl-card` rule to the override block.
     */
    public function testDaisyUiBlocksAddNoIblSelector(): void
    {
        foreach ([self::pluginBody(), self::themeBody(), self::overrideBody()] as $body) {
            $this->assertStringNotContainsString('.ibl-', $body);
        }
    }

    /**
     * Mutation: delete the Updater guard rule.
     */
    public function testUpdaterTextErrorGuardSitsInUnnestedUtilitiesLayer(): void
    {
        $body = self::overrideBody();

        $this->assertMatchesRegularExpression(
            '/\.updater-log \.text-error,\s*\.updater-details__content \.text-error\s*\{[^}]*color:\s*var\(--error-500\)/',
            $body
        );
    }

    /** Strip CSS comments so commented-out text never satisfies an assertion. */
    private static function css(): string
    {
        $path = dirname(__DIR__, 2) . '/design/input.css';
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        // Match quoted strings too and keep them, so a glob like "../*.log" never opens a comment.
        $stripped = preg_replace_callback(
            '#"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|/\*.*?\*/#s',
            static fn (array $m): string => str_starts_with($m[0], '/*') ? '' : $m[0],
            $content
        );
        self::assertIsString($stripped);

        return $stripped;
    }

    /**
     * Walk the stylesheet once, tracking brace depth, and return every TOP-LEVEL block
     * as ['prelude' => trimmed text before '{', 'body' => text between the matching braces].
     * Reset the prelude buffer on ';' at depth 0 so @import lines never leak into a prelude.
     *
     * @return list<array{prelude: string, body: string}>
     */
    private static function topLevelBlocks(string $css): array
    {
        $blocks = [];
        $depth = 0;
        $prelude = '';
        $body = '';
        $length = strlen($css);

        for ($i = 0; $i < $length; $i++) {
            $ch = $css[$i];
            if ($depth === 0) {
                if ($ch === '{') {
                    $depth = 1;
                    $body = '';
                } elseif ($ch === ';') {
                    $prelude = '';
                } else {
                    $prelude .= $ch;
                }
                continue;
            }

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $blocks[] = ['prelude' => trim($prelude), 'body' => $body];
                    $prelude = '';
                    continue;
                }
            }
            $body .= $ch;
        }

        return $blocks;
    }

    /** @return list<string> */
    private static function blocksWithPrelude(string $prelude): array
    {
        $bodies = [];
        foreach (self::topLevelBlocks(self::css()) as $block) {
            if ($block['prelude'] === $prelude) {
                $bodies[] = $block['body'];
            }
        }

        return $bodies;
    }

    private static function pluginBody(): string
    {
        $bodies = self::blocksWithPrelude('@plugin "daisyui"');
        self::assertCount(1, $bodies, 'Expected exactly one @plugin "daisyui" block');

        return $bodies[0];
    }

    private static function themeBody(): string
    {
        $bodies = self::blocksWithPrelude('@plugin "daisyui/theme"');
        self::assertCount(1, $bodies, 'Expected exactly one @plugin "daisyui/theme" block');

        return $bodies[0];
    }

    /** The top-level `@layer utilities` block that carries the daisyUI brand overrides. */
    private static function overrideBody(): string
    {
        foreach (self::blocksWithPrelude('@layer utilities') as $body) {
            if (
                str_contains($body, '.d-label')
                && str_contains($body, '.d-card-title')
                && str_contains($body, '.d-fieldset-legend')
                && str_contains($body, '.d-btn')
            ) {
                return $body;
            }
        }
        self::fail('No top-level @layer utilities block holds the .d-label, .d-card-title, .d-fieldset-legend, .d-btn overrides');
    }
}
