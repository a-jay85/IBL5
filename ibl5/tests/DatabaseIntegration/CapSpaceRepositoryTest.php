<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use CapSpace\CapSpaceRepository;

/**
 * Tests CapSpaceRepository against real MariaDB — team listings and
 * players under contract filtering (cy != cyt, excludes pipe names).
 */
class CapSpaceRepositoryTest extends DatabaseTestCase
{
    private CapSpaceRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new CapSpaceRepository($this->db);
    }

    // ── getAllTeams ──────────────────────────────────────────────

    public function testGetAllTeamsReturns28Teams(): void
    {
        $teams = $this->repo->getAllTeams();

        self::assertCount(28, $teams);
    }

    // ── getPlayersUnderContractAfterSeason ───────────────────────

    public function testGetPlayersUnderContractAfterSeasonReturnsMatchingPlayers(): void
    {
        // cy=1, cyt=3 → cy != cyt, so player is under contract after this season
        $this->insertTestPlayer(200100001, 'CapSpace Under', ['tid' => 1, 'cy' => 1, 'cyt' => 3]);

        $players = $this->repo->getPlayersUnderContractAfterSeason(1);

        $found = false;
        foreach ($players as $row) {
            if ($row['cy'] === 1 && $row['cyt'] === 3) {
                $found = true;
                break;
            }
        }
        // We may have pre-existing players too, so just check our insert is present
        self::assertTrue($found, 'Expected to find player with cy=1, cyt=3');
    }

    public function testGetPlayersUnderContractAfterSeasonExcludesExpiringContracts(): void
    {
        // Insert a non-expiring player to guarantee the result is non-empty
        $this->insertTestPlayer(200100003, 'CapSpace Active', ['tid' => 1, 'cy' => 1, 'cyt' => 3]);
        // Insert an expiring player (cy=3, cyt=3) — should NOT be returned
        $this->insertTestPlayer(200100002, 'CapSpace Expirn', ['tid' => 1, 'cy' => 3, 'cyt' => 3]);

        $players = $this->repo->getPlayersUnderContractAfterSeason(1);

        self::assertNotEmpty($players, 'Expected at least one non-expiring player');
        foreach ($players as $row) {
            // No row should have cy === cyt (that's the filter condition)
            self::assertNotSame($row['cy'], $row['cyt'], 'Found expiring contract that should have been filtered');
        }
    }
}
