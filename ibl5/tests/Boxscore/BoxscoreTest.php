<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Boxscore\Boxscore
 */
class BoxscoreTest extends TestCase
{
    // --- fillGameInfo() date logic via withGameInfoLine() ---

    public function testNovemberGameUsesStartingYear(): void
    {
        // Month code "01" → +10 = 11 (November), hits elseif month > 10 → year = startingYear
        $line = $this->makeGameInfoLine(monthCode: '01', dayCode: '14');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame('11', $box->gameMonth);
        $this->assertSame(2025, $box->gameYear);
    }

    public function testDecemberGameUsesStartingYear(): void
    {
        // Month code "02" → +10 = 12 (December), hits elseif month > 10
        $line = $this->makeGameInfoLine(monthCode: '02', dayCode: '09');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame('12', $box->gameMonth);
        $this->assertSame(2025, $box->gameYear);
    }

    public function testJanuaryGameWrapsMonthAndUsesEndingYear(): void
    {
        // Month code "03" → +10 = 13 (> 12 and != 22) → 13 - 12 = 01
        $line = $this->makeGameInfoLine(monthCode: '03', dayCode: '04');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame('01', $box->gameMonth);
        $this->assertSame(2026, $box->gameYear);
    }

    public function testFebruaryGameWrapsMonth(): void
    {
        // Month code "04" → +10 = 14 → 14 - 12 = 02
        $line = $this->makeGameInfoLine(monthCode: '04', dayCode: '00');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame('02', $box->gameMonth);
    }

    public function testPlayoffGameHackedToJune(): void
    {
        // Month code "12" → +10 = 22 = JSB::PLAYOFF_MONTH → 22 - 16 = 06
        $line = $this->makeGameInfoLine(monthCode: '12', dayCode: '00');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame('06', $box->gameMonth);
        $this->assertSame(2026, $box->gameYear);
    }

    public function testOlympicsGameUsesAugustAndEndingYear(): void
    {
        $line = $this->makeGameInfoLine(monthCode: '01', dayCode: '05');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs', 'olympics');

        $this->assertSame('08', $box->gameMonth);
        $this->assertSame(2026, $box->gameYear);
    }

    public function testOlympicsCaseInsensitive(): void
    {
        $line = $this->makeGameInfoLine(monthCode: '01', dayCode: '05');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs', 'Olympics');

        $this->assertSame('08', $box->gameMonth);
    }

    public function testHeatPhaseOverridesMonthToHeatMonth(): void
    {
        // Month code "01" → +10 = 11, hits elseif month > 10, phase = "HEAT" → month = 10
        $line = $this->makeGameInfoLine(monthCode: '01', dayCode: '00');
        $box = Boxscore::withGameInfoLine($line, 2026, 'HEAT');

        $this->assertSame('10', $box->gameMonth);
        $this->assertSame(2025, $box->gameYear);
    }

    public function testTeamIdsParsedWithPlusOneOffset(): void
    {
        // Visitor team code "04" → 4+1 = 5, Home team code "09" → 9+1 = 10
        $line = $this->makeGameInfoLine(visitorTeamCode: '04', homeTeamCode: '09');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame(5, $box->visitor_teamid);
        $this->assertSame(10, $box->home_teamid);
    }

    public function testDayParsedWithPlusOneOffset(): void
    {
        // Day code "14" → 14+1 = 15
        $line = $this->makeGameInfoLine(dayCode: '14');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame('15', $box->gameDay);
    }

    public function testGameOfThatDayParsedWithPlusOneOffset(): void
    {
        // game_of_that_day code "02" → 2+1 = 3
        $line = $this->makeGameInfoLine(gameOfDayCode: '02');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame(3, $box->game_of_that_day);
    }

    public function testAttendanceAndCapacityExtracted(): void
    {
        $line = $this->makeGameInfoLine(attendance: '18500', capacity: '20000');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame('18500', $box->attendance);
        $this->assertSame('20000', $box->capacity);
    }

    public function testRecordExtracted(): void
    {
        $line = $this->makeGameInfoLine(vWins: '30', vLosses: '10', hWins: '25', hLosses: '15');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame('30', $box->visitor_wins);
        $this->assertSame('10', $box->visitor_losses);
        $this->assertSame('25', $box->home_wins);
        $this->assertSame('15', $box->home_losses);
    }

    public function testQuarterScoresExtracted(): void
    {
        $line = $this->makeGameInfoLine(
            vQ1: ' 25', vQ2: ' 30', vQ3: ' 22', vQ4: ' 28', vOT: '  0',
            hQ1: ' 27', hQ2: ' 24', hQ3: ' 31', hQ4: ' 26', hOT: '  0',
        );
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame(' 25', $box->visitor_q1_points);
        $this->assertSame(' 30', $box->visitor_q2_points);
        $this->assertSame(' 27', $box->home_q1_points);
        $this->assertSame('  0', $box->visitor_ot_points);
    }

    public function testGameDateAssembledCorrectly(): void
    {
        // January 15th game in 2026 season
        $line = $this->makeGameInfoLine(monthCode: '03', dayCode: '14');
        $box = Boxscore::withGameInfoLine($line, 2026, 'Regular Season/Playoffs');

        $this->assertSame('2026-01-15', $box->gameDate);
    }

    // --- scoresMatchDatabase() ---

    public function testScoresMatchDatabaseReturnsTrueForMatchingScores(): void
    {
        $box = Boxscore::withGameInfoLine(
            $this->makeGameInfoLine(
                vQ1: ' 25', vQ2: ' 30', vQ3: ' 22', vQ4: ' 28', vOT: '  0',
                hQ1: ' 27', hQ2: ' 24', hQ3: ' 31', hQ4: ' 26', hOT: '  0',
            ),
            2026,
            'Regular Season/Playoffs',
        );

        $dbRow = [
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30,
            'visitor_q3_points' => 22, 'visitor_q4_points' => 28, 'visitor_ot_points' => 0,
            'home_q1_points' => 27, 'home_q2_points' => 24,
            'home_q3_points' => 31, 'home_q4_points' => 26, 'home_ot_points' => 0,
        ];

        $this->assertTrue($box->scoresMatchDatabase($dbRow));
    }

    public function testScoresMatchDatabaseReturnsFalseForMismatchedVisitorScores(): void
    {
        $box = Boxscore::withGameInfoLine(
            $this->makeGameInfoLine(
                vQ1: ' 25', vQ2: ' 30', vQ3: ' 22', vQ4: ' 28', vOT: '  0',
                hQ1: ' 27', hQ2: ' 24', hQ3: ' 31', hQ4: ' 26', hOT: '  0',
            ),
            2026,
            'Regular Season/Playoffs',
        );

        $dbRow = [
            'visitor_q1_points' => 99, 'visitor_q2_points' => 30,
            'visitor_q3_points' => 22, 'visitor_q4_points' => 28, 'visitor_ot_points' => 0,
            'home_q1_points' => 27, 'home_q2_points' => 24,
            'home_q3_points' => 31, 'home_q4_points' => 26, 'home_ot_points' => 0,
        ];

        $this->assertFalse($box->scoresMatchDatabase($dbRow));
    }

    public function testScoresMatchDatabaseReturnsFalseForMismatchedHomeScores(): void
    {
        $box = Boxscore::withGameInfoLine(
            $this->makeGameInfoLine(
                vQ1: ' 25', vQ2: ' 30', vQ3: ' 22', vQ4: ' 28', vOT: '  0',
                hQ1: ' 27', hQ2: ' 24', hQ3: ' 31', hQ4: ' 26', hOT: '  0',
            ),
            2026,
            'Regular Season/Playoffs',
        );

        $dbRow = [
            'visitor_q1_points' => 25, 'visitor_q2_points' => 30,
            'visitor_q3_points' => 22, 'visitor_q4_points' => 28, 'visitor_ot_points' => 0,
            'home_q1_points' => 27, 'home_q2_points' => 24,
            'home_q3_points' => 31, 'home_q4_points' => 99, 'home_ot_points' => 0,
        ];

        $this->assertFalse($box->scoresMatchDatabase($dbRow));
    }

    // --- overrideGameContext() ---

    public function testOverrideGameContextSetsFields(): void
    {
        $box = Boxscore::withGameInfoLine(
            $this->makeGameInfoLine(),
            2026,
            'Regular Season/Playoffs',
        );

        $box->overrideGameContext('2026-02-15', 50, 51, 1);

        $this->assertSame('2026-02-15', $box->gameDate);
        $this->assertSame(50, $box->visitor_teamid);
        $this->assertSame(51, $box->home_teamid);
        $this->assertSame(1, $box->game_of_that_day);
    }

    // --- SQL builders ---

    public function testPlayerInsertSqlContainsTableName(): void
    {
        $sql = Boxscore::playerInsertSql('ibl_box_scores');

        $this->assertStringContainsString('ibl_box_scores', $sql);
        $this->assertStringContainsString('INSERT INTO', $sql);
    }

    public function testPlayerInsertSqlContainsExpectedColumns(): void
    {
        $sql = Boxscore::playerInsertSql('ibl_box_scores');

        $this->assertStringContainsString('game_date', $sql);
        $this->assertStringContainsString('pid', $sql);
        $this->assertStringContainsString('game_min', $sql);
        $this->assertStringContainsString('game_pf', $sql);
    }

    public function testTeamInsertSqlContainsTableName(): void
    {
        $sql = Boxscore::teamInsertSql('ibl_box_scores_teams');

        $this->assertStringContainsString('ibl_box_scores_teams', $sql);
        $this->assertStringContainsString('INSERT INTO', $sql);
    }

    public function testTeamInsertSqlContainsExpectedColumns(): void
    {
        $sql = Boxscore::teamInsertSql('ibl_box_scores_teams');

        $this->assertStringContainsString('visitor_teamid', $sql);
        $this->assertStringContainsString('home_teamid', $sql);
        $this->assertStringContainsString('visitor_q1_points', $sql);
        $this->assertStringContainsString('home_ot_points', $sql);
    }

    // --- Helper to build fixed-width game info lines ---

    /**
     * Build a fixed-width game info line for Boxscore::fillGameInfo().
     *
     * Format: month(2) day(2) gameOfDay(2) visitor(2) home(2) attendance(5) capacity(5)
     *         vWins(2) vLosses(2) hWins(2) hLosses(2) vQ1(3) vQ2(3) vQ3(3) vQ4(3) vOT(3)
     *         hQ1(3) hQ2(3) hQ3(3) hQ4(3) hOT(3) = 58 chars total
     */
    private function makeGameInfoLine(
        string $monthCode = '03',
        string $dayCode = '04',
        string $gameOfDayCode = '00',
        string $visitorTeamCode = '00',
        string $homeTeamCode = '01',
        string $attendance = '18000',
        string $capacity = '20000',
        string $vWins = '30',
        string $vLosses = '10',
        string $hWins = '25',
        string $hLosses = '15',
        string $vQ1 = ' 25',
        string $vQ2 = ' 30',
        string $vQ3 = ' 22',
        string $vQ4 = ' 28',
        string $vOT = '  0',
        string $hQ1 = ' 27',
        string $hQ2 = ' 24',
        string $hQ3 = ' 31',
        string $hQ4 = ' 26',
        string $hOT = '  0',
    ): string {
        return $monthCode . $dayCode . $gameOfDayCode
            . $visitorTeamCode . $homeTeamCode
            . $attendance . $capacity
            . $vWins . $vLosses . $hWins . $hLosses
            . $vQ1 . $vQ2 . $vQ3 . $vQ4 . $vOT
            . $hQ1 . $hQ2 . $hQ3 . $hQ4 . $hOT;
    }
}
