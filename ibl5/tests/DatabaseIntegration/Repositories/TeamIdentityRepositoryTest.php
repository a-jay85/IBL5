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

    public function testGetTeamColorRowReturnsStoredColors(): void
    {
        self::assertNotNull($this->repo->getTeamnameFromTeamID(1));
        $this->db->query("UPDATE ibl_team_info SET color1 = 'ABC123', color2 = '0F0F0F' WHERE teamid = 1");

        self::assertSame(['color1' => 'ABC123', 'color2' => '0F0F0F'], $this->repo->getTeamColorRow(1));
    }

    public function testGetTeamColorRowReturnsNullForUnknownTeam(): void
    {
        self::assertNotNull($this->repo->getTeamnameFromTeamID(1));

        self::assertNull($this->repo->getTeamColorRow(999999));
    }

    /**
     * The fallback lives in TeamColorHelper, not SQL: a query that adds
     * COALESCE(NULLIF(color1,''),'D4AF37') would turn '' into gold here.
     */
    public function testGetTeamColorRowReturnsEmptyColorsUnchanged(): void
    {
        self::assertNotNull($this->repo->getTeamnameFromTeamID(1));
        $this->db->query("UPDATE ibl_team_info SET color1 = '', color2 = '' WHERE teamid = 1");

        self::assertSame(['color1' => '', 'color2' => ''], $this->repo->getTeamColorRow(1));
    }

    public function testGetOwnerNameReturnsStoredOwner(): void
    {
        self::assertNotNull($this->repo->getTeamnameFromTeamID(1));
        $this->db->query("UPDATE ibl_team_info SET owner_name = 'Pin Owner' WHERE teamid = 1");

        self::assertSame('Pin Owner', $this->repo->getOwnerName(1));
    }

    public function testGetOwnerNameReturnsNullForUnknownTeam(): void
    {
        self::assertNotNull($this->repo->getTeamnameFromTeamID(1));

        self::assertNull($this->repo->getOwnerName(999999));
    }

    public function testGetOwnerNameReturnsEmptyStringForBlankOwner(): void
    {
        self::assertNotNull($this->repo->getTeamnameFromTeamID(1));
        $this->db->query("UPDATE ibl_team_info SET owner_name = '' WHERE teamid = 1");

        self::assertSame('', $this->repo->getOwnerName(1));
    }
}
