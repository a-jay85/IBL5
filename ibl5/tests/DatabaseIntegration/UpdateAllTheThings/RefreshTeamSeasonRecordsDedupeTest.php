<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use FranchiseHistory\FranchiseHistoryRepository;
use League\League;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\Steps\RefreshTeamSeasonRecordsStep;

/**
 * Phantom-repeat dedupe pins for RefreshTeamSeasonRecordsStep.
 *
 * A phantom repeat of a game carries a different game_of_that_day. The league
 * has no doubleheaders, so (game_date, visitor_teamid, home_teamid) identifies a
 * game and the lowest ordinal is canonical.
 *
 * Never call execute() in this file. DatabaseTestCase wraps each test in
 * begin_transaction()/rollback(), and execute() would commit that outer
 * transaction and leak seeded rows into the shared test DB. Every rebuild goes
 * through rebuildWithinCurrentTransaction().
 *
 * The test DB is production-synced and the sibling RefreshMaterializedRecordsStepTest
 * commits deletes of box scores, so every assertion is a delta against a baseline
 * rebuild taken inside the same test. Seed dates are in 2098 so they cannot collide
 * with a real game.
 */
#[Group('database')]
final class RefreshTeamSeasonRecordsDedupeTest extends DatabaseTestCase
{
    private const REGULAR_DATE = '2098-11-05';
    private const REGULAR_DATE_NULL_ORDINAL = '2098-11-07';
    private const HEAT_DATE = '2098-10-15';
    private const REGULAR_YEAR = 2099;
    private const HEAT_YEAR = 2098;

    public function testRegularSeasonPhantomWithHigherOrdinalCountedOnce(): void
    {
        [$t1, $t2] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $t1, $t2);

        $this->rebuild();
        $before1 = $this->record($t1, self::REGULAR_YEAR, 1);
        $before2 = $this->record($t2, self::REGULAR_YEAR, 1);

        $this->seedRealGame(self::REGULAR_DATE, 3, $t1, $t2);
        $this->seedPhantom(self::REGULAR_DATE, 7, $t1, $t2);
        $this->rebuild();

        $after1 = $this->record($t1, self::REGULAR_YEAR, 1);
        $after2 = $this->record($t2, self::REGULAR_YEAR, 1);

        $this->assertSame(0, $after1['wins'] - $before1['wins']);
        $this->assertSame(1, $after1['losses'] - $before1['losses']);
        $this->assertSame(1, $after2['wins'] - $before2['wins']);
        $this->assertSame(0, $after2['losses'] - $before2['losses']);
    }

    public function testHeatPhantomWithHigherOrdinalCountedOnce(): void
    {
        [$t1, $t2] = $this->realTeams(2);
        $this->assertTripleEmpty(self::HEAT_DATE, $t1, $t2);

        $this->rebuild();
        $before1 = $this->record($t1, self::HEAT_YEAR, 3);
        $before2 = $this->record($t2, self::HEAT_YEAR, 3);

        $this->seedRealGame(self::HEAT_DATE, 3, $t1, $t2);
        $this->seedPhantom(self::HEAT_DATE, 7, $t1, $t2);
        $this->rebuild();

        $after1 = $this->record($t1, self::HEAT_YEAR, 3);
        $after2 = $this->record($t2, self::HEAT_YEAR, 3);

        $this->assertSame(0, $after1['wins'] - $before1['wins']);
        $this->assertSame(1, $after1['losses'] - $before1['losses']);
        $this->assertSame(1, $after2['wins'] - $before2['wins']);
        $this->assertSame(0, $after2['losses'] - $before2['losses']);
    }

    public function testLowestOrdinalScoresWinWhenRepeatHasLowerOrdinal(): void
    {
        [$t1, $t2] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $t1, $t2);

        $this->rebuild();
        $before1 = $this->record($t1, self::REGULAR_YEAR, 1);
        $before2 = $this->record($t2, self::REGULAR_YEAR, 1);

        $this->seedPhantom(self::REGULAR_DATE, 2, $t1, $t2);
        $this->seedRealGame(self::REGULAR_DATE, 5, $t1, $t2);
        $this->rebuild();

        $after1 = $this->record($t1, self::REGULAR_YEAR, 1);
        $after2 = $this->record($t2, self::REGULAR_YEAR, 1);

        $this->assertSame(1, $after1['wins'] - $before1['wins']);
        $this->assertSame(0, $after1['losses'] - $before1['losses']);
        $this->assertSame(0, $after2['wins'] - $before2['wins']);
        $this->assertSame(1, $after2['losses'] - $before2['losses']);
    }

    public function testTwoTeamRowsOfOneGameCountedOnce(): void
    {
        [$t1, $t2] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $t1, $t2);

        $this->rebuild();
        $before1 = $this->record($t1, self::REGULAR_YEAR, 1);
        $before2 = $this->record($t2, self::REGULAR_YEAR, 1);

        $this->seedRealGame(self::REGULAR_DATE, 1, $t1, $t2);
        $this->rebuild();

        $after1 = $this->record($t1, self::REGULAR_YEAR, 1);
        $after2 = $this->record($t2, self::REGULAR_YEAR, 1);

        $this->assertSame(0, $after1['wins'] - $before1['wins']);
        $this->assertSame(1, $after1['losses'] - $before1['losses']);
        $this->assertSame(1, $after2['wins'] - $before2['wins']);
        $this->assertSame(0, $after2['losses'] - $before2['losses']);
    }

    public function testDistinctMatchupsSameDayBothCounted(): void
    {
        [$t1, $t2, $t3, $t4] = $this->realTeams(4);
        $this->assertTripleEmpty(self::REGULAR_DATE, $t1, $t2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $t3, $t4);

        $this->rebuild();
        $before = $this->recordsFor([$t1, $t2, $t3, $t4], self::REGULAR_YEAR, 1);

        $this->seedRealGame(self::REGULAR_DATE, 1, $t1, $t2);
        $this->seedRealGame(self::REGULAR_DATE, 2, $t3, $t4);
        $this->rebuild();
        $after = $this->recordsFor([$t1, $t2, $t3, $t4], self::REGULAR_YEAR, 1);

        $this->assertSame(0, $after[$t1]['wins'] - $before[$t1]['wins']);
        $this->assertSame(1, $after[$t1]['losses'] - $before[$t1]['losses']);
        $this->assertSame(1, $after[$t2]['wins'] - $before[$t2]['wins']);
        $this->assertSame(0, $after[$t2]['losses'] - $before[$t2]['losses']);
        $this->assertSame(0, $after[$t3]['wins'] - $before[$t3]['wins']);
        $this->assertSame(1, $after[$t3]['losses'] - $before[$t3]['losses']);
        $this->assertSame(1, $after[$t4]['wins'] - $before[$t4]['wins']);
        $this->assertSame(0, $after[$t4]['losses'] - $before[$t4]['losses']);
    }

    public function testNullOrdinalGameCountedOnce(): void
    {
        [$t1, $t2] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE_NULL_ORDINAL, $t1, $t2);

        $this->rebuild();
        $before1 = $this->record($t1, self::REGULAR_YEAR, 1);
        $before2 = $this->record($t2, self::REGULAR_YEAR, 1);

        $this->seedNullOrdinalGame(self::REGULAR_DATE_NULL_ORDINAL, $t1, $t2);
        $this->rebuild();

        $after1 = $this->record($t1, self::REGULAR_YEAR, 1);
        $after2 = $this->record($t2, self::REGULAR_YEAR, 1);

        $this->assertSame(0, $after1['wins'] - $before1['wins']);
        $this->assertSame(1, $after1['losses'] - $before1['losses']);
        $this->assertSame(1, $after2['wins'] - $before2['wins']);
        $this->assertSame(0, $after2['losses'] - $before2['losses']);
    }

    public function testSeededWinsPlusLossesEqualTwiceDistinctMatchups(): void
    {
        [$t1, $t2, $t3, $t4] = $this->realTeams(4);
        $this->assertTripleEmpty(self::REGULAR_DATE, $t1, $t2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $t3, $t4);

        $this->rebuild();
        $before = $this->recordsFor([$t1, $t2, $t3, $t4], self::REGULAR_YEAR, 1);

        $this->seedRealGame(self::REGULAR_DATE, 1, $t1, $t2);
        $this->seedPhantom(self::REGULAR_DATE, 6, $t1, $t2);
        $this->seedRealGame(self::REGULAR_DATE, 2, $t3, $t4);
        $this->rebuild();
        $after = $this->recordsFor([$t1, $t2, $t3, $t4], self::REGULAR_YEAR, 1);

        $delta = 0;
        foreach ([$t1, $t2, $t3, $t4] as $tid) {
            $delta += ($after[$tid]['wins'] - $before[$tid]['wins'])
                + ($after[$tid]['losses'] - $before[$tid]['losses']);
        }

        // Two distinct (date, visitor, home) triples, two team rows each.
        $this->assertSame(4, $delta);
    }

    public function testRegularSeasonDeltaMatchesFiveSeasonWindowQuery(): void
    {
        [$t1, $t2] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $t1, $t2);

        $names = [$t1 => $this->teamName($t1), $t2 => $this->teamName($t2)];
        $repo = new FranchiseHistoryRepository($this->db);

        $this->rebuild();
        $tableBefore = $this->recordsFor([$t1, $t2], self::REGULAR_YEAR, 1);
        $windowBefore = $this->windowFor($repo, $names);

        $this->seedRealGame(self::REGULAR_DATE, 3, $t1, $t2);
        $this->seedPhantom(self::REGULAR_DATE, 7, $t1, $t2);
        $this->rebuild();
        $tableAfter = $this->recordsFor([$t1, $t2], self::REGULAR_YEAR, 1);
        $windowAfter = $this->windowFor($repo, $names);

        foreach ([$t1, $t2] as $tid) {
            $this->assertSame(
                $windowAfter[$tid]['wins'] - $windowBefore[$tid]['wins'],
                $tableAfter[$tid]['wins'] - $tableBefore[$tid]['wins'],
                "wins delta for team $tid"
            );
            $this->assertSame(
                $windowAfter[$tid]['losses'] - $windowBefore[$tid]['losses'],
                $tableAfter[$tid]['losses'] - $tableBefore[$tid]['losses'],
                "losses delta for team $tid"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return list<int>
     */
    private function realTeams(int $n): array
    {
        $stmt = $this->db->prepare(
            'SELECT teamid FROM `ibl_team_info` WHERE teamid BETWEEN 1 AND ? ORDER BY teamid LIMIT ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare team query: ' . $this->db->error);
        $max = League::MAX_REAL_TEAMID;
        $stmt->bind_param('ii', $max, $n);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);

        $ids = [];
        while (($row = $result->fetch_assoc()) !== null) {
            $id = $row['teamid'];
            self::assertIsInt($id);
            $ids[] = $id;
        }
        $stmt->close();

        self::assertCount($n, $ids, 'Test DB lacks enough real teams');

        return $ids;
    }

    private function teamName(int $teamId): string
    {
        $stmt = $this->db->prepare('SELECT team_name FROM `ibl_team_info` WHERE teamid = ?');
        self::assertNotFalse($stmt, 'Failed to prepare team name query: ' . $this->db->error);
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row);
        $name = $row['team_name'];
        self::assertIsString($name);

        return $name;
    }

    private function assertTripleEmpty(string $date, int $visitor, int $home): void
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams`
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare triple query: ' . $this->db->error);
        $stmt->bind_param('sii', $date, $visitor, $home);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row);
        self::assertSame(0, (int) $row['c'], 'seed triple already has box scores; pick another date');
    }

    private function rebuild(): void
    {
        (new RefreshTeamSeasonRecordsStep($this->db))->rebuildWithinCurrentTransaction();
    }

    /**
     * @return array{wins: int, losses: int}
     */
    private function record(int $teamId, int $year, int $gameType): array
    {
        $stmt = $this->db->prepare(
            'SELECT wins, losses FROM `ibl_team_season_records`
             WHERE team_id = ? AND `year` = ? AND game_type = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare record query: ' . $this->db->error);
        $stmt->bind_param('iii', $teamId, $year, $gameType);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return ['wins' => 0, 'losses' => 0];
        }

        $wins = $row['wins'];
        $losses = $row['losses'];
        self::assertTrue(is_numeric($wins) && is_numeric($losses));

        return ['wins' => (int) $wins, 'losses' => (int) $losses];
    }

    /**
     * @param list<int> $teamIds
     * @return array<int, array{wins: int, losses: int}>
     */
    private function recordsFor(array $teamIds, int $year, int $gameType): array
    {
        $records = [];
        foreach ($teamIds as $tid) {
            $records[$tid] = $this->record($tid, $year, $gameType);
        }

        return $records;
    }

    /**
     * @param array<int, string> $names team id => team_name
     * @return array<int, array{wins: int, losses: int}>
     */
    private function windowFor(FranchiseHistoryRepository $repo, array $names): array
    {
        $byName = [];
        foreach ($repo->getFiveSeasonWindowRows(2099) as $row) {
            $byName[$row['currentname']] = [
                'wins' => (int) $row['five_season_wins'],
                'losses' => (int) $row['five_season_losses'],
            ];
        }

        $out = [];
        foreach ($names as $tid => $name) {
            $out[$tid] = $byName[$name] ?? ['wins' => 0, 'losses' => 0];
        }

        return $out;
    }

    /**
     * A real game: visitor 85, home 104, so the home team wins.
     */
    private function seedRealGame(string $date, int $ordinal, int $visitor, int $home): void
    {
        $this->insertTeamBoxscoreRow($date, 'Visitor', $ordinal, $visitor, $home);
        $this->insertTeamBoxscoreRow($date, 'Home', $ordinal, $visitor, $home);
    }

    /**
     * A phantom repeat of a game with the quarter scores swapped: visitor 104,
     * home 85, so the visitor wins.
     */
    private function seedPhantom(string $date, int $ordinal, int $visitor, int $home): void
    {
        foreach (['Visitor', 'Home'] as $name) {
            $this->insertRow('ibl_box_scores_teams', [
                'game_date' => $date,
                'name' => $name,
                'game_of_that_day' => $ordinal,
                'visitor_teamid' => $visitor,
                'home_teamid' => $home,
                'attendance' => 10000,
                'capacity' => 15000,
                'visitor_wins' => 20,
                'visitor_losses' => 10,
                'home_wins' => 25,
                'home_losses' => 5,
                'visitor_q1_points' => 28,
                'visitor_q2_points' => 24,
                'visitor_q3_points' => 22,
                'visitor_q4_points' => 30,
                'visitor_ot_points' => 0,
                'home_q1_points' => 20,
                'home_q2_points' => 22,
                'home_q3_points' => 18,
                'home_q4_points' => 25,
                'home_ot_points' => 0,
                'game_2gm' => 30,
                'game_2ga' => 60,
                'game_ftm' => 15,
                'game_fta' => 20,
                'game_3gm' => 8,
                'game_3ga' => 22,
                'game_orb' => 10,
                'game_drb' => 30,
                'game_ast' => 20,
                'game_stl' => 8,
                'game_tov' => 12,
                'game_blk' => 5,
                'game_pf' => 18,
            ]);
        }
    }

    /**
     * insertRow binds PHP null as '', which cannot produce a SQL NULL, so the
     * ordinal is nulled with an UPDATE after the insert.
     */
    private function seedNullOrdinalGame(string $date, int $visitor, int $home): void
    {
        $this->seedRealGame($date, 9, $visitor, $home);

        $stmt = $this->db->prepare(
            'UPDATE `ibl_box_scores_teams` SET game_of_that_day = NULL
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare NULL-ordinal update: ' . $this->db->error);
        $stmt->bind_param('sii', $date, $visitor, $home);
        $stmt->execute();
        $stmt->close();
    }
}
