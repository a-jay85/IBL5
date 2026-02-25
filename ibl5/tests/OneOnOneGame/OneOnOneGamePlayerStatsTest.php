<?php

declare(strict_types=1);


namespace Tests\OneOnOneGame;
use PHPUnit\Framework\TestCase;
use OneOnOneGame\OneOnOneGamePlayerStats;

/**
 * Tests for OneOnOneGamePlayerStats DTO
 */
final class OneOnOneGamePlayerStatsTest extends TestCase
{
    public function testInitializesWithZeroValues(): void
    {
        $stats = new OneOnOneGamePlayerStats();

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
}
