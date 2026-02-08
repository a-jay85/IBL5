<?php

declare(strict_types=1);

namespace Tests\UI\Tables;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use UI\Tables\Ratings;
use Player\Player;

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
        $player->playerID = $playerId;
        $player->name = $name;
        $player->position = 'PG';
        $player->age = 25;
        $player->ratingFieldGoalAttempts = 15;
        $player->ratingFieldGoalPercentage = 45;
        $player->ratingFreeThrowAttempts = 5;
        $player->ratingFreeThrowPercentage = 80;
        $player->ratingThreePointAttempts = 5;
        $player->ratingThreePointPercentage = 35;
        $player->ratingOffensiveRebounds = 2;
        $player->ratingDefensiveRebounds = 4;
        $player->ratingAssists = 8;
        $player->ratingSteals = 1;
        $player->ratingTurnovers = 2;
        $player->ratingBlocks = 0;
        $player->ratingFouls = 2;
        $player->ratingOutsideOffense = 75;
        $player->ratingDriveOffense = 70;
        $player->ratingPostOffense = 50;
        $player->ratingTransitionOffense = 80;
        $player->ratingOutsideDefense = 70;
        $player->ratingDriveDefense = 65;
        $player->ratingPostDefense = 55;
        $player->ratingTransitionDefense = 75;
        $player->ratingClutch = 70;
        $player->ratingConsistency = 75;
        $player->decoratedName = $name;
        $player->daysRemainingForInjury = 0;

        $player->method('getInjuryReturnDate')->willReturn('');

        return $player;
    }

    /**
     * Create a mock Team object
     *
     * @return \stdClass
     */
    private function createMockTeam(): object
    {
        $team = new \stdClass();
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamID = 1;

        return $team;
    }

    /**
     * Test that NextSim module doesn't render empty row at the beginning
     */
    public function testNextSimNoEmptyRowAtBeginning(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        $players = [
            $this->createMockPlayer('Player 1', 1),
            $this->createMockPlayer('Player 2', 2),
        ];

        $html = Ratings::render($mockDb, $players, $team, '', $mockSeason, 'NextSim');

        // Find the opening <tbody> tag and get content after it
        $tableStart = strpos($html, '<tbody>');
        $this->assertNotFalse($tableStart, 'tbody should exist in ratings table');

        // Get content after <tbody> tag
        $afterTbody = substr($html, $tableStart);

        // The first element after <tbody> should be a <tr> with player data, NOT an empty separator
        // Player rows don't have the "ratings-separator" class
        $firstRowMatch = preg_match(
            '/<tbody>\\s*<tr(?![^>]*class="ratings-separator")/',
            $afterTbody
        );

        $this->assertEquals(
            1,
            $firstRowMatch,
            'First row after <tbody> should be a player row, not an empty separator'
        );

        // Verify no separator row at the start of tbody (those have class="ratings-separator")
        $emptyRowMatch = preg_match(
            '/<tbody>\\s*<tr[^>]*class="ratings-separator"/',
            $afterTbody
        );

        $this->assertEquals(
            0,
            $emptyRowMatch,
            'Should not have empty separator row at the beginning of tbody'
        );
    }

    /**
     * Test that NextSim separator rows exist between pairs (but not at start)
     */
    public function testNextSimSeparatorRowsBetweenPairs(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        // Create 4 players to generate separator rows
        $players = [
            $this->createMockPlayer('Player 1', 1),
            $this->createMockPlayer('Player 2', 2),
            $this->createMockPlayer('Player 3', 3),
            $this->createMockPlayer('Player 4', 4),
        ];

        $html = Ratings::render($mockDb, $players, $team, '', $mockSeason, 'NextSim');

        // Count separator rows (rows with class="ratings-separator")
        $separatorCount = preg_match_all(
            '/<tr[^>]*class="ratings-separator"/',
            $html
        );

        // With 4 players and NextSim mode, we should have 1 separator (between pairs 1-2 and 3-4)
        $this->assertEquals(
            1,
            $separatorCount,
            'Should have exactly one separator row between player pairs'
        );
    }

    /**
     * Test that non-NextSim modules don't render separators
     */
    public function testNonNextSimNoSeparatorRows(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        $players = [
            $this->createMockPlayer('Player 1', 1),
            $this->createMockPlayer('Player 2', 2),
            $this->createMockPlayer('Player 3', 3),
            $this->createMockPlayer('Player 4', 4),
        ];

        $html = Ratings::render($mockDb, $players, $team, '', $mockSeason, '');

        // Count separator rows (rows with class="ratings-separator")
        $separatorCount = preg_match_all(
            '/<tr[^>]*class="ratings-separator"/',
            $html
        );

        // Non-NextSim modules should have 0 separator rows
        $this->assertEquals(
            0,
            $separatorCount,
            'Non-NextSim modules should not have separator rows'
        );
    }

    /**
     * Test that empty player list renders without errors
     */
    public function testEmptyPlayerList(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season::class);
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
        $mockSeason = $this->createMock(\Season::class);
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
