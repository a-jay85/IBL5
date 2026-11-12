<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
use PHPUnit\Framework\Attributes\Group;
use RecordHolders\RecordHoldersRepository;
use Updater\Steps\RefreshTeamSeasonRecordsStep;

/**
 * Pins RecordHoldersRepository::getBestWorstSeasonRecord() against the frozen
 * pre-change query over the ibl_team_win_loss view.
 *
 * The seed hits every ORDER BY tiebreaker (pct, wins, year, teamid), so equality
 * with the frozen query proves the table-backed query keeps the same order.
 *
 * Never run DDL or ANALYZE TABLE in this class: both commit implicitly in MariaDB
 * and would leak the seed out of the rolled-back transaction.
 */
#[Group('database')]
final class BestWorstSeasonRecordEquivalenceTest extends DatabaseTestCase
{
    private const FROZEN_BEST_SQL = "SELECT
            twl.currentname AS team_name, twl.year, twl.wins, twl.losses
        FROM `ibl_team_win_loss` twl
        JOIN `ibl_team_info` ti ON ti.team_name = twl.currentname
        WHERE ti.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            AND (twl.wins + twl.losses) > 0
        ORDER BY (twl.wins / (twl.wins + twl.losses)) DESC,
            twl.wins DESC, twl.year ASC, ti.teamid ASC
        LIMIT 5";

    private const FROZEN_WORST_SQL = "SELECT
            twl.currentname AS team_name, twl.year, twl.wins, twl.losses
        FROM `ibl_team_win_loss` twl
        JOIN `ibl_team_info` ti ON ti.team_name = twl.currentname
        WHERE ti.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            AND (twl.wins + twl.losses) > 0
        ORDER BY (twl.wins / (twl.wins + twl.losses)) ASC,
            twl.wins ASC, twl.year ASC, ti.teamid ASC
        LIMIT 5";

    /** @var list<array{0: string, 1: int, 2: int, 3: int, 4: int}> */
    private const SEED_GAMES = [
        ['2013-11-05', 1, 1, 4, 0],
        ['2013-11-06', 1, 1, 4, 0],
        ['2013-11-05', 2, 2, 5, 0],
        ['2013-11-06', 2, 2, 5, 0],
        ['2013-11-05', 3, 3, 6, 0],
        ['2012-11-05', 1, 1, 4, 0],
        ['2012-11-06', 1, 1, 4, 0],
    ];

    public function testBestSeasonRecordsMatchFrozenView(): void
    {
        $names = $this->seedTieSeasons();

        $frozen = $this->normalize($this->runFrozen(self::FROZEN_BEST_SQL));
        self::assertSame($this->expectedBest($names), $frozen);

        $repository = new RecordHoldersRepository($this->db);
        self::assertSame($frozen, $this->normalize($repository->getBestWorstSeasonRecord('DESC')));
    }

    public function testWorstSeasonRecordsMatchFrozenView(): void
    {
        $names = $this->seedTieSeasons();

        $frozen = $this->normalize($this->runFrozen(self::FROZEN_WORST_SQL));
        self::assertSame($this->expectedWorst($names), $frozen);

        $repository = new RecordHoldersRepository($this->db);
        self::assertSame($frozen, $this->normalize($repository->getBestWorstSeasonRecord('ASC')));
    }

    public function testHeatSeasonRowExcludedFromBest(): void
    {
        $names = $this->seedTieSeasons();
        $this->insertTeamSeasonRecordRow(1, 2012, 3, $names[1], $names[1], 5, 0);

        $repository = new RecordHoldersRepository($this->db);

        self::assertSame(
            $this->expectedBest($names),
            $this->normalize($repository->getBestWorstSeasonRecord('DESC'))
        );
    }

    public function testZeroGameSeasonRowExcludedFromWorst(): void
    {
        $names = $this->seedTieSeasons();
        $this->insertTeamSeasonRecordRow(7, 2012, 1, $names[7], $names[7], 0, 0);

        $repository = new RecordHoldersRepository($this->db);

        self::assertSame(
            $this->expectedWorst($names),
            $this->normalize($repository->getBestWorstSeasonRecord('ASC'))
        );
    }

    /**
     * Clears box scores, seeds the tie games, and rebuilds ibl_team_season_records.
     *
     * @return array<int, string> teamid => team_name for teamids 1..7
     */
    private function seedTieSeasons(): array
    {
        self::assertTrue(
            $this->db->query('DELETE FROM ibl_box_scores_teams'),
            'clear box scores failed: ' . $this->db->error
        );

        $result = $this->db->query(
            'SELECT teamid, team_name FROM ibl_team_info WHERE teamid BETWEEN 1 AND 7 ORDER BY teamid'
        );
        self::assertNotFalse($result);
        $names = [];
        while ($row = $result->fetch_assoc()) {
            $names[(int) self::stringOf($row['teamid'])] = self::stringOf($row['team_name']);
        }
        self::assertCount(7, $names);

        foreach (self::SEED_GAMES as [$date, $gameOfDay, $visitor, $home]) {
            $this->insertScoredTeamBoxscoreRow($date, $gameOfDay, $visitor, $home, 60, 50, 'Visitor');
            $this->insertScoredTeamBoxscoreRow($date, $gameOfDay, $visitor, $home, 60, 50, 'Home');
        }

        (new RefreshTeamSeasonRecordsStep($this->db))->rebuildWithinCurrentTransaction();

        return $names;
    }

    /**
     * @param array<int, string> $names
     * @return list<array{team_name: string, year: string, wins: string, losses: string}>
     */
    private function expectedBest(array $names): array
    {
        return [
            $this->expectedRow($names[1], 2013, 2, 0),
            $this->expectedRow($names[1], 2014, 2, 0),
            $this->expectedRow($names[2], 2014, 2, 0),
            $this->expectedRow($names[3], 2014, 1, 0),
            $this->expectedRow($names[4], 2013, 0, 2),
        ];
    }

    /**
     * @param array<int, string> $names
     * @return list<array{team_name: string, year: string, wins: string, losses: string}>
     */
    private function expectedWorst(array $names): array
    {
        return [
            $this->expectedRow($names[4], 2013, 0, 2),
            $this->expectedRow($names[4], 2014, 0, 2),
            $this->expectedRow($names[5], 2014, 0, 2),
            $this->expectedRow($names[6], 2014, 0, 1),
            $this->expectedRow($names[3], 2014, 1, 0),
        ];
    }

    /**
     * @return array{team_name: string, year: string, wins: string, losses: string}
     */
    private function expectedRow(string $teamName, int $year, int $wins, int $losses): array
    {
        return [
            'team_name' => $teamName,
            'year' => (string) $year,
            'wins' => (string) $wins,
            'losses' => (string) $losses,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runFrozen(string $sql): array
    {
        $result = $this->db->query($sql);
        self::assertNotFalse($result, 'frozen query failed: ' . $this->db->error);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Order is the property under test, so rows are never sorted.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return list<array{team_name: string, year: string, wins: string, losses: string}>
     */
    private function normalize(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = [
                'team_name' => self::stringOf($row['team_name'] ?? ''),
                'year' => self::stringOf($row['year'] ?? ''),
                'wins' => self::stringOf($row['wins'] ?? ''),
                'losses' => self::stringOf($row['losses'] ?? ''),
            ];
        }

        return $normalized;
    }

    private static function stringOf(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
