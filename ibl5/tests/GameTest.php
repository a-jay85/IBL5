<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use LeagueSchedule\Game;

/**
 * GameTest - Tests for Game entity class
 */
class GameTest extends TestCase
{
    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testGameCanBeInstantiated(): void
    {
        $row = $this->createValidScheduleRow();
        $game = new Game($row);
        
        $this->assertInstanceOf(Game::class, $game);
    }

    public function testGamePropertiesAreSetCorrectly(): void
    {
        $row = $this->createValidScheduleRow();
        $game = new Game($row);
        
        $this->assertEquals('2025-01-15', $game->date);
        $this->assertEquals(1001, $game->boxScoreID);
        $this->assertEquals(1, $game->visitor_teamid);
        $this->assertEquals(2, $game->home_teamid);
        $this->assertEquals(98, $game->visitorScore);
        $this->assertEquals(105, $game->homeScore);
    }

    // ============================================
    // WINNING TEAM TESTS
    // ============================================

    public function testHomeTeamWinsWhenHigherScore(): void
    {
        $row = $this->createValidScheduleRow([
            'visitor_score' => 95,
            'home_score' => 100
        ]);
        $game = new Game($row);
        
        $this->assertEquals(2, $game->winningTeamID); // Home team
        $this->assertFalse($game->isUnplayed);
    }

    public function testVisitorTeamWinsWhenHigherScore(): void
    {
        $row = $this->createValidScheduleRow([
            'visitor_score' => 110,
            'home_score' => 105
        ]);
        $game = new Game($row);
        
        $this->assertEquals(1, $game->winningTeamID); // Visitor team
        $this->assertFalse($game->isUnplayed);
    }

    public function testGameIsUnplayedWhenScoresTied(): void
    {
        $row = $this->createValidScheduleRow([
            'visitor_score' => 0,
            'home_score' => 0
        ]);
        $game = new Game($row);
        
        $this->assertTrue($game->isUnplayed);
    }

    // ============================================
    // GET OPPOSING TEAM TESTS
    // ============================================

    public function testGetOpposingTeamIdReturnsHomeTeamForVisitor(): void
    {
        $row = $this->createValidScheduleRow();
        $game = new Game($row);
        
        $opposingTeamId = $game->getOpposingTeamID(1); // User is visitor (team 1)
        
        $this->assertEquals(2, $opposingTeamId); // Opposing is home (team 2)
    }

    public function testGetOpposingTeamIdReturnsVisitorTeamForHome(): void
    {
        $row = $this->createValidScheduleRow();
        $game = new Game($row);
        
        $opposingTeamId = $game->getOpposingTeamID(2); // User is home (team 2)
        
        $this->assertEquals(1, $opposingTeamId); // Opposing is visitor (team 1)
    }

    // ============================================
    // GET USER TEAM LOCATION PREFIX TESTS
    // ============================================

    public function testGetUserTeamLocationPrefixReturnsAtForVisitor(): void
    {
        $row = $this->createValidScheduleRow();
        $game = new Game($row);
        
        $prefix = $game->getUserTeamLocationPrefix(1); // User is visitor
        
        $this->assertEquals('@', $prefix);
    }

    public function testGetUserTeamLocationPrefixReturnsVsForHome(): void
    {
        $row = $this->createValidScheduleRow();
        $game = new Game($row);
        
        $prefix = $game->getUserTeamLocationPrefix(2); // User is home
        
        $this->assertEquals('vs', $prefix);
    }

    // ============================================
    // DATE OBJECT TESTS
    // ============================================

    public function testDateObjectIsCreated(): void
    {
        $row = $this->createValidScheduleRow();
        $game = new Game($row);
        
        $this->assertInstanceOf(\DateTime::class, $game->dateObject);
    }

    public function testDateObjectMatchesDateString(): void
    {
        $row = $this->createValidScheduleRow(['game_date' => '2025-06-15']);
        $game = new Game($row);
        
        $this->assertEquals('2025-06-15', $game->dateObject->format('Y-m-d'));
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function createValidScheduleRow(array $overrides = []): array
    {
        return array_merge([
            'game_date' => '2025-01-15',
            'box_id' => 1001,
            'visitor_teamid' => 1,
            'home_teamid' => 2,
            'visitor_score' => 98,
            'home_score' => 105,
        ], $overrides);
    }
}
