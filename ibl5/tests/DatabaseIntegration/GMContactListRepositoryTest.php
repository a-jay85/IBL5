<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use GMContactList\GMContactListRepository;

/**
 * Tests GMContactListRepository against real MariaDB — GM contact
 * listings with team info for all 28 real teams.
 */
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
        self::assertArrayHasKey('discordID', $first);
    }
}
