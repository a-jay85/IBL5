<?php

declare(strict_types=1);

namespace Tests\UI;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UI\TableStyles;

#[CoversClass(TableStyles::class)]
class TableStylesTest extends TestCase
{
    public function testSanitizeColorAcceptsValidHex(): void
    {
        self::assertSame('1e3a5f', TableStyles::sanitizeColor('1e3a5f'));
        self::assertSame('FFF', TableStyles::sanitizeColor('FFF'));
        self::assertSame('abc', TableStyles::sanitizeColor('#abc'));
    }

    public function testSanitizeColorRejectsInjectionVector(): void
    {
        self::assertSame('000000', TableStyles::sanitizeColor('red; background:url(javascript:alert(1))'));
        self::assertSame('000000', TableStyles::sanitizeColor("'/**/"));
        self::assertSame('000000', TableStyles::sanitizeColor('1e3a5f; --evil: 1'));
        self::assertSame('000000', TableStyles::sanitizeColor(''));
        self::assertSame('000000', TableStyles::sanitizeColor('not-hex'));
    }

    public function testInlineTeamVarsEmitsCanonicalNames(): void
    {
        $vars = TableStyles::inlineTeamVars('1e3a5f', 'D4AF37');
        self::assertSame('--team-color-primary: #1e3a5f; --team-color-secondary: #D4AF37;', $vars);
    }

    public function testInlineTeamVarsSanitizesBothColors(): void
    {
        $vars = TableStyles::inlineTeamVars('red; evil', "'/**/");
        self::assertSame('--team-color-primary: #000000; --team-color-secondary: #000000;', $vars);
    }

    public function testInlineTeamVarsDefaultOmitsOnPrimaryVar(): void
    {
        $vars = TableStyles::inlineTeamVars('003DA5','FF5733');
        self::assertSame('--team-color-primary: #003DA5; --team-color-secondary: #FF5733;', $vars);
    }

    public function testInlineTeamVarsOnPrimaryFallsBackForCiSeedPair(): void
    {
        $vars = TableStyles::inlineTeamVars('003DA5', 'FF5733', true);
        self::assertStringEndsWith(' --team-color-on-primary: #ffffff;', $vars);
    }

    public function testInlineTeamVarsOnPrimaryKeepsPassingSecondary(): void
    {
        $vars = TableStyles::inlineTeamVars('003DA5', 'FFFFFF', true);
        self::assertStringEndsWith(' --team-color-on-primary: #FFFFFF;', $vars);
    }

    public function testInlineTeamVarsOnPrimarySanitizesInjectedColor(): void
    {
        $vars = TableStyles::inlineTeamVars('003DA5', 'red;}body{x', true);
        self::assertStringNotContainsString(';}', $vars);
        self::assertStringNotContainsString('body', $vars);
        self::assertStringEndsWith(' --team-color-on-primary: #ffffff;', $vars);
    }
}
