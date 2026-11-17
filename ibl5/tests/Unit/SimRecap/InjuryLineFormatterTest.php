<?php

declare(strict_types=1);

namespace Tests\Unit\SimRecap;

use PHPUnit\Framework\TestCase;
use SimRecap\InjuryLineFormatter;

final class InjuryLineFormatterTest extends TestCase
{
    public function testMatchesSampleLineByteForByte(): void
    {
        $line = InjuryLineFormatter::formatLine(
            ['name' => 'Bobby Portis', 'teamAbbrev' => 'MIN', 'injuryDescription' => 'left shoulder strain', 'injuryGamesMissed' => 4],
            '123456789012345678',
        );

        self::assertSame('-B.Portis, MIN - left shoulder strain (4) <@123456789012345678>', $line);

        $second = InjuryLineFormatter::formatLine(
            ['name' => 'Bobby Portis', 'teamAbbrev' => 'MIN', 'injuryDescription' => 'blateral patella tendinitis', 'injuryGamesMissed' => 13],
            '123456789012345678',
        );

        self::assertSame('-B.Portis, MIN - blateral patella tendinitis (13) <@123456789012345678>', $second);
    }

    /**
     * Guards the n/j/y date format: a switch to m/d/y would print 01/04/09.
     */
    public function testHeaderDateHasNoZeroPadding(): void
    {
        self::assertSame('**1/4/09**', InjuryLineFormatter::formatHeader('2009-01-04'));
        self::assertSame('**12/25/08**', InjuryLineFormatter::formatHeader('2008-12-25'));
    }

    public function testOmitsMentionWhenTeamHasNoGm(): void
    {
        $line = InjuryLineFormatter::formatLine(
            ['name' => 'Bobby Portis', 'teamAbbrev' => 'MIN', 'injuryDescription' => 'left shoulder strain', 'injuryGamesMissed' => 4],
            null,
        );

        self::assertSame('-B.Portis, MIN - left shoulder strain (4)', $line);
        self::assertStringEndsWith('(4)', $line);
    }

    public function testSingleTokenNameUsedWhole(): void
    {
        $line = InjuryLineFormatter::formatLine(
            ['name' => 'Nene', 'teamAbbrev' => 'DEN', 'injuryDescription' => 'sprained ankle', 'injuryGamesMissed' => 2],
            null,
        );

        self::assertSame('-Nene, DEN - sprained ankle (2)', $line);
    }

    public function testTrimsPaddedNameBeforeShortening(): void
    {
        $line = InjuryLineFormatter::formatLine(
            ['name' => '  Bobby Portis ', 'teamAbbrev' => 'Timberwolves', 'injuryDescription' => 'left shoulder strain', 'injuryGamesMissed' => 4],
            null,
        );

        self::assertSame('-B.Portis, Timberwolves - left shoulder strain (4)', $line);
    }

    /**
     * A multibyte first initial stays one whole character.
     */
    public function testMultibyteInitialKeptWhole(): void
    {
        $line = InjuryLineFormatter::formatLine(
            ['name' => 'Ömer Aşık', 'teamAbbrev' => 'Bulls', 'injuryDescription' => 'sore knee', 'injuryGamesMissed' => 1],
            null,
        );

        self::assertSame('-Ö.Aşık, Bulls - sore knee (1)', $line);
    }
}
