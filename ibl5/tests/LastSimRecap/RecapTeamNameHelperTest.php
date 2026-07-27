<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\RecapTeamNameHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LastSimRecap\RecapTeamNameHelper
 */
final class RecapTeamNameHelperTest extends TestCase
{
    public function testUnmappedNameIsEscapedOnlyWithNoSpans(): void
    {
        $html = RecapTeamNameHelper::responsive('Pistons');

        self::assertSame('Pistons', $html);
        self::assertStringNotContainsString('last-sim-recap__name-full', $html);
        self::assertStringNotContainsString('last-sim-recap__name-short', $html);
    }

    public function testMappedNameRendersBothFullAndShortSpans(): void
    {
        $html = RecapTeamNameHelper::responsive('Trailblazers');

        self::assertSame(
            '<span class="last-sim-recap__name-full">Trailblazers</span>'
            . '<span class="last-sim-recap__name-short">Blazers</span>',
            $html
        );
    }

    public function testSecondMappedNameUsesItsOwnShortForm(): void
    {
        $html = RecapTeamNameHelper::responsive('Timberwolves');

        self::assertStringContainsString('>Timberwolves</span>', $html);
        self::assertStringContainsString('>Wolves</span>', $html);
    }

    public function testMaliciousNameIsEscaped(): void
    {
        $html = RecapTeamNameHelper::responsive('<script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEmptyNameReturnsEmptyString(): void
    {
        self::assertSame('', RecapTeamNameHelper::responsive(''));
    }
}
