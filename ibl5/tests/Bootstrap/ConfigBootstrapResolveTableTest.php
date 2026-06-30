<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\ConfigBootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Negative-path coverage for ConfigBootstrap::resolveConfigTable().
 *
 * loadNukeConfig() builds `SELECT * FROM \`<prefix>_config\`` from the
 * operator-controlled `$GLOBALS['prefix']`. The table name is a SQL identifier
 * (never a bound value), so resolveConfigTable() validates it against a
 * conservative identifier pattern and falls back to the default `nuke_config`
 * for any non-conforming value — an injection-style prefix can never reach the
 * query text.
 */
final class ConfigBootstrapResolveTableTest extends TestCase
{
    public function testValidPrefixResolvesToPrefixedTable(): void
    {
        self::assertSame('nuke_config', ConfigBootstrap::resolveConfigTable('nuke'));
        self::assertSame('iblhoops_config', ConfigBootstrap::resolveConfigTable('iblhoops'));
        self::assertSame('my_app_config', ConfigBootstrap::resolveConfigTable('my_app'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function injectionStylePrefixProvider(): array
    {
        return [
            'sql injection with backtick' => ['nuke`; DROP TABLE users; --'],
            'path traversal'              => ['../etc'],
            'space-separated clause'      => ['nuke OR 1=1'],
            'semicolon'                   => ['nuke;'],
            'empty string'                => [''],
            'whitespace only'             => ['   '],
            'parenthesis'                 => ['nuke(select)'],
        ];
    }

    #[DataProvider('injectionStylePrefixProvider')]
    public function testInjectionStylePrefixFallsBackToSafeDefault(string $prefix): void
    {
        $resolved = ConfigBootstrap::resolveConfigTable($prefix);

        self::assertSame('nuke_config', $resolved);
        if (trim($prefix) !== '') {
            self::assertStringNotContainsString($prefix, $resolved);
        }
    }
}
