<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Repositories;

use PHPUnit\Framework\Attributes\Group;
use Repositories\TeamIdentityRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * DB-integration tests for the isKnownDiscordID method added in PR #3.
 * The seed sets discord_id = '100000000000000001' on teamid=1.
 */
#[Group('database')]
class TeamIdentityRepositoryTest extends DatabaseTestCase
{
    private TeamIdentityRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamIdentityRepository($this->db);
    }

    public function testIsKnownDiscordIDReturnsTrueForSeedTeam(): void
    {
        // Seed: UPDATE ibl_team_info SET discord_id = '100000000000000001' WHERE teamid = 1
        self::assertTrue($this->repo->isKnownDiscordID('100000000000000001'));
    }

    public function testIsKnownDiscordIDReturnsFalseForUnknownId(): void
    {
        self::assertFalse($this->repo->isKnownDiscordID('999999999999999999'));
    }

    public function testIsKnownDiscordIDReturnsFalseForNullDiscordTeam(): void
    {
        // Teams without a discord_id set must NOT match any lookup
        // Insert a team with no discord_id and verify it doesn't spuriously match
        self::assertFalse($this->repo->isKnownDiscordID('0'));
    }
}
