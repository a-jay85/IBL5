<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Navigation\NavigationRepository;

/**
 * Tests NavigationRepository against real MariaDB — team ID resolution
 * from username and teams data grouped by conference/division.
 */
#[Group('database')]
class NavigationRepositoryTest extends DatabaseTestCase
{
    private NavigationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new NavigationRepository($this->db);
    }

    // ── resolveTeamId ───────────────────────────────────────────

    public function testResolveTeamIdReturnsTeamIdForKnownUser(): void
    {
        // Set gm_username within the transaction (production may differ from CI seed)
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET gm_username = ? WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $user = 'nav_test_gm';
        $teamid = 1;
        $stmt->bind_param('si', $user, $teamid);
        $stmt->execute();
        $stmt->close();

        $result = $this->repo->resolveTeamId('nav_test_gm');

        self::assertSame(1, $result);
    }

    public function testResolveTeamIdReturnsNullForUnknownUser(): void
    {
        $result = $this->repo->resolveTeamId('nonexistent_user_999');

        self::assertNull($result);
    }

    // ── getTeamsData ────────────────────────────────────────────

    public function testGetTeamsDataReturnsGroupedByConferenceDivision(): void
    {
        $result = $this->repo->getTeamsData();

        self::assertNotNull($result);
        self::assertIsArray($result);

        // Should have conference keys
        self::assertNotSame([], $result);
        foreach ($result as $conference => $divisions) {
            self::assertIsString($conference);
            self::assertIsArray($divisions);
            foreach ($divisions as $division => $teams) {
                self::assertIsString($division);
                self::assertIsArray($teams);
                foreach ($teams as $team) {
                    self::assertArrayHasKey('teamid', $team);
                    self::assertArrayHasKey('team_name', $team);
                    self::assertArrayHasKey('team_city', $team);
                }
            }
        }
    }

    public function testGetTeamsDataContainsTeams(): void
    {
        $result = $this->repo->getTeamsData();

        self::assertNotNull($result);

        // Flatten and count all teams — JOIN requires matching team_name in both tables
        $totalTeams = 0;
        foreach ($result as $divisions) {
            foreach ($divisions as $teams) {
                $totalTeams += count($teams);
            }
        }

        // Production may have fewer matches than CI seed (team name mismatches)
        self::assertGreaterThanOrEqual(26, $totalTeams);
    }

    public function testGetTeamsDataBreaksTeamCityTiesByTeamidAscending(): void
    {
        $divisionResult = $this->db->query(
            "SELECT ti.teamid FROM ibl_team_info ti
             JOIN ibl_standings s ON ti.team_name = s.team_name
             WHERE s.conference = 'Eastern'
               AND s.division = (SELECT MIN(division) FROM ibl_standings WHERE conference = 'Eastern')
             ORDER BY ti.teamid"
        );
        self::assertInstanceOf(\mysqli_result::class, $divisionResult);
        $divisionTeamids = array_map('intval', array_column($divisionResult->fetch_all(MYSQLI_ASSOC), 'teamid'));
        self::assertGreaterThanOrEqual(2, count($divisionTeamids));

        $this->db->query(
            "UPDATE ibl_team_info SET team_city = 'Aaa Tie' WHERE teamid IN (" . implode(', ', $divisionTeamids) . ')'
        );

        $result = $this->repo->getTeamsData();

        self::assertNotNull($result);
        $teamids = [];
        $totalTeams = 0;
        foreach ($result as $divisions) {
            foreach ($divisions as $teams) {
                foreach ($teams as $team) {
                    $totalTeams++;
                    if (in_array($team['teamid'], $divisionTeamids, true)) {
                        $teamids[] = $team['teamid'];
                    }
                }
            }
        }

        self::assertSame($divisionTeamids, $teamids);

        $countResult = $this->db->query(
            'SELECT COUNT(*) AS n FROM ibl_team_info ti JOIN ibl_standings s ON ti.team_name = s.team_name'
        );
        self::assertInstanceOf(\mysqli_result::class, $countResult);
        $joinCount = $countResult->fetch_assoc();
        self::assertNotNull($joinCount);
        self::assertSame((int) $joinCount['n'], $totalTeams);
    }
}
