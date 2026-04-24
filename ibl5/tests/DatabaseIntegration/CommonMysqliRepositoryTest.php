<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
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
        $username = 'db_inttest_gm';
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->close();

        $result = $this->repo->getTeamnameFromUsername('db_inttest_gm');

        self::assertSame('Metros', $result);
    }

    public function testGetTidFromTeamnameReturnsInt(): void
    {
        $teamid = $this->repo->getTidFromTeamname('Metros');

        self::assertSame(1, $teamid);
    }

    public function testGetTidFromTeamnameReturnsNullForUnknown(): void
    {
        $teamid = $this->repo->getTidFromTeamname('Nonexistent');

        self::assertNull($teamid);
    }

    public function testGetPlayerByIdReturnsRow(): void
    {
        $this->insertTestPlayer(200010001, 'CMR TestPlyr', ['teamid' => 1]);

        $player = $this->repo->getPlayerByID(200010001);

        self::assertNotNull($player);
        self::assertSame(200010001, $player['pid']);
        self::assertSame('CMR TestPlyr', $player['name']);
        self::assertSame(1, $player['teamid']);
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
        // testgm user is seeded in auth_users by db-seed.sql
        $user = $this->repo->getUserByUsername('testgm');

        self::assertNotNull($user);
        self::assertSame('testgm', $user['username']);
        self::assertArrayHasKey('user_id', $user);
        self::assertArrayHasKey('user_email', $user);
    }

    // ── getUsernameFromTeamname ──────────────────────────────────

    public function testGetUsernameFromTeamnameReturnsGmUsername(): void
    {
        $stmt = $this->db->prepare("UPDATE ibl_team_info SET gm_username = ? WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $username = 'b8_gm_user';
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->close();

        self::assertSame('b8_gm_user', $this->repo->getUsernameFromTeamname('Metros'));
    }

    public function testGetUsernameFromTeamnameReturnsNullForUnknown(): void
    {
        self::assertNull($this->repo->getUsernameFromTeamname('Nonexistent'));
    }

    // ── getTeamDiscordID ────────────────────────────────────────

    public function testGetTeamDiscordIdReturnsIntOrNull(): void
    {
        $result = $this->repo->getTeamDiscordID('Metros');

        // CI seed may or may not have discord_id — either int or null is valid
        self::assertTrue($result === null || is_int($result));
    }

    public function testGetTeamDiscordIdReturnsNullForUnknownTeam(): void
    {
        self::assertNull($this->repo->getTeamDiscordID('Nonexistent'));
    }

    // ── getPlayerIDFromPlayerName ───────────────────────────────

    public function testGetPlayerIdFromPlayerNameReturnsPid(): void
    {
        $this->insertTestPlayer(200010020, 'CMR NameTest');

        self::assertSame(200010020, $this->repo->getPlayerIDFromPlayerName('CMR NameTest'));
    }

    public function testGetPlayerIdFromPlayerNameReturnsNullForUnknown(): void
    {
        self::assertNull($this->repo->getPlayerIDFromPlayerName('CMR NoSuchPlayer'));
    }

    // ── getPlayerByName ─────────────────────────────────────────

    public function testGetPlayerByNameReturnsRowWithTeamData(): void
    {
        $this->insertTestPlayer(200010021, 'CMR ByName', ['teamid' => 1]);

        $player = $this->repo->getPlayerByName('CMR ByName');

        self::assertNotNull($player);
        self::assertSame(200010021, $player['pid']);
        self::assertSame('CMR ByName', $player['name']);
        self::assertSame('Metros', $player['teamname']);
        self::assertArrayHasKey('color1', $player);
        self::assertArrayHasKey('color2', $player);
    }

    public function testGetPlayerByNameReturnsNullForUnknown(): void
    {
        self::assertNull($this->repo->getPlayerByName('CMR NoSuchPlayer'));
    }

    // ── getAllRealTeams ──────────────────────────────────────────

    public function testGetAllRealTeamsReturns28Teams(): void
    {
        $teams = $this->repo->getAllRealTeams();

        self::assertCount(28, $teams);
    }

    public function testGetAllRealTeamsExcludesSpecialTeams(): void
    {
        $teams = $this->repo->getAllRealTeams();
        $ids = array_column($teams, 'teamid');

        self::assertNotContains(0, $ids);   // Free Agents
        self::assertNotContains(40, $ids);  // Rookies
        self::assertNotContains(41, $ids);  // Sophomores
        self::assertNotContains(50, $ids);  // All-Star Away
        self::assertNotContains(51, $ids);  // All-Star Home
    }

    // ── Salary methods ──────────────────────────────────────────

    public function testGetTeamTotalSalaryReturnsInt(): void
    {
        $salary = $this->repo->getTeamTotalSalary('Metros');

        self::assertIsInt($salary);
        self::assertGreaterThanOrEqual(0, $salary);
    }

    public function testGetTeamTotalSalaryReturnsZeroForUnknownTeam(): void
    {
        self::assertSame(0, $this->repo->getTeamTotalSalary('Nonexistent'));
    }

    public function testGetTeamNextYearSalaryReturnsInt(): void
    {
        $salary = $this->repo->getTeamNextYearSalary('Metros');

        self::assertIsInt($salary);
        self::assertGreaterThanOrEqual(0, $salary);
    }

    public function testGetPositionSalaryCommitmentNextYearReturnsInt(): void
    {
        $salary = $this->repo->getPositionSalaryCommitmentNextYear('Metros', 'PG', 0);

        self::assertIsInt($salary);
        self::assertGreaterThanOrEqual(0, $salary);
    }

    public function testGetTeamSalarySummaryReturnsBothKeys(): void
    {
        $summary = $this->repo->getTeamSalarySummary('Metros');

        self::assertArrayHasKey('current', $summary);
        self::assertArrayHasKey('nextYear', $summary);
        self::assertIsInt($summary['current']);
        self::assertIsInt($summary['nextYear']);
    }

    public function testGetTeamCapSpaceNextSeasonReturnsInt(): void
    {
        $capSpace = $this->repo->getTeamCapSpaceNextSeason('Metros');

        self::assertIsInt($capSpace);
        // Cap space = HARD_CAP_MAX - next year salary
        $nextYear = $this->repo->getTeamNextYearSalary('Metros');
        self::assertSame(League::HARD_CAP_MAX - $nextYear, $capSpace);
    }
}
