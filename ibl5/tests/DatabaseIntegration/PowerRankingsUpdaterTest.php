<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use Season\Season;
use Updater\PowerRankingsUpdater;

/**
 * Pins deterministic same-date tie ordering of the two raw schedule fetches in
 * PowerRankingsUpdater. Both methods are private; ReflectionMethod exercises the
 * ORDER BY directly instead of through the full update() run.
 */
#[Group('database')]
final class PowerRankingsUpdaterTest extends DatabaseTestCase
{
    private PowerRankingsUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();

        // Season\Season is class-aliased to the mock in PHPUnit runs; the years are set explicitly.
        $season = new Season($this->db);
        $season->beginningYear = 2098;
        $season->endingYear = 2099;

        $this->updater = new PowerRankingsUpdater($this->db, $season);

        // Clear the query window inside the transaction; tearDown rolls this back.
        $this->db->query("DELETE FROM ibl_schedule WHERE game_date BETWEEN '2098-11-01' AND '2099-05-30'");
    }

    public function testFetchAllPlayedGamesBreaksSameDateTiesByScheduleIdAscending(): void
    {
        $this->insertScheduleRow(2099, '2099-01-15', 5, 101, 2, 90);
        $this->insertScheduleRow(2099, '2099-01-15', 3, 101, 4, 90);
        $this->insertScheduleRow(2099, '2099-01-15', 1, 101, 6, 90);
        $this->insertScheduleRow(2099, '2099-01-14', 7, 101, 8, 90);
        // Unplayed (0/0) row: the score filter must exclude it.
        $this->insertScheduleRow(2099, '2099-01-16', 9, 0, 10, 0);

        $rows = $this->invokeFetch('fetchAllPlayedGames');

        // Date is primary; the three same-date games follow schedule id.
        self::assertSame([7, 5, 3, 1], array_column($rows, 'visitor_teamid'));
    }

    public function testFetchAllUnplayedGamesBreaksSameDateTiesByScheduleIdAscending(): void
    {
        $this->insertScheduleRow(2099, '2099-01-15', 5, 0, 2, 0);
        $this->insertScheduleRow(2099, '2099-01-15', 3, 0, 4, 0);
        $this->insertScheduleRow(2099, '2099-01-15', 1, 0, 6, 0);
        $this->insertScheduleRow(2099, '2099-01-14', 7, 0, 8, 0);

        $rows = $this->invokeFetch('fetchAllUnplayedGames');

        self::assertSame([7, 5, 3, 1], array_column($rows, 'visitor_teamid'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invokeFetch(string $method): array
    {
        /** @var list<array<string, mixed>> */
        return (new \ReflectionMethod($this->updater, $method))->invoke($this->updater, 11);
    }
}
