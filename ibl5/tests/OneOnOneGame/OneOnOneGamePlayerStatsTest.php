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

        $this->assertSame(0, $stats->fieldGoalsMade);
        $this->assertSame(0, $stats->fieldGoalsAttempted);
        $this->assertSame(0, $stats->threePointersMade);
        $this->assertSame(0, $stats->threePointersAttempted);
        $this->assertSame(0, $stats->offensiveRebounds);
        $this->assertSame(0, $stats->totalRebounds);
        $this->assertSame(0, $stats->steals);
        $this->assertSame(0, $stats->blocks);
        $this->assertSame(0, $stats->turnovers);
        $this->assertSame(0, $stats->fouls);
    }
}
