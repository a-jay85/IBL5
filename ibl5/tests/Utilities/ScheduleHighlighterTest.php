<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\ScheduleHighlighter;

/**
 * ScheduleHighlighterTest - Tests for schedule highlighting utilities
 */
class ScheduleHighlighterTest extends TestCase
{
    public function testIsNextSimGameReturnsTrueWhenGameDateBeforeProjectedEnd(): void
    {
        $gameDate = date_create('2024-01-10');
        $projectedEnd = date_create('2024-01-15');

        $this->assertTrue(ScheduleHighlighter::isNextSimGame($gameDate, $projectedEnd));
    }

    public function testIsNextSimGameReturnsTrueWhenGameDateEqualsProjectedEnd(): void
    {
        $gameDate = date_create('2024-01-15');
        $projectedEnd = date_create('2024-01-15');

        $this->assertTrue(ScheduleHighlighter::isNextSimGame($gameDate, $projectedEnd));
    }

    public function testIsNextSimGameReturnsFalseWhenGameDateAfterProjectedEnd(): void
    {
        $gameDate = date_create('2024-01-20');
        $projectedEnd = date_create('2024-01-15');

        $this->assertFalse(ScheduleHighlighter::isNextSimGame($gameDate, $projectedEnd));
    }

    public function testIsGameUnplayedReturnsTrueWhenScoresEqual(): void
    {
        $this->assertTrue(ScheduleHighlighter::isGameUnplayed(0, 0));
    }

    public function testIsGameUnplayedReturnsTrueWhenScoresEqualNonZero(): void
    {
        // Edge case: tied game (rare in basketball but possible)
        $this->assertTrue(ScheduleHighlighter::isGameUnplayed(100, 100));
    }

    public function testIsGameUnplayedReturnsFalseWhenScoresDifferent(): void
    {
        $this->assertFalse(ScheduleHighlighter::isGameUnplayed(105, 98));
    }

    public function testIsGameUnplayedHandlesStringScores(): void
    {
        $this->assertTrue(ScheduleHighlighter::isGameUnplayed('0', '0'));
        $this->assertFalse(ScheduleHighlighter::isGameUnplayed('105', '98'));
    }

    public function testShouldHighlightReturnsTrueForUnplayedGameInNextSimRange(): void
    {
        $gameDate = date_create('2024-01-10');
        $projectedEnd = date_create('2024-01-15');

        $this->assertTrue(ScheduleHighlighter::shouldHighlight(0, 0, $gameDate, $projectedEnd));
    }

    public function testShouldHighlightReturnsFalseForPlayedGameInNextSimRange(): void
    {
        $gameDate = date_create('2024-01-10');
        $projectedEnd = date_create('2024-01-15');

        $this->assertFalse(ScheduleHighlighter::shouldHighlight(105, 98, $gameDate, $projectedEnd));
    }

    public function testShouldHighlightReturnsFalseForUnplayedGameOutsideNextSimRange(): void
    {
        $gameDate = date_create('2024-01-20');
        $projectedEnd = date_create('2024-01-15');

        $this->assertFalse(ScheduleHighlighter::shouldHighlight(0, 0, $gameDate, $projectedEnd));
    }

    public function testShouldHighlightReturnsFalseForPlayedGameOutsideNextSimRange(): void
    {
        $gameDate = date_create('2024-01-20');
        $projectedEnd = date_create('2024-01-15');

        $this->assertFalse(ScheduleHighlighter::shouldHighlight(105, 98, $gameDate, $projectedEnd));
    }
}
