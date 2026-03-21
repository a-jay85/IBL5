<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use SeriesRecords\SeriesRecordsRepository;

/**
 * Tests SeriesRecordsRepository against real MariaDB — team listings,
 * series records via vw_series_records (derived from ibl_schedule),
 * and max team ID lookups.
 */
class SeriesRecordsRepositoryTest extends DatabaseTestCase
{
    private SeriesRecordsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SeriesRecordsRepository($this->db);
    }

    // ── getTeamsForSeriesRecords ─────────────────────────────────

    public function testGetTeamsForSeriesRecordsReturns28Teams(): void
    {
        $teams = $this->repo->getTeamsForSeriesRecords();

        self::assertCount(28, $teams);
        self::assertArrayHasKey('teamid', $teams[0]);
        self::assertArrayHasKey('team_city', $teams[0]);
        self::assertArrayHasKey('team_name', $teams[0]);
        self::assertArrayHasKey('color1', $teams[0]);
        self::assertArrayHasKey('color2', $teams[0]);
    }

    // ── getSeriesRecords ────────────────────────────────────────

    public function testGetSeriesRecordsReturnsRecordsFromScheduleData(): void
    {
        // Team 1 beats Team 2 at home (HScore > VScore)
        $this->insertScheduleRow(2099, '2099-01-15', 2, 90, 1, 100);
        // Team 2 beats Team 1 at home (HScore > VScore)
        $this->insertScheduleRow(2099, '2099-01-20', 1, 85, 2, 95);

        $records = $this->repo->getSeriesRecords();

        // Find the record for team 1 vs team 2
        $found1v2 = null;
        $found2v1 = null;
        foreach ($records as $row) {
            if ($row['self'] === 1 && $row['opponent'] === 2) {
                $found1v2 = $row;
            }
            if ($row['self'] === 2 && $row['opponent'] === 1) {
                $found2v1 = $row;
            }
        }

        // Team 1 won 1 game at home vs team 2
        self::assertNotNull($found1v2);
        self::assertGreaterThanOrEqual(1, $found1v2['wins']);

        // Team 2 won 1 game at home vs team 1
        self::assertNotNull($found2v1);
        self::assertGreaterThanOrEqual(1, $found2v1['wins']);
    }

    public function testGetSeriesRecordsResultsAreOrderedBySelfThenOpponent(): void
    {
        $records = $this->repo->getSeriesRecords();

        if (count($records) < 2) {
            // Not enough data to verify ordering — insert some
            $this->insertScheduleRow(2099, '2099-02-01', 2, 80, 1, 100);
            $records = $this->repo->getSeriesRecords();
        }

        // Verify ordering: self ASC, then opponent ASC
        for ($i = 1, $count = count($records); $i < $count; $i++) {
            $prev = $records[$i - 1];
            $curr = $records[$i];
            self::assertTrue(
                $prev['self'] < $curr['self']
                || ($prev['self'] === $curr['self'] && $prev['opponent'] <= $curr['opponent']),
                "Records not ordered by self, opponent at index $i"
            );
        }
    }

    // ── getMaxTeamId ────────────────────────────────────────────

    public function testGetMaxTeamIdReturnsIntegerResult(): void
    {
        // Insert a schedule row with a known Visitor team ID
        $this->insertScheduleRow(2099, '2099-03-01', 28, 90, 1, 100);

        $result = $this->repo->getMaxTeamId();

        self::assertGreaterThanOrEqual(28, $result);
    }
}
