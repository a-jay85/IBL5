<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use GMContactList\GMContactListRepository;

/**
 * Tests GMContactListRepository against real MariaDB — GM contact
 * listings with team info for all 28 real teams.
 */
#[Group('database')]
class GMContactListRepositoryTest extends DatabaseTestCase
{
    private GMContactListRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new GMContactListRepository($this->db);
    }

    public function testGetAllTeamContactsReturns28Teams(): void
    {
        $contacts = $this->repo->getAllTeamContacts();

        self::assertCount(28, $contacts);
    }

    public function testGetAllTeamContactsIncludesContactFields(): void
    {
        $contacts = $this->repo->getAllTeamContacts();

        self::assertNotEmpty($contacts);
        $first = $contacts[0];
        self::assertArrayHasKey('teamid', $first);
        self::assertArrayHasKey('team_city', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('color1', $first);
        self::assertArrayHasKey('color2', $first);
        self::assertArrayHasKey('owner_name', $first);
        self::assertArrayHasKey('discord_id', $first);
    }

    public function testExcludesTeamsOutsideRealFranchiseRange(): void
    {
        $this->insertRow('ibl_team_info', ['teamid' => 99]);

        $contacts = $this->repo->getAllTeamContacts();

        $teamIds = array_map('intval', array_column($contacts, 'teamid'));

        self::assertNotContains(
            99,
            $teamIds,
            'teamid 99 is outside the 1..League::MAX_REAL_TEAMID franchise range and must not be returned'
        );
        self::assertCount(
            28,
            $contacts,
            'seeding an out-of-range team must not change the real-franchise row count'
        );

        foreach ($teamIds as $teamId) {
            self::assertGreaterThanOrEqual(1, $teamId);
            self::assertLessThanOrEqual(28, $teamId);
        }
    }

    public function testTwoDistinctInRangeTeamsAreNotCollapsed(): void
    {
        $contacts = $this->repo->getAllTeamContacts();

        self::assertGreaterThanOrEqual(
            2,
            count($contacts),
            'dedup can only be observed across at least two distinct in-range teams'
        );

        $teamIds = array_map('intval', array_column($contacts, 'teamid'));

        self::assertCount(
            count($contacts),
            $teamIds,
            'every returned row must carry a teamid — a dropped key would silently hide a duplicate'
        );
        self::assertCount(
            count($teamIds),
            array_unique($teamIds),
            'each in-range team must appear exactly once; a collapsing GROUP BY/DISTINCT or a fan-out JOIN would break this'
        );
    }
}
