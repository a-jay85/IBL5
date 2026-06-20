<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\StreakCalculator;

class StreakCalculatorTest extends TestCase
{
    /** @var callable(int): string */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = fn (int $tid): string => 'T' . $tid;
    }

    /**
     * @return array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int}
     */
    private function game(string $date, int $vis, int $home, int $visScore, int $homeScore): array
    {
        return [
            'game_date'      => $date,
            'visitor_teamid' => $vis,
            'home_teamid'    => $home,
            'visitorScore'   => $visScore,
            'homeScore'      => $homeScore,
        ];
    }

    // Test 1: empty input → []
    public function testLongestStreakEmptyInput(): void
    {
        $result = StreakCalculator::longestStreak([], 'winning', $this->resolver);
        $this->assertSame([], $result);
    }

    // Test 2: W W L W W W → streak 3, second run's start/end dates
    public function testLongestStreakSecondRunDates(): void
    {
        $games = [
            $this->game('2024-01-01', 1, 2, 100, 90), // T1 wins
            $this->game('2024-01-02', 1, 2, 100, 90), // T1 wins
            $this->game('2024-01-03', 1, 2, 90, 100),  // T2 wins (T1 loses, streak resets)
            $this->game('2024-01-04', 1, 2, 100, 90), // T1 wins — second run starts
            $this->game('2024-01-05', 1, 2, 100, 90), // T1 wins
            $this->game('2024-01-06', 1, 2, 100, 90), // T1 wins
        ];

        $result = StreakCalculator::longestStreak($games, 'winning', $this->resolver);

        $this->assertCount(1, $result);
        $this->assertSame('T1', $result[0]['team_name']);
        $this->assertSame(3, $result[0]['streak']);
        $this->assertSame('2024-01-04', $result[0]['start_date']);
        $this->assertSame('2024-01-06', $result[0]['end_date']);
    }

    // Test 3: Two teams tied at max N → both returned (tie collection + maxStreak > 0)
    public function testLongestStreakTwoTeamsTied(): void
    {
        $games = [];
        // T3 beats T5 three times
        for ($i = 1; $i <= 3; $i++) {
            $games[] = $this->game('2024-02-0' . $i, 3, 5, 100, 90);
        }
        // T4 beats T6 three times — same max streak
        for ($i = 1; $i <= 3; $i++) {
            $games[] = $this->game('2024-02-0' . ($i + 3), 4, 6, 100, 90);
        }

        $result = StreakCalculator::longestStreak($games, 'winning', $this->resolver);

        $teamNames = array_column($result, 'team_name');
        $this->assertCount(2, $result);
        $this->assertContains('T3', $teamNames);
        $this->assertContains('T4', $teamNames);
        $this->assertSame(3, $result[0]['streak']);
    }

    // Test 4: Streak crossing IBL season boundary → start_year !== end_year
    public function testLongestStreakCrossesSeasonBoundary(): void
    {
        // Jan 2022 → IblSeasonDateHelper returns season year 2022
        // Nov 2022 → IblSeasonDateHelper returns season year 2023
        $games = [
            $this->game('2022-01-15', 10, 11, 100, 90),
            $this->game('2022-11-15', 10, 11, 100, 90),
        ];

        $result = StreakCalculator::longestStreak($games, 'winning', $this->resolver);

        $this->assertCount(1, $result);
        $this->assertSame('T10', $result[0]['team_name']);
        $this->assertSame(2, $result[0]['streak']);
        $this->assertSame(2022, $result[0]['start_year']);
        $this->assertSame(2023, $result[0]['end_year']);
        $this->assertNotSame($result[0]['start_year'], $result[0]['end_year']);
    }

    // Test 5: losing type — T10 loses 3 consecutive games
    public function testLongestStreakLosingType(): void
    {
        $games = [
            $this->game('2024-01-01', 10, 12, 90, 100), // T10 loses
            $this->game('2024-01-02', 10, 12, 90, 100), // T10 loses
            $this->game('2024-01-03', 10, 12, 90, 100), // T10 loses
            $this->game('2024-01-04', 10, 12, 100, 90), // T10 wins (breaks streak)
        ];

        $result = StreakCalculator::longestStreak($games, 'losing', $this->resolver);

        $this->assertCount(1, $result);
        $this->assertSame('T10', $result[0]['team_name']);
        $this->assertSame(3, $result[0]['streak']);
        $this->assertSame('2024-01-01', $result[0]['start_date']);
        $this->assertSame('2024-01-03', $result[0]['end_date']);
    }

    // Test 6: bestWorstSeasonStart empty input → []
    public function testBestWorstSeasonStartEmptyInput(): void
    {
        $result = StreakCalculator::bestWorstSeasonStart([], 'best', $this->resolver);
        $this->assertSame([], $result);
    }

    // Test 7: W W L for best → wins===2 (streakBroken halts count)
    public function testBestSeasonStartWwl(): void
    {
        $games = [
            $this->game('2024-01-01', 1, 2, 100, 90), // T1 wins, T2 loses → T2 streak broken
            $this->game('2024-01-02', 1, 2, 100, 90), // T1 wins (wins=2)
            $this->game('2024-01-03', 1, 2, 90, 100),  // T2 wins, T1 loses → T1 streak broken
        ];

        $result = StreakCalculator::bestWorstSeasonStart($games, 'best', $this->resolver);

        $this->assertCount(1, $result);
        $this->assertSame('T1', $result[0]['team_name']);
        $this->assertSame(2, $result[0]['wins']);
    }

    // Test 8: Team that loses game 1 (best) excluded; tied single-best survivor returned
    public function testBestSeasonStartTeamLosingGame1Excluded(): void
    {
        $games = [
            $this->game('2024-01-01', 1, 2, 90, 100),  // T2 wins, T1 loses → T1 streak broken immediately
            $this->game('2024-01-02', 1, 2, 90, 100),  // T2 wins (wins=2), T1 skipped
            $this->game('2024-01-03', 1, 2, 100, 90),  // T1 wins (skipped), T2 loses → T2 streak broken
        ];

        $result = StreakCalculator::bestWorstSeasonStart($games, 'best', $this->resolver);

        $teamNames = array_column($result, 'team_name');
        $this->assertNotContains('T1', $teamNames);
        $this->assertContains('T2', $teamNames);
        $this->assertSame(2, $result[0]['wins']);
    }

    // Test 9: Same team in two season-years counted independently per teamid-year key
    public function testBestSeasonStartIndependentPerYear(): void
    {
        $games = [
            // Jan 2022 → season year 2022
            $this->game('2022-01-01', 1, 2, 100, 90),
            $this->game('2022-01-02', 1, 2, 100, 90),
            // Jan 2023 → season year 2023
            $this->game('2023-01-01', 1, 2, 100, 90),
            $this->game('2023-01-02', 1, 2, 100, 90),
            $this->game('2023-01-03', 1, 2, 100, 90),
        ];

        $result = StreakCalculator::bestWorstSeasonStart($games, 'best', $this->resolver);

        // T1 in 2022: wins=2; T1 in 2023: wins=3 → maxValue=3 → only 2023 entry returned
        $this->assertCount(1, $result);
        $this->assertSame('T1', $result[0]['team_name']);
        $this->assertSame(2023, $result[0]['year']);
        $this->assertSame(3, $result[0]['wins']);
    }

    // Test 10: worst type returns losses field
    public function testWorstSeasonStartReturnsLosses(): void
    {
        $games = [
            $this->game('2024-01-01', 1, 2, 90, 100),  // T1 loses (losses=1), T2 wins → T2 streak broken
            $this->game('2024-01-02', 1, 2, 90, 100),  // T1 loses (losses=2), T2 skipped
            $this->game('2024-01-03', 1, 2, 100, 90),  // T1 wins → T1 streak broken, T2 skipped
        ];

        $result = StreakCalculator::bestWorstSeasonStart($games, 'worst', $this->resolver);

        $this->assertCount(1, $result);
        $this->assertSame('T1', $result[0]['team_name']);
        $this->assertSame(2, $result[0]['losses']);
        $this->assertSame(0, $result[0]['wins']);
    }
}
