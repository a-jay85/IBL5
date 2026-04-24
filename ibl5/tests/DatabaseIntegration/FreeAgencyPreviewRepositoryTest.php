<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use FreeAgencyPreview\FreeAgencyPreviewRepository;

/**
 * Tests FreeAgencyPreviewRepository against real MariaDB — active player
 * listings with ratings and FA preference fields.
 */
class FreeAgencyPreviewRepositoryTest extends DatabaseTestCase
{
    private FreeAgencyPreviewRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FreeAgencyPreviewRepository($this->db);
    }

    // ── getActivePlayers ────────────────────────────────────────

    public function testGetActivePlayersReturnsNonEmptyList(): void
    {
        $this->insertTestPlayer(200120001, 'FA Preview Plyr', ['teamid' => 1, 'retired' => 0]);

        $players = $this->repo->getActivePlayers();

        self::assertNotEmpty($players);
    }

    public function testGetActivePlayersIncludesRatingsAndPreferences(): void
    {
        $this->insertTestPlayer(200120002, 'FA Preview Rate', ['teamid' => 1, 'retired' => 0]);

        $players = $this->repo->getActivePlayers();

        self::assertNotEmpty($players);
        $first = $players[0];

        // Rating fields
        self::assertArrayHasKey('r_fga', $first);
        self::assertArrayHasKey('r_fgp', $first);
        self::assertArrayHasKey('r_orb', $first);
        self::assertArrayHasKey('r_ast', $first);

        // Tendency fields
        self::assertArrayHasKey('oo', $first);
        self::assertArrayHasKey('r_drive_off', $first);

        // FA preference fields
        self::assertArrayHasKey('loyalty', $first);
        self::assertArrayHasKey('winner', $first);
        self::assertArrayHasKey('playing_time', $first);
        self::assertArrayHasKey('security', $first);
        self::assertArrayHasKey('tradition', $first);

        // Team info from JOIN
        self::assertArrayHasKey('team_city', $first);
        self::assertArrayHasKey('color1', $first);
    }
}
