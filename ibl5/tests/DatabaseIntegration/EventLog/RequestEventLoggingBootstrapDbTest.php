<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\EventLog;

use EventLog\EventLogRepository;
use League\League;
use PHPUnit\Framework\Attributes\Group;
use Repositories\TeamIdentityRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Verifies the component behavior the bootstrap step composes:
 * team resolver accuracy and authenticated/anonymous repository writes.
 *
 * boot() cannot be driven directly here because PHP_SAPI==='cli' in the
 * phpunit process triggers the step's CLI no-op guard (verified in the unit
 * suite: RequestEventLoggingBootstrapTest::testNoOpUnderCliSapi). These tests
 * prove the same matrix assertions at the component level.
 */
#[Group('database')]
final class RequestEventLoggingBootstrapDbTest extends DatabaseTestCase
{
    private EventLogRepository $repo;
    private TeamIdentityRepository $teamRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EventLogRepository($this->db);
        $this->teamRepo = new TeamIdentityRepository($this->db);
    }

    // ── Matrix row: authenticated row w/ resolved team_id ───────────────────

    public function testAuthenticatedRequestLogsResolvedTeamId(): void
    {
        // Resolve team for testgm the same way the step does.
        $teamName = $this->teamRepo->getTeamnameFromUsername('testgm');
        self::assertNotNull($teamName, 'testgm must resolve to a team in seed');
        $teamId = $this->teamRepo->getTidFromTeamname($teamName);

        $this->repo->insert(
            '/ibl5/modules.php?name=Team_Info',
            'Team_Info',
            'GET',
            'testgm',
            $teamId,
            null,
            'UA/1.0'
        );

        $stmt = $this->db->prepare(
            'SELECT username, team_id, route_name, http_method FROM `ibl_events` ORDER BY id DESC LIMIT 1'
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('testgm', $row['username']);
        self::assertSame(1, $row['team_id']);   // Metros = team_id 1 per db-seed.sql
        self::assertSame('Team_Info', $row['route_name']);
        self::assertSame('GET', $row['http_method']);
    }

    // ── Matrix row: anonymous NULL row via step ──────────────────────────────

    public function testAnonymousRequestLogsNullIdentity(): void
    {
        // Anonymous: no username → no team resolution → NULL identity columns.
        $this->repo->insert(
            '/ibl5/modules.php?name=Standings',
            'Standings',
            'GET',
            null,
            null,
            null,
            null
        );

        $stmt = $this->db->prepare(
            'SELECT username, team_id, request_uri FROM `ibl_events` ORDER BY id DESC LIMIT 1'
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertNull($row['username']);
        self::assertNull($row['team_id']);
        self::assertNotEmpty($row['request_uri']);  // pageview still recorded
    }

    // ── Matrix row: team resolver known-GM vs anon/FA ───────────────────────

    public function testTeamResolverKnownGmReturnsTid(): void
    {
        $teamName = $this->teamRepo->getTeamnameFromUsername('testgm');
        self::assertNotNull($teamName);

        $teamId = $this->teamRepo->getTidFromTeamname($teamName);
        self::assertSame(1, $teamId);
    }

    public function testTeamResolverNullUsernameReturnsFreeAgents(): void
    {
        $teamName = $this->teamRepo->getTeamnameFromUsername(null);
        self::assertSame(League::FREE_AGENTS_TEAM_NAME, $teamName);
    }

    public function testTeamResolverUnknownUsernameReturnsNull(): void
    {
        $teamName = $this->teamRepo->getTeamnameFromUsername('no-such-user-xyz');
        self::assertNull($teamName);
    }
}
