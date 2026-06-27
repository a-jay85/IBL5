<?php

declare(strict_types=1);

namespace Tests\LeagueSchedule;

use LeagueSchedule\Game;
use PHPUnit\Framework\TestCase;

class LeagueScheduleGameTest extends TestCase
{
    /** @return array{game_date: string, box_id: int, visitor_teamid: int, home_teamid: int, visitor_score: int, home_score: int} */
    private function makeRow(int $visitorScore, int $homeScore, int $visitorTeamId = 1, int $homeTeamId = 2): array
    {
        return [
            'game_date' => '2026-01-15',
            'box_id' => 101,
            'visitor_teamid' => $visitorTeamId,
            'home_teamid' => $homeTeamId,
            'visitor_score' => $visitorScore,
            'home_score' => $homeScore,
        ];
    }

    public function testConstructorMarksUnplayedWhenScoresEqual(): void
    {
        $game = new Game($this->makeRow(0, 0));

        $this->assertTrue($game->isUnplayed);
        $this->assertSame(2, $game->winningTeamID); // equal → home wins ternary
    }

    public function testConstructorSetsWinningTeamWhenVisitorWins(): void
    {
        $game = new Game($this->makeRow(110, 100));

        $this->assertFalse($game->isUnplayed);
        $this->assertSame(1, $game->winningTeamID);
    }

    public function testConstructorSetsWinningTeamWhenHomeWins(): void
    {
        $game = new Game($this->makeRow(95, 108));

        $this->assertFalse($game->isUnplayed);
        $this->assertSame(2, $game->winningTeamID);
    }

    public function testGetOpposingTeamIDWhenUserIsVisitor(): void
    {
        $game = new Game($this->makeRow(100, 95));

        $this->assertSame(2, $game->getOpposingTeamID(1));
    }

    public function testGetOpposingTeamIDWhenUserIsHome(): void
    {
        $game = new Game($this->makeRow(100, 95));

        $this->assertSame(1, $game->getOpposingTeamID(2));
    }

    public function testGetOpposingTeamIDWhenUserIsNeitherReturnsVisitor(): void
    {
        $game = new Game($this->makeRow(100, 95));

        $this->assertSame(1, $game->getOpposingTeamID(9999));
    }

    public function testGetUserTeamLocationPrefixIsAtSignWhenVisitor(): void
    {
        $game = new Game($this->makeRow(100, 95));

        $this->assertSame('@', $game->getUserTeamLocationPrefix(1));
    }

    public function testGetUserTeamLocationPrefixIsVsWhenHome(): void
    {
        $game = new Game($this->makeRow(100, 95));

        $this->assertSame('vs', $game->getUserTeamLocationPrefix(2));
    }

    public function testGetUserTeamLocationPrefixIsVsWhenNeither(): void
    {
        $game = new Game($this->makeRow(100, 95));

        $this->assertSame('vs', $game->getUserTeamLocationPrefix(9999));
    }
}
