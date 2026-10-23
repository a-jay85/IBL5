<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\Steps\RefreshPlayoffSeriesResultsStep;

/**
 * Regression pin for the per-team-side deduplication in
 * RefreshPlayoffSeriesResultsStep::SELECT_SQL.
 *
 * ibl_box_scores_teams stores ONE ROW PER TEAM SIDE — two rows per game, split
 * by the `name` column (uq_game_team spans game_date, visitor_teamid,
 * home_teamid, game_of_that_day, name). The step's playoff_games CTE therefore
 * has to collapse the two team-side rows of each game, and it keeps only the lowest game_of_that_day per date/visitor/home triple so a phantom repeat counts once. PR #1906 removed that GROUP BY
 * and every series doubled (a 4-1 became an 8-2); the existing mock-DB unit
 * tests never execute the SQL, and the existing DB-integration test seeds only
 * ONE row per game, so neither could observe it.
 *
 * These tests seed BOTH team-side rows, matching production shape, and assert
 * exact game counts.
 *
 * Fixtures live in June 2099 so they cannot collide with production data or with
 * the June 2025 fixtures in RefreshMaterializedRecordsStepTest. The step calls
 * begin_transaction(), which commits DatabaseTestCase's outer transaction, so
 * the fixtures are cleaned up explicitly rather than rolled back.
 * The phantom and NULL-ordinal tests call rebuildWithinCurrentTransaction() instead, so their writes roll back with the outer transaction.
 */
#[Group('database')]
class RefreshPlayoffSeriesResultsDedupTest extends DatabaseTestCase
{
    private const int FIXTURE_YEAR = 2099;

    private int $tidA;

    private int $tidB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tidA = $this->firstRealTeamId(0);
        $this->tidB = $this->firstRealTeamId(1);

        $this->purgeFixtures();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            try {
                $this->purgeFixtures();
            } catch (\Throwable) {
                // Connection may already be closed; parent handles teardown.
            }
        }

        parent::tearDown();
    }

    public function testSeriesCountsEachGameOnceDespiteTwoRowsPerGame(): void
    {
        $this->seedFourOneSeries();

        $result = (new RefreshPlayoffSeriesResultsStep($this->db))->execute();
        self::assertTrue($result->success, 'Step must succeed: ' . $result->detail);

        $row = $this->fixtureSeriesRow();
        self::assertNotNull($row, 'Expected one series row for the fixture year');

        self::assertSame($this->tidA, (int) $row['winner_tid'], 'Team A won 4 of 5 games');
        self::assertSame(4, (int) $row['winner_games'], 'Five distinct games, four won by A');
        self::assertSame(1, (int) $row['loser_games'], 'Five distinct games, one won by B');
        self::assertSame(5, (int) $row['total_games'], 'Ten source rows describe five games');
    }

    public function testExactlyOneSeriesRowIsProducedForTheFixturePair(): void
    {
        $this->seedFourOneSeries();

        (new RefreshPlayoffSeriesResultsStep($this->db))->execute();

        $result = $this->db->query(
            'SELECT COUNT(*) AS cnt FROM ibl_playoff_series_results WHERE `year` = ' . self::FIXTURE_YEAR
        );
        $row = $result === false ? null : $result->fetch_assoc();

        self::assertSame(1, (int) ($row['cnt'] ?? -1), 'One matchup must yield exactly one series row');
    }

    /**
     * Invariant across every row the step writes: total_games must equal the
     * number of DISTINCT games in the source for that year and matchup. Written
     * generically so it holds for any source rows present, though in-suite it
     * sees only these fixtures — RefreshMaterializedRecordsStepTest sorts first
     * and its boundary tests permanently clear ibl_box_scores_teams.
     */
    public function testTotalGamesMatchesDistinctSourceGamesForEverySeries(): void
    {
        $this->seedFourOneSeries();

        (new RefreshPlayoffSeriesResultsStep($this->db))->execute();

        $mismatches = $this->db->query(<<<'SQL'
            SELECT r.`year`, r.winner_tid, r.loser_tid, r.total_games, src.distinct_games
            FROM ibl_playoff_series_results r
            JOIN (
                SELECT YEAR(game_date) AS `year`,
                    LEAST(visitor_teamid, home_teamid) AS team_a,
                    GREATEST(visitor_teamid, home_teamid) AS team_b,
                    COUNT(DISTINCT CONCAT_WS('|', game_date, visitor_teamid, home_teamid))
                        AS distinct_games
                FROM ibl_box_scores_teams
                WHERE game_type = 2
                GROUP BY YEAR(game_date),
                    LEAST(visitor_teamid, home_teamid),
                    GREATEST(visitor_teamid, home_teamid)
            ) src
                ON src.`year` = r.`year`
                AND src.team_a = LEAST(r.winner_tid, r.loser_tid)
                AND src.team_b = GREATEST(r.winner_tid, r.loser_tid)
            WHERE r.total_games <> src.distinct_games
            SQL);

        $rows = $mismatches === false ? [] : $mismatches->fetch_all(MYSQLI_ASSOC);

        self::assertSame(
            [],
            $rows,
            'Every series total_games must equal its distinct source game count; '
            . 'a mismatch means the per-team-side dedup is gone.',
        );
    }

    public function testPhantomRepeatWithHigherOrdinalIsCountedOnce(): void
    {
        $this->seedFourOneSeries();
        $this->seedPhantom('2099-06-01', 2, $this->tidB, $this->tidA);

        (new RefreshPlayoffSeriesResultsStep($this->db))->rebuildWithinCurrentTransaction();

        $row = $this->fixtureSeriesRow();
        self::assertNotNull($row, 'Expected one series row for the fixture year');

        self::assertSame($this->tidA, (int) $row['winner_tid'], 'Lowest ordinal is canonical, so A still wins');
        self::assertSame(4, (int) $row['winner_games'], 'The swapped-score phantom must not hand B a win');
        self::assertSame(1, (int) $row['loser_games'], 'Only the canonical copy of game 1 counts');
        self::assertSame(5, (int) $row['total_games'], 'A phantom repeat is not a sixth game');
    }

    public function testGameWithNullOrdinalIsStillCounted(): void
    {
        $this->seedFourOneSeries();
        $this->nullOrdinal('2099-06-03', 1);

        (new RefreshPlayoffSeriesResultsStep($this->db))->rebuildWithinCurrentTransaction();

        $row = $this->fixtureSeriesRow();
        self::assertNotNull($row, 'Expected one series row for the fixture year');

        self::assertSame(4, (int) $row['winner_games'], 'A NULL-ordinal game still counts');
        self::assertSame(1, (int) $row['loser_games']);
        self::assertSame(5, (int) $row['total_games'], 'The NULL-safe join keeps game 2');
    }

    public function testNullOrdinalPhantomBesideCanonicalGameIsIgnored(): void
    {
        $this->seedFourOneSeries();
        $this->seedPhantom('2099-06-01', 2, $this->tidB, $this->tidA);
        $this->nullOrdinal('2099-06-01', 2);

        (new RefreshPlayoffSeriesResultsStep($this->db))->rebuildWithinCurrentTransaction();

        $row = $this->fixtureSeriesRow();
        self::assertNotNull($row, 'Expected one series row for the fixture year');

        self::assertSame(4, (int) $row['winner_games'], 'The NULL-ordinal phantom must not count');
        self::assertSame(1, (int) $row['loser_games']);
        self::assertSame(5, (int) $row['total_games'], 'MIN ignores NULL, so the ordinal-1 copy is canonical');
    }

    /**
     * Five playoff games between tidA and tidB, each written as TWO rows — one
     * per team side, distinguished by `name`, exactly as the importer writes
     * them. insertTeamBoxscoreRow's fixed scores always favour the HOME side, so
     * swapping the sides on game 5 hands tidB its single win.
     */
    private function seedFourOneSeries(): void
    {
        $games = [
            ['2099-06-01', $this->tidB, $this->tidA],
            ['2099-06-03', $this->tidB, $this->tidA],
            ['2099-06-05', $this->tidB, $this->tidA],
            ['2099-06-07', $this->tidB, $this->tidA],
            ['2099-06-09', $this->tidA, $this->tidB],
        ];

        foreach ($games as [$date, $visitorTid, $homeTid]) {
            $this->insertTeamBoxscoreRow($date, 'DedupVisitor', 1, $visitorTid, $homeTid);
            $this->insertTeamBoxscoreRow($date, 'DedupHome', 1, $visitorTid, $homeTid);
        }
    }

    /** @return array<string, string|null>|null */
    private function fixtureSeriesRow(): ?array
    {
        $result = $this->db->query(
            'SELECT winner_tid, loser_tid, winner_games, loser_games, total_games '
            . 'FROM ibl_playoff_series_results WHERE `year` = ' . self::FIXTURE_YEAR
        );

        if ($result === false) {
            return null;
        }

        return $result->fetch_assoc();
    }

    private function firstRealTeamId(int $offset): int
    {
        $result = $this->db->query(
            'SELECT teamid FROM ibl_team_info WHERE teamid > 0 ORDER BY teamid LIMIT 1 OFFSET ' . $offset
        );
        $row = $result === false ? null : $result->fetch_assoc();

        self::assertNotNull($row, 'Test DB must contain at least two real teams');

        return (int) $row['teamid'];
    }

    /**
     * A phantom repeat of a game with the scores swapped, so the visitor wins.
     */
    private function seedPhantom(string $date, int $ordinal, int $visitorTid, int $homeTid): void
    {
        foreach (['DedupVisitor', 'DedupHome'] as $name) {
            $this->insertRow('ibl_box_scores_teams', [
                'game_date' => $date,
                'name' => $name,
                'game_of_that_day' => $ordinal,
                'visitor_teamid' => $visitorTid,
                'home_teamid' => $homeTid,
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
     * ordinal is nulled with an UPDATE after the insert. All fixture dates fall
     * in June 2099, so the date plus ordinal targets fixture rows only.
     */
    private function nullOrdinal(string $date, int $ordinal): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `ibl_box_scores_teams` SET game_of_that_day = NULL
             WHERE game_date = ? AND game_of_that_day = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare NULL-ordinal update: ' . $this->db->error);
        $stmt->bind_param('si', $date, $ordinal);
        $stmt->execute();
        $stmt->close();
    }

    private function purgeFixtures(): void
    {
        $this->db->query('DELETE FROM ibl_box_scores_teams WHERE YEAR(game_date) = ' . self::FIXTURE_YEAR);
        $this->db->query('DELETE FROM ibl_playoff_series_results WHERE `year` = ' . self::FIXTURE_YEAR);
    }
}
