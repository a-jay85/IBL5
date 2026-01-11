<?php

declare(strict_types=1);

namespace Tests\Statistics;

use PHPUnit\Framework\TestCase;
use Statistics\TeamStatsCalculator;

/**
 * TeamStatsCalculatorTest - Tests for team statistics calculation
 */
class TeamStatsCalculatorTest extends TestCase
{
    private TeamStatsCalculator $calculator;
    private object $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->calculator = new TeamStatsCalculator($this->mockDb);
    }

    public function testCalculateInitializesCorrectlyWithEmptyGames(): void
    {
        $games = [];
        $result = $this->calculator->calculate($games, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('wins', $result);
        $this->assertArrayHasKey('losses', $result);
        $this->assertArrayHasKey('homeWins', $result);
        $this->assertArrayHasKey('homeLosses', $result);
        $this->assertArrayHasKey('awayWins', $result);
        $this->assertArrayHasKey('awayLosses', $result);
        $this->assertArrayHasKey('streak', $result);
        $this->assertArrayHasKey('streakType', $result);

        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(0, $result['losses']);
    }

    public function testCalculateWithAwayWin(): void
    {
        $games = [
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 95]
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(1, $result['wins']);
        $this->assertEquals(0, $result['losses']);
        $this->assertEquals(1, $result['awayWins']);
        $this->assertEquals(0, $result['homeWins']);
        $this->assertEquals('W', $result['streakType']);
        $this->assertEquals(1, $result['streak']);
    }

    public function testCalculateWithHomeLoss(): void
    {
        $games = [
            ['Visitor' => 2, 'VScore' => 100, 'Home' => 1, 'HScore' => 95]
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(1, $result['losses']);
        $this->assertEquals(0, $result['awayLosses']);
        $this->assertEquals(1, $result['homeLosses']);
        $this->assertEquals('L', $result['streakType']);
        $this->assertEquals(1, $result['streak']);
    }

    public function testCalculateWithHomeWin(): void
    {
        $games = [
            ['Visitor' => 2, 'VScore' => 95, 'Home' => 1, 'HScore' => 100]
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(1, $result['wins']);
        $this->assertEquals(0, $result['losses']);
        $this->assertEquals(1, $result['homeWins']);
        $this->assertEquals('W', $result['streakType']);
    }

    public function testCalculateWithAwayLoss(): void
    {
        $games = [
            ['Visitor' => 1, 'VScore' => 95, 'Home' => 2, 'HScore' => 100]
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(1, $result['losses']);
        $this->assertEquals(1, $result['awayLosses']);
        $this->assertEquals('L', $result['streakType']);
    }

    public function testCalculateStreakIncrementsForConsecutiveWins(): void
    {
        $games = [
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 95],
            ['Visitor' => 1, 'VScore' => 105, 'Home' => 3, 'HScore' => 100],
            ['Visitor' => 1, 'VScore' => 110, 'Home' => 4, 'HScore' => 105],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(3, $result['wins']);
        $this->assertEquals(3, $result['streak']);
        $this->assertEquals('W', $result['streakType']);
    }

    public function testCalculateStreakResetsOnLoss(): void
    {
        $games = [
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 95],
            ['Visitor' => 1, 'VScore' => 95, 'Home' => 3, 'HScore' => 100],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(1, $result['wins']);
        $this->assertEquals(1, $result['losses']);
        $this->assertEquals(1, $result['streak']);
        $this->assertEquals('L', $result['streakType']);
    }

    public function testCalculateLast10GamesTracking(): void
    {
        // Create 15 games, team wins all
        $games = [];
        for ($i = 0; $i < 15; $i++) {
            $games[] = ['Visitor' => 1, 'VScore' => 100, 'Home' => $i + 2, 'HScore' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(15, $result['wins']);
        $this->assertEquals(10, $result['winsInLast10Games']);
        $this->assertEquals(0, $result['lossesInLast10Games']);
    }

    public function testCalculateSkipsTiedGames(): void
    {
        $games = [
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 100], // Tied - should skip
            ['Visitor' => 1, 'VScore' => 105, 'Home' => 3, 'HScore' => 100], // Win
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertEquals(1, $result['wins']);
        $this->assertEquals(0, $result['losses']);
    }

    public function testCalculateRankingScore(): void
    {
        $result = TeamStatsCalculator::calculateRankingScore(10, 5, 50, 25);
        
        // Total win points = 50 + 10 = 60
        // Total loss points = 25 + 5 = 30
        // Ranking = (60 / 90) * 100 = 66.7
        $this->assertEquals(66.7, $result);
    }

    public function testCalculateRankingScoreWithZeroValues(): void
    {
        $result = TeamStatsCalculator::calculateRankingScore(0, 0, 0, 0);
        $this->assertEquals(0.0, $result);
    }

    public function testCalculateGamesBack(): void
    {
        // 10 wins, 5 losses: (10/2) - (5/2) = 5 - 2.5 = 2.5
        $result = TeamStatsCalculator::calculateGamesBack(10, 5);
        $this->assertEquals(2.5, $result);
    }

    public function testCalculateGamesBackWithEvenRecord(): void
    {
        $result = TeamStatsCalculator::calculateGamesBack(10, 10);
        $this->assertEquals(0.0, $result);
    }
}
