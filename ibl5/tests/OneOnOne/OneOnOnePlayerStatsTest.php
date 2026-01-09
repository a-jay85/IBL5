<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use OneOnOne\OneOnOnePlayerStats;

/**
 * Tests for OneOnOnePlayerStats DTO
 */
final class OneOnOnePlayerStatsTest extends TestCase
{
    public function testInitializesWithZeroValues(): void
    {
        $stats = new OneOnOnePlayerStats();

        $this->assertEquals(0, $stats->fieldGoalsMade);
        $this->assertEquals(0, $stats->fieldGoalsAttempted);
        $this->assertEquals(0, $stats->threePointersMade);
        $this->assertEquals(0, $stats->threePointersAttempted);
        $this->assertEquals(0, $stats->offensiveRebounds);
        $this->assertEquals(0, $stats->totalRebounds);
        $this->assertEquals(0, $stats->steals);
        $this->assertEquals(0, $stats->blocks);
        $this->assertEquals(0, $stats->turnovers);
        $this->assertEquals(0, $stats->fouls);
    }

    public function testResetClearsAllValues(): void
    {
        $stats = new OneOnOnePlayerStats();
        $stats->fieldGoalsMade = 5;
        $stats->fieldGoalsAttempted = 10;
        $stats->threePointersMade = 2;
        $stats->steals = 3;

        $stats->reset();

        $this->assertEquals(0, $stats->fieldGoalsMade);
        $this->assertEquals(0, $stats->fieldGoalsAttempted);
        $this->assertEquals(0, $stats->threePointersMade);
        $this->assertEquals(0, $stats->steals);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $stats = new OneOnOnePlayerStats();
        $stats->fieldGoalsMade = 8;
        $stats->fieldGoalsAttempted = 15;
        $stats->threePointersMade = 3;
        $stats->threePointersAttempted = 6;
        $stats->offensiveRebounds = 2;
        $stats->totalRebounds = 5;
        $stats->steals = 1;
        $stats->blocks = 2;
        $stats->turnovers = 3;
        $stats->fouls = 1;

        $array = $stats->toArray();

        $this->assertEquals([
            'fgm' => 8,
            'fga' => 15,
            '3gm' => 3,
            '3ga' => 6,
            'orb' => 2,
            'reb' => 5,
            'stl' => 1,
            'blk' => 2,
            'to' => 3,
            'foul' => 1,
        ], $array);
    }
}
