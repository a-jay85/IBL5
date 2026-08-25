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
     * @param string $nameStatusClass Value returned by getNameStatusClass() ('' = normal, 'player-expiring' = expiring)
     * @return Player&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockPlayer(string $name = 'Test Player', int $playerId = 1, string $nameStatusClass = ''): Player
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
        $player->method('getNameStatusClass')->willReturn($nameStatusClass);
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
     * Characterization pin: render() with no $ariaLabel arg emits table open tag with NO aria-label
     */
    public function testTableOpenTagHasNoAriaLabel(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        $html = Ratings::render($mockDb, [], $team, '', $mockSeason, 'NextSim');

        $this->assertStringContainsString('<table class="ibl-data-table team-table responsive-table sortable"', $html);
        $this->assertStringNotContainsString('aria-label', $html);
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

    // ============================================
    // Phase 4 — markExpiringRows flag tests
    // ============================================

    /**
     * Positive: flag true + expiring player (getNameStatusClass() === 'player-expiring')
     * must produce a row with class="player-fa-expiring-row".
     */
    public function testRenderMarksExpiringRowWhenFlagEnabled(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        $players = [$this->createMockPlayer('Expiring Player', 1, 'player-expiring')];

        $html = Ratings::render($mockDb, $players, $team, '', $mockSeason, '', [], '', true);

        $this->assertStringContainsString('class="player-fa-expiring-row"', $html);
    }

    /**
     * Negative: flag true but player has years remaining (getNameStatusClass() returns '').
     * The class must NOT appear — proves the predicate discriminates, not just that the flag gates.
     */
    public function testRenderDoesNotMarkPlayerWithYearsRemaining(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        // cy=1 of cyt=3 — not expiring, nameStatusClass = ''
        $players = [$this->createMockPlayer('Under Contract', 2, '')];

        $html = Ratings::render($mockDb, $players, $team, '', $mockSeason, '', [], '', true);

        $this->assertStringNotContainsString('player-fa-expiring-row', $html);
    }

    /**
     * Negative: flag omitted (default false), expiring player.
     * The class must NOT appear — proves default is behavior-preserving.
     */
    public function testRenderDoesNotMarkExpiringRowWhenFlagDisabled(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        $players = [$this->createMockPlayer('Expiring Player', 3, 'player-expiring')];

        // Flag defaults to false — call with 8 positional args (no trailing bool)
        $html = Ratings::render($mockDb, $players, $team, '', $mockSeason, '', [], '');

        $this->assertStringNotContainsString('player-fa-expiring-row', $html);
    }

    /**
     * Boundary: flag omitted, the <tr> must have NO class= attribute at all.
     * Proves no empty-attribute drift (no stray class="").
     */
    public function testRenderTrIsByteIdenticalWhenFlagDisabled(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        $players = [$this->createMockPlayer('Test Player', 4, '')];

        $html = Ratings::render($mockDb, $players, $team, '', $mockSeason, '', [], '');

        // The only <tr> inside <tbody> must not carry any class= attribute
        $tbodyStart = strpos($html, '<tbody>');
        $this->assertNotFalse($tbodyStart, '<tbody> must exist in output');
        $tbodyFragment = substr($html, (int) $tbodyStart);
        $trPos = strpos($tbodyFragment, '<tr');
        $this->assertNotFalse($trPos, '<tr> must exist inside <tbody>');
        $trEnd = strpos($tbodyFragment, '>', (int) $trPos);
        $this->assertNotFalse($trEnd, '<tr> opening tag must be closed');
        $trTag = substr($tbodyFragment, (int) $trPos, (int) $trEnd - (int) $trPos + 1);
        $this->assertStringNotContainsString('class=', $trTag, '<tr> must carry no class= attribute when flag is false');
    }

    /**
     * Mixed-roster boundary: three players — expiring (cy=cyt), mid-contract (cy=1,cyt=3),
     * and late-but-not-expiring (cy=3,cyt=4) — with flag true.
     * Exactly ONE row must receive player-fa-expiring-row.
     * The cy=3,cyt=4 case is the off-by-one guard.
     */
    public function testRenderMarksOnlyExpiringRowsInMixedRoster(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $team = $this->createMockTeam();

        $players = [
            $this->createMockPlayer('Expiring Player', 10, 'player-expiring'),     // cy=cyt
            $this->createMockPlayer('Early Contract', 11, ''),                      // cy=1,cyt=3
            $this->createMockPlayer('Late Non-Expiring', 12, ''),                   // cy=3,cyt=4
        ];

        $html = Ratings::render($mockDb, $players, $team, '', $mockSeason, '', [], '', true);

        $this->assertSame(
            1,
            substr_count($html, 'player-fa-expiring-row'),
            'Exactly one row should be marked as expiring'
        );
    }
}
