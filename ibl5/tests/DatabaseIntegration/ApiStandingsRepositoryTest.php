<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Api\Repository\ApiStandingsRepository;

/**
 * Tests ApiStandingsRepository against real MariaDB —
 * standings view queries with optional conference filtering.
 * CI seed has 28 teams with standings data.
 */
class ApiStandingsRepositoryTest extends DatabaseTestCase
{
    private ApiStandingsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ApiStandingsRepository($this->db);
    }

    public function testGetStandingsReturnsAllTeams(): void
    {
        $result = $this->repo->getStandings();

        // CI seed has 28 teams with standings
        self::assertNotEmpty($result);
        self::assertCount(28, $result);
    }

    public function testGetStandingsRowHasExpectedStructure(): void
    {
        $result = $this->repo->getStandings();

        self::assertNotEmpty($result);
        $row = $result[0];

        self::assertArrayHasKey('teamid', $row);
        self::assertArrayHasKey('team_uuid', $row);
        self::assertArrayHasKey('team_city', $row);
        self::assertArrayHasKey('team_name', $row);
        self::assertArrayHasKey('full_team_name', $row);
        self::assertArrayHasKey('conference', $row);
        self::assertArrayHasKey('division', $row);
        self::assertArrayHasKey('league_record', $row);
        self::assertArrayHasKey('win_percentage', $row);
    }

    public function testGetStandingsFilteredByConference(): void
    {
        $allStandings = $this->repo->getStandings();
        $conferences = array_unique(array_column($allStandings, 'conference'));

        // Should have exactly 2 conferences
        self::assertCount(2, $conferences);

        foreach ($conferences as $conf) {
            self::assertIsString($conf);
            $filtered = $this->repo->getStandings($conf);

            self::assertNotEmpty($filtered);
            self::assertLessThan(count($allStandings), count($filtered));

            // Every row should match the requested conference
            foreach ($filtered as $row) {
                self::assertSame($conf, $row['conference']);
            }
        }
    }

    public function testGetStandingsOrderedByWinPercentage(): void
    {
        // When fetching by conference, should be ordered by win_percentage DESC
        $allStandings = $this->repo->getStandings();
        $conf = $allStandings[0]['conference'];
        self::assertIsString($conf);

        $filtered = $this->repo->getStandings($conf);
        $percentages = array_column($filtered, 'win_percentage');

        // All non-null values should be in descending order
        $prev = PHP_FLOAT_MAX;
        foreach ($percentages as $pct) {
            if ($pct !== null) {
                self::assertLessThanOrEqual($prev, $pct);
                $prev = $pct;
            }
        }
    }
}
