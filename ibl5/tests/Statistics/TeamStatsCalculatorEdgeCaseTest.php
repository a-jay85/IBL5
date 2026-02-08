<?php

declare(strict_types=1);

namespace Tests\Statistics;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Statistics\TeamStatsCalculator;

/**
 * Edge case tests for TeamStatsCalculator
 *
 * Tests boundary conditions, unusual states, and edge cases for team statistics.
 *
 * @covers \Statistics\TeamStatsCalculator
 */
class TeamStatsCalculatorEdgeCaseTest extends TestCase
{
    private TeamStatsCalculator $calculator;
    private object $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->calculator = new TeamStatsCalculator($this->mockDb);
    }

    // ============================================
    // RANKING SCORE EDGE CASES
    // ============================================

    public function testRankingScoreWithAllWins(): void
    {
        // All wins, no losses
        $result = TeamStatsCalculator::calculateRankingScore(82, 0, 3000, 0);

        // Total win points = 3000 + 82 = 3082
        // Total loss points = 0 + 0 = 0
        // Ranking = (3082 / 3082) * 100 = 100.0
        $this->assertEquals(100.0, $result);
    }

    public function testRankingScoreWithAllLosses(): void
    {
        // All losses, no wins
        $result = TeamStatsCalculator::calculateRankingScore(0, 82, 0, 3000);

        // Total win points = 0
        // Total loss points = 3000 + 82 = 3082
        // Ranking = 0 / 3082 * 100 = 0.0
        $this->assertEquals(0.0, $result);
    }

    public function testRankingScoreWithVeryLargeValues(): void
    {
        // Very large values that might cause overflow
        $result = TeamStatsCalculator::calculateRankingScore(1000000, 500000, 50000000, 25000000);

        // Should handle large values without overflow
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
        $this->assertLessThanOrEqual(100, $result);
    }

    public function testRankingScoreRounding(): void
    {
        // Test that rounding works correctly
        $result = TeamStatsCalculator::calculateRankingScore(1, 2, 0, 0);

        // Total win points = 0 + 1 = 1
        // Total loss points = 0 + 2 = 2
        // Ranking = (1 / 3) * 100 = 33.333...
        $this->assertEquals(33.3, $result);
    }

    public function testRankingScoreExact50Percent(): void
    {
        // Exactly 50-50
        $result = TeamStatsCalculator::calculateRankingScore(41, 41, 1000, 1000);

        // Total win = 1041, Total loss = 1041
        // Ranking = 1041 / 2082 * 100 = 50.0
        $this->assertEquals(50.0, $result);
    }

    public function testRankingScoreNear50Percent(): void
    {
        // Test values that show clear difference after rounding
        // Just above 50% - need larger difference to survive rounding
        $result1 = TeamStatsCalculator::calculateRankingScore(50, 41, 1000, 1000);
        // Just below 50%
        $result2 = TeamStatsCalculator::calculateRankingScore(41, 50, 1000, 1000);

        $this->assertGreaterThan(50.0, $result1);
        $this->assertLessThan(50.0, $result2);
    }

    // ============================================
    // GAMES BACK EDGE CASES
    // ============================================

    public function testGamesBackWithNegative(): void
    {
        // More losses than wins - behind first place
        $result = TeamStatsCalculator::calculateGamesBack(5, 10);

        // (5/2) - (10/2) = 2.5 - 5 = -2.5
        $this->assertEquals(-2.5, $result);
    }

    public function testGamesBackWithVeryLargeDifferential(): void
    {
        // Huge differential
        $result = TeamStatsCalculator::calculateGamesBack(82, 0);

        $this->assertEquals(41.0, $result);
    }

    public function testGamesBackWithOddNumbers(): void
    {
        // Odd number of wins/losses
        $result = TeamStatsCalculator::calculateGamesBack(7, 4);

        // (7/2) - (4/2) = 3.5 - 2 = 1.5
        $this->assertEquals(1.5, $result);
    }

    public function testGamesBackWithZeroWins(): void
    {
        $result = TeamStatsCalculator::calculateGamesBack(0, 10);

        // (0/2) - (10/2) = 0 - 5 = -5
        $this->assertEquals(-5.0, $result);
    }

    public function testGamesBackWithZeroLosses(): void
    {
        $result = TeamStatsCalculator::calculateGamesBack(10, 0);

        // (10/2) - (0/2) = 5 - 0 = 5
        $this->assertEquals(5.0, $result);
    }

    public function testGamesBackWithBothZero(): void
    {
        $result = TeamStatsCalculator::calculateGamesBack(0, 0);

        $this->assertEquals(0.0, $result);
    }

    // ============================================
    // STREAK EDGE CASES
    // ============================================

    public function testLongWinStreak(): void
    {
        // 15 consecutive wins
        $games = [];
        for ($i = 0; $i < 15; $i++) {
            $games[] = ['Visitor' => 1, 'VScore' => 100, 'Home' => $i + 2, 'HScore' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(15, $result['streak']);
        $this->assertEquals('W', $result['streakType']);
    }

    public function testLongLossStreak(): void
    {
        // 15 consecutive losses
        $games = [];
        for ($i = 0; $i < 15; $i++) {
            $games[] = ['Visitor' => 1, 'VScore' => 95, 'Home' => $i + 2, 'HScore' => 100];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(15, $result['streak']);
        $this->assertEquals('L', $result['streakType']);
    }

    public function testAlternatingWinsAndLosses(): void
    {
        // Win, loss, win, loss pattern
        $games = [
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 95], // Win
            ['Visitor' => 1, 'VScore' => 95, 'Home' => 3, 'HScore' => 100], // Loss
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 4, 'HScore' => 95], // Win
            ['Visitor' => 1, 'VScore' => 95, 'Home' => 5, 'HScore' => 100], // Loss
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(2, $result['wins']);
        $this->assertEquals(2, $result['losses']);
        $this->assertEquals(1, $result['streak']); // Current streak is 1
        $this->assertEquals('L', $result['streakType']); // Last game was loss
    }

    public function testStreakResetsCorrectlyAfterMultipleConsecutive(): void
    {
        $games = [
            // 3 wins
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 95],
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 3, 'HScore' => 95],
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 4, 'HScore' => 95],
            // 1 loss (resets streak to 1)
            ['Visitor' => 1, 'VScore' => 95, 'Home' => 5, 'HScore' => 100],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(3, $result['wins']);
        $this->assertEquals(1, $result['losses']);
        $this->assertEquals(1, $result['streak']);
        $this->assertEquals('L', $result['streakType']);
    }

    // ============================================
    // MISSING/NULL DATA EDGE CASES
    // ============================================

    public function testHandlesMissingScoreFields(): void
    {
        $games = [
            ['Visitor' => 1, 'Home' => 2], // Missing scores
        ];

        $result = $this->calculator->calculate($games, 1);

        // With missing scores, game should be skipped (0 == 0 is tie)
        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(0, $result['losses']);
    }

    public function testHandlesMissingTeamFields(): void
    {
        $games = [
            ['VScore' => 100, 'HScore' => 95], // Missing team IDs (defaults to 0)
        ];

        $result = $this->calculator->calculate($games, 1);

        // When team IDs default to 0 and tid=1, falls into else branch
        // Treats game as if team 1 is home team
        // homeScore (95) > awayScore (100) is false, so it's a loss
        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(1, $result['losses']);
    }

    public function testHandlesNullValues(): void
    {
        $games = [
            ['Visitor' => null, 'VScore' => null, 'Home' => null, 'HScore' => null],
        ];

        $result = $this->calculator->calculate($games, 1);

        // Should handle gracefully
        $this->assertIsArray($result);
    }

    // ============================================
    // LAST 10 GAMES EDGE CASES
    // ============================================

    public function testLast10GamesWithExactly10Games(): void
    {
        $games = [];
        for ($i = 0; $i < 10; $i++) {
            $games[] = ['Visitor' => 1, 'VScore' => 100, 'Home' => $i + 2, 'HScore' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(10, $result['wins']);
        $this->assertEquals(10, $result['winsInLast10Games']);
    }

    public function testLast10GamesWithLessThan10Games(): void
    {
        $games = [];
        for ($i = 0; $i < 5; $i++) {
            $games[] = ['Visitor' => 1, 'VScore' => 100, 'Home' => $i + 2, 'HScore' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(5, $result['wins']);
        $this->assertEquals(5, $result['winsInLast10Games']);
    }

    public function testLast10GamesWithExactly11Games(): void
    {
        $games = [];
        // First game is a loss (outside last 10)
        $games[] = ['Visitor' => 1, 'VScore' => 95, 'Home' => 99, 'HScore' => 100];
        // Next 10 are wins
        for ($i = 0; $i < 10; $i++) {
            $games[] = ['Visitor' => 1, 'VScore' => 100, 'Home' => $i + 2, 'HScore' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(10, $result['wins']);
        $this->assertEquals(1, $result['losses']);
        $this->assertEquals(10, $result['winsInLast10Games']);
        $this->assertEquals(0, $result['lossesInLast10Games']);
    }

    // ============================================
    // HOME/AWAY SPLIT EDGE CASES
    // ============================================

    public function testAllHomeGames(): void
    {
        $games = [];
        for ($i = 0; $i < 5; $i++) {
            $games[] = ['Visitor' => $i + 2, 'VScore' => 95, 'Home' => 1, 'HScore' => 100];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(5, $result['wins']);
        $this->assertEquals(5, $result['homeWins']);
        $this->assertEquals(0, $result['awayWins']);
    }

    public function testAllAwayGames(): void
    {
        $games = [];
        for ($i = 0; $i < 5; $i++) {
            $games[] = ['Visitor' => 1, 'VScore' => 100, 'Home' => $i + 2, 'HScore' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(5, $result['wins']);
        $this->assertEquals(0, $result['homeWins']);
        $this->assertEquals(5, $result['awayWins']);
    }

    // ============================================
    // WIN/LOSS POINTS EDGE CASES
    // ============================================

    public function testWinPointsDefaultToZeroWithMockDb(): void
    {
        // MockDatabase doesn't have fetchOne, so opponent records default to 0
        $games = [
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 95],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(1, $result['wins']);
        // winPoints defaults to 0 when db doesn't support fetchOne
        $this->assertEquals(0, $result['winPoints']);
    }

    public function testLossPointsDefaultToZeroWithMockDb(): void
    {
        // MockDatabase doesn't have fetchOne, so opponent records default to 0
        $games = [
            ['Visitor' => 1, 'VScore' => 95, 'Home' => 2, 'HScore' => 100],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(1, $result['losses']);
        // lossPoints defaults to 0 when db doesn't support fetchOne
        $this->assertEquals(0, $result['lossPoints']);
    }

    // ============================================
    // DATA PROVIDER TESTS
    // ============================================

    #[DataProvider('rankingScoreBoundaryProvider')]
    public function testRankingScoreBoundaries(int $wins, int $losses, int $winPts, int $lossPts, float $expected): void
    {
        $result = TeamStatsCalculator::calculateRankingScore($wins, $losses, $winPts, $lossPts);

        $this->assertEquals($expected, $result);
    }

    public static function rankingScoreBoundaryProvider(): array
    {
        return [
            'perfect record' => [82, 0, 3000, 0, 100.0],
            'worst record' => [0, 82, 0, 3000, 0.0],
            'even record' => [41, 41, 1000, 1000, 50.0],
            'all zeros' => [0, 0, 0, 0, 0.0],
            'only one win' => [1, 0, 0, 0, 100.0],
            'only one loss' => [0, 1, 0, 0, 0.0],
        ];
    }

    #[DataProvider('gamesBackProvider')]
    public function testGamesBackVariousScenarios(int $wins, int $losses, float $expected): void
    {
        $result = TeamStatsCalculator::calculateGamesBack($wins, $losses);

        $this->assertEquals($expected, $result);
    }

    public static function gamesBackProvider(): array
    {
        return [
            'even record' => [41, 41, 0.0],
            '10 game lead' => [51, 31, 10.0],
            '10 games back' => [31, 51, -10.0],
            'half game lead' => [42, 41, 0.5],
            'half game back' => [41, 42, -0.5],
            'perfect season' => [82, 0, 41.0],
            'winless season' => [0, 82, -41.0],
        ];
    }
}
