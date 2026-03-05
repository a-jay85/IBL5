<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Boxscore date mapping, particularly Olympics league handling.
 *
 * The .sco binary encodes months as offsets from October (0=Oct, 1=Nov, ...).
 * For Olympics, all dates should map to August of the ending year.
 */
class BoxscoreDateMappingTest extends TestCase
{
    /**
     * Build a minimal 58-char game info line for testing.
     *
     * Format: 2-char month offset, 2-char day offset, 2-char game#,
     * 2-char visitor, 2-char home, 5-char attendance, 5-char capacity,
     * then W/L and quarter scores to fill 58 chars total.
     */
    private function buildGameInfoLine(int $monthOffset = 0, int $dayOffset = 14): string
    {
        // Month offset (0=Oct), day offset (0=day 1), game#=0, visitor=0, home=1
        $line = sprintf('%02d', $monthOffset)  // month offset from Oct
              . sprintf('%02d', $dayOffset)     // day offset (0-indexed)
              . '00'                            // game of that day
              . '00'                            // visitor team (0-indexed → tid 1)
              . '01'                            // home team (0-indexed → tid 2)
              . '18000'                         // attendance
              . '20000'                         // capacity
              . '1005'                          // visitor wins/losses
              . '0510'                          // home wins/losses
              . '025030028027000'               // visitor quarter scores (5x3 chars)
              . '022031025030000';              // home quarter scores (5x3 chars)

        return $line;
    }

    public function testOlympicsLeagueMapsAllDatesToAugust(): void
    {
        $gameInfoLine = $this->buildGameInfoLine(0, 14); // month offset 0 = October in IBL
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2003, 'Regular Season/Playoffs', 'olympics');

        $this->assertSame('08', $boxscore->gameMonth);
        $this->assertSame(2003, $boxscore->gameYear);
        $this->assertStringStartsWith('2003-08-', $boxscore->gameDate);
    }

    public function testOlympicsLeagueUsesEndingYear(): void
    {
        $gameInfoLine = $this->buildGameInfoLine(2, 5); // month offset 2 = December in IBL
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2005, 'Regular Season/Playoffs', 'olympics');

        $this->assertSame(2005, $boxscore->gameYear);
        $this->assertSame('08', $boxscore->gameMonth);
    }

    public function testIblLeaguePreservesOriginalDateLogic(): void
    {
        // Month offset 1 = November (10+1=11), should be in starting year
        $gameInfoLine = $this->buildGameInfoLine(1, 10);
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2026, 'Regular Season/Playoffs', 'ibl');

        $this->assertSame('11', $boxscore->gameMonth);
        $this->assertSame(2025, $boxscore->gameYear); // Starting year for November
    }

    public function testDefaultLeagueParameterUsesIblLogic(): void
    {
        // Default (no league param) should behave like IBL
        $gameInfoLine = $this->buildGameInfoLine(1, 10);
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2026, 'Regular Season/Playoffs');

        $this->assertSame('11', $boxscore->gameMonth);
        $this->assertSame(2025, $boxscore->gameYear);
    }

    public function testOlympicsLeagueIsCaseInsensitive(): void
    {
        $gameInfoLine = $this->buildGameInfoLine(0, 1);
        $boxscore = \Boxscore::withGameInfoLine($gameInfoLine, 2003, 'Regular Season/Playoffs', 'Olympics');

        $this->assertSame('08', $boxscore->gameMonth);
        $this->assertSame(2003, $boxscore->gameYear);
    }
}
