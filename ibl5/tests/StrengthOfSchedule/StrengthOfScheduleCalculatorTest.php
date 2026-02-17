<?php

declare(strict_types=1);

namespace Tests\StrengthOfSchedule;

use PHPUnit\Framework\TestCase;
use StrengthOfSchedule\StrengthOfScheduleCalculator;

class StrengthOfScheduleCalculatorTest extends TestCase
{
    public function testCalculateAverageOpponentWinPctWithKnownOpponents(): void
    {
        $games = [
            ['Visitor' => 1, 'Home' => 2],
            ['Visitor' => 3, 'Home' => 1],
            ['Visitor' => 1, 'Home' => 4],
        ];

        $teamWinPcts = [
            1 => 0.600,
            2 => 0.700,
            3 => 0.400,
            4 => 0.500,
        ];

        // Team 1 opponents: 2 (0.700), 3 (0.400), 4 (0.500)
        // Average: (0.700 + 0.400 + 0.500) / 3 = 0.533
        $result = StrengthOfScheduleCalculator::calculateAverageOpponentWinPct($games, 1, $teamWinPcts);
        $this->assertSame(0.533, $result);
    }

    public function testCalculateAverageOpponentWinPctReturnsZeroForNoGames(): void
    {
        $result = StrengthOfScheduleCalculator::calculateAverageOpponentWinPct([], 1, [1 => 0.500]);
        $this->assertSame(0.0, $result);
    }

    public function testCalculateAverageOpponentWinPctHandlesMissingTeam(): void
    {
        $games = [
            ['Visitor' => 1, 'Home' => 99],
        ];

        $teamWinPcts = [
            1 => 0.600,
            // Team 99 not in lookup — defaults to 0.0
        ];

        $result = StrengthOfScheduleCalculator::calculateAverageOpponentWinPct($games, 1, $teamWinPcts);
        $this->assertSame(0.0, $result);
    }

    public function testAssignTierElite(): void
    {
        $this->assertSame('elite', StrengthOfScheduleCalculator::assignTier(70.0));
        $this->assertSame('elite', StrengthOfScheduleCalculator::assignTier(85.5));
        $this->assertSame('elite', StrengthOfScheduleCalculator::assignTier(100.0));
    }

    public function testAssignTierStrong(): void
    {
        $this->assertSame('strong', StrengthOfScheduleCalculator::assignTier(55.0));
        $this->assertSame('strong', StrengthOfScheduleCalculator::assignTier(69.9));
    }

    public function testAssignTierAverage(): void
    {
        $this->assertSame('average', StrengthOfScheduleCalculator::assignTier(45.0));
        $this->assertSame('average', StrengthOfScheduleCalculator::assignTier(54.9));
    }

    public function testAssignTierWeak(): void
    {
        $this->assertSame('weak', StrengthOfScheduleCalculator::assignTier(30.0));
        $this->assertSame('weak', StrengthOfScheduleCalculator::assignTier(44.9));
    }

    public function testAssignTierBottom(): void
    {
        $this->assertSame('bottom', StrengthOfScheduleCalculator::assignTier(29.9));
        $this->assertSame('bottom', StrengthOfScheduleCalculator::assignTier(0.0));
    }

    public function testRankTeamsHighestSosGetsRankOne(): void
    {
        $sosValues = [
            1 => 0.600,
            2 => 0.450,
            3 => 0.700,
            4 => 0.500,
        ];

        $ranks = StrengthOfScheduleCalculator::rankTeams($sosValues);

        // Team 3 (0.700) = rank 1, Team 1 (0.600) = rank 2, Team 4 (0.500) = rank 3, Team 2 (0.450) = rank 4
        $this->assertSame(1, $ranks[3]);
        $this->assertSame(2, $ranks[1]);
        $this->assertSame(3, $ranks[4]);
        $this->assertSame(4, $ranks[2]);
    }

    public function testRankTeamsEmptyInput(): void
    {
        $ranks = StrengthOfScheduleCalculator::rankTeams([]);
        $this->assertSame([], $ranks);
    }

    public function testCalculateAverageOpponentWinPctSingleGame(): void
    {
        $games = [
            ['Visitor' => 1, 'Home' => 2],
        ];

        $teamWinPcts = [
            1 => 0.500,
            2 => 0.750,
        ];

        $result = StrengthOfScheduleCalculator::calculateAverageOpponentWinPct($games, 1, $teamWinPcts);
        $this->assertSame(0.75, $result);
    }
}
