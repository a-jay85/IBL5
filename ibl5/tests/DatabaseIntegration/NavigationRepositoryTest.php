<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Navigation\NavigationRepository;

/**
 * Tests NavigationRepository against real MariaDB — team ID resolution
 * from username and teams data grouped by conference/division.
 */
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
        $stmt->bind_param('si', $user, $teamid);
        $user = 'nav_test_gm';
        $teamid = 1;
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
        self::assertNotEmpty($result);
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
}
