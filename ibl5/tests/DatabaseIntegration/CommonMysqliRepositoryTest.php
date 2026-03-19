<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Services\CommonMysqliRepository;

/**
 * Tests CommonMysqliRepository read-only lookups against real MariaDB.
 */
class CommonMysqliRepositoryTest extends DatabaseTestCase
{
    private CommonMysqliRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new CommonMysqliRepository($this->db);
    }

    public function testGetTeamByNameReturnsRowForKnownTeam(): void
    {
        $team = $this->repo->getTeamByName('Metros');

        self::assertNotNull($team);
        self::assertSame(1, $team['teamid']);
        self::assertSame('New York', $team['team_city']);
    }

    public function testGetTeamByNameReturnsNullForUnknownTeam(): void
    {
        $team = $this->repo->getTeamByName('Nonexistent');

        self::assertNull($team);
    }

    public function testGetTeamnameFromUsernameReturnsFreeAgentsForNull(): void
    {
        $result = $this->repo->getTeamnameFromUsername(null);

        self::assertSame('Free Agents', $result);
    }

    public function testGetTeamnameFromUsernameReturnsTeamForKnownGm(): void
    {
        $result = $this->repo->getTeamnameFromUsername('testgm');

        self::assertSame('Metros', $result);
    }

    public function testGetTidFromTeamnameReturnsInt(): void
    {
        $tid = $this->repo->getTidFromTeamname('Metros');

        self::assertSame(1, $tid);
    }

    public function testGetTidFromTeamnameReturnsNullForUnknown(): void
    {
        $tid = $this->repo->getTidFromTeamname('Nonexistent');

        self::assertNull($tid);
    }

    public function testGetPlayerByIdReturnsRow(): void
    {
        $player = $this->repo->getPlayerByID(1);

        self::assertNotNull($player);
        self::assertSame(1, $player['pid']);
        self::assertSame('Test Player One', $player['name']);
        self::assertSame(1, $player['tid']);
        self::assertSame('Metros', $player['teamname']);
    }

    public function testGetPlayerByIdReturnsNullForUnknown(): void
    {
        $player = $this->repo->getPlayerByID(99999);

        self::assertNull($player);
    }

    public function testGetTeamnameFromTeamIdReturnsString(): void
    {
        $name = $this->repo->getTeamnameFromTeamID(1);

        self::assertSame('Metros', $name);
    }

    public function testGetUserByUsernameReturnsRow(): void
    {
        $user = $this->repo->getUserByUsername('testgm');

        self::assertNotNull($user);
        self::assertSame('testgm', $user['username']);
    }
}
