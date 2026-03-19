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
        self::assertIsInt($team['teamid']);
        self::assertSame('Metros', $team['team_name']);
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
        // Method queries ibl_team_info.gm_username, so update a real team within the transaction
        $stmt = $this->db->prepare("UPDATE ibl_team_info SET gm_username = ? WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $username);
        $username = 'db_inttest_gm';
        $stmt->execute();
        $stmt->close();

        $result = $this->repo->getTeamnameFromUsername('db_inttest_gm');

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
        $this->insertTestPlayer(200010001, 'CMR TestPlyr', ['tid' => 1]);

        $player = $this->repo->getPlayerByID(200010001);

        self::assertNotNull($player);
        self::assertSame(200010001, $player['pid']);
        self::assertSame('CMR TestPlyr', $player['name']);
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

        self::assertIsString($name);
        self::assertNotEmpty($name);
    }

    public function testGetUserByUsernameReturnsRow(): void
    {
        $this->insertRow('nuke_users', [
            'username' => 'db_inttest_usr',
            'user_email' => 'test2@test.com',
            'user_ibl_team' => 'Metros',
            'user_password' => 'x',
            'user_avatar' => '',
            'bio' => '',
            'ublock' => '',
        ]);

        $user = $this->repo->getUserByUsername('db_inttest_usr');

        self::assertNotNull($user);
        self::assertSame('db_inttest_usr', $user['username']);
    }
}
