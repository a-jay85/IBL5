<?php

declare(strict_types=1);

namespace Tests\UI\Tables;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use UI\Tables\Ratings;
use Player\Player;
use Season\Season;

/**
 * RatingsTest - Tests for Ratings table rendering
 *
 * Verifies that player matchup tables render correctly without
 * blank separator rows at the beginning.
 *
 * @covers \UI\Tables\Ratings
 */
#[AllowMockObjectsWithoutExpectations]
class RatingsTest extends TestCase
{
    /**
     * Create a mock Player object for testing
     *
     * @param string $name Player name
     * @param int $playerId Player ID
     * @return Player&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockPlayer(string $name = 'Test Player', int $playerId = 1): Player
    {
        $player = $this->createMock(Player::class);
        $player->method('getPlayerID')->willReturn($playerId);
        $player->method('getName')->willReturn($name);
        $player->method('getPosition')->willReturn('PG');
        $player->method('getAge')->willReturn(25);
        $player->method('getRatingFieldGoalAttempts')->willReturn(15);
        $player->method('getRatingFieldGoalPercentage')->willReturn(45);
        $player->method('getRatingFreeThrowAttempts')->willReturn(5);
        $player->method('getRatingFreeThrowPercentage')->willReturn(80);
        $player->method('getRatingThreePointAttempts')->willReturn(5);
        $player->method('getRatingThreePointPercentage')->willReturn(35);
        $player->method('getRatingOffensiveRebounds')->willReturn(2);
        $player->method('getRatingDefensiveRebounds')->willReturn(4);
        $player->method('getRatingAssists')->willReturn(8);
        $player->method('getRatingSteals')->willReturn(1);
        $player->method('getRatingTurnovers')->willReturn(2);
        $player->method('getRatingBlocks')->willReturn(0);
        $player->method('getRatingFouls')->willReturn(2);
        $player->method('getRatingOutsideOffense')->willReturn(75);
        $player->method('getRatingDriveOffense')->willReturn(70);
        $player->method('getRatingPostOffense')->willReturn(50);
        $player->method('getRatingTransitionOffense')->willReturn(80);
        $player->method('getRatingOutsideDefense')->willReturn(70);
        $player->method('getRatingDriveDefense')->willReturn(65);
        $player->method('getRatingPostDefense')->willReturn(55);
        $player->method('getRatingTransitionDefense')->willReturn(75);
        $player->method('getRatingClutch')->willReturn(70);
        $player->method('getRatingConsistency')->willReturn(75);
        $player->method('getDecoratedName')->willReturn($name);
        $player->method('getNameStatusClass')->willReturn('');
        $player->method('getDaysRemainingForInjury')->willReturn(0);
        $player->method('getInjuryReturnDate')->willReturn('');

        return $player;
    }

    /**
     * Create a mock Team object
     *
     * @return \Team\Team&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockTeam(): \Team\Team
    {
        $team = $this->createMock(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 1;

        return $team;
    }

    /**
     * Test that empty player list renders without errors
     */
    public function testEmptyPlayerList(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        $html = Ratings::render($mockDb, [], $team, '', $mockSeason, 'NextSim');

        $this->assertIsString($html);
        $this->assertStringContainsString('<tbody>', $html);
        $this->assertStringContainsString('</tbody>', $html);
    }

    /**
     * Test that first player is rendered with correct data (not blank/zero)
     */
    public function testFirstPlayerHasData(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        $players = [
            $this->createMockPlayer('Test Player One', 100),
            $this->createMockPlayer('Test Player Two', 200),
        ];

        $html = Ratings::render($mockDb, $players, $team, '', $mockSeason, 'NextSim');

        // Verify first player name appears in the table
        $this->assertStringContainsString('Test Player One', $html);
        
        // Verify the first player row contains non-zero ratings
        $this->assertMatchesRegularExpression(
            '/Test Player One.*?<td[^>]*>75<\/td>/s',
            $html,
            'First player should have non-zero rating values'
        );
    }
}
