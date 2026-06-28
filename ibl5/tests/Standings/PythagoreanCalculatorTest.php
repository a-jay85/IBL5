<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\TestCase;
use Standings\PythagoreanCalculator;

/**
 * @covers \Standings\PythagoreanCalculator
 */
class PythagoreanCalculatorTest extends TestCase
{
    public function testCalculateReturnsPointsScoredAndAllowed(): void
    {
        // Same inputs the existing StandingsRepository characterization pins:
        // pointsScored = 2*1000 + 500 + 300 = 2800
        // pointsAllowed = 2*900 + 450 + 250 = 2500
        $stats = [
            'off_fgm' => 1000, 'off_ftm' => 500, 'off_tgm' => 300,
            'def_fgm' => 900, 'def_ftm' => 450, 'def_tgm' => 250,
        ];

        $result = (new PythagoreanCalculator())->calculate($stats);

        $this->assertSame(['pointsScored' => 2800, 'pointsAllowed' => 2500], $result);
    }

    public function testCalculateWithAllZeroInputsReturnsZeroPoints(): void
    {
        // Boundary: all-zero shooting yields 0/0. No division occurs -- calculatePoints
        // is 2*fgm + ftm + tgm, so zero inputs return zero, not a guarded value.
        $stats = [
            'off_fgm' => 0, 'off_ftm' => 0, 'off_tgm' => 0,
            'def_fgm' => 0, 'def_ftm' => 0, 'def_tgm' => 0,
        ];

        $result = (new PythagoreanCalculator())->calculate($stats);

        $this->assertSame(['pointsScored' => 0, 'pointsAllowed' => 0], $result);
    }

    public function testCalculateIgnoresExtraKeysOnTolerantShape(): void
    {
        // Call site #2 (getAllPythagoreanStats, L237) passes $row, which also carries
        // `teamid` (and other) keys. The `...<string, mixed>` @param tail must accept
        // them and the calc must ignore them — pins that the extra-key call site is safe.
        $stats = [
            'off_fgm' => 1000, 'off_ftm' => 500, 'off_tgm' => 300,
            'def_fgm' => 900, 'def_ftm' => 450, 'def_tgm' => 250,
            'teamid' => 7,
        ];

        $result = (new PythagoreanCalculator())->calculate($stats);

        $this->assertSame(['pointsScored' => 2800, 'pointsAllowed' => 2500], $result);
    }
}
