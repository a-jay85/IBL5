<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
use OnThisDay\MomentKind;
use OnThisDay\OnThisDayMomentBuilder;
use OnThisDay\RecordGameTracker;
use PHPUnit\Framework\Attributes\Group;

/**
 * Golden characterization of OnThisDayMomentBuilder::collect() against a
 * test-local copy of the SQL it ran before the guarded games were built once.
 *
 * Today's row order for the player-game and trade lists comes from implicit
 * sorts (the baseline dump on the full DB was sorted by neither date nor pid),
 * so those three lists are compared sorted by moment key. The record list comes
 * from an ORDER BY stream, so it is compared in order.
 */
#[Group('database')]
final class OnThisDayGuardedGamesCharacterizationTest extends DatabaseTestCase
{
    private const PID_EARLY = 200_930_001;
    private const PID_PLAYOFF50 = 200_930_002;
    private const PID_TD_FEB = 200_930_003;
    private const PID_TRADE_LATE = 200_930_004;
    private const PID_SHORT = 200_930_005;
    private const PID_49 = 200_930_006;
    private const PID_TRADE_EARLY = 200_930_007;
    private const PID_TD_OCT = 200_930_008;
    private const PID_PHANTOM = 200_930_009;
    private const PID_RECORD = 200_930_010;

    /** Test-local copy of the phantom-guarded aggregate as it stood before the temp tables. */
    private const REF_GUARDED = "SELECT b.game_date, b.visitor_teamid, b.home_teamid, b.pid,
                  MAX(b.teamid) AS teamid, MAX(b.calc_points) AS pts, MAX(b.calc_rebounds) AS reb,
                  MAX(b.game_ast) AS ast, MAX(b.game_stl) AS stl, MAX(b.game_blk) AS blk,
                  MAX(b.season_year) AS season_year, MAX(b.game_type) AS game_type
                FROM `ibl_box_scores` b
                WHERE b.pid > 0
                  AND b.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                  AND b.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.pid";

    private const REF_PLAYER_GAMES_HEAD = "SELECT g.*, p.name AS player_name,
                    CASE WHEN g.teamid = g.visitor_teamid THEN g.home_teamid ELSE g.visitor_teamid END AS opponent_teamid
                  FROM (" . self::REF_GUARDED . ") g
                  JOIN `ibl_plr` p ON p.pid = g.pid
                  WHERE ";

    private const REF_FIFTY_PREDICATE = 'g.game_type IN (1, 2, 3) AND g.pts >= 50';

    private const REF_TRIPLE_PREDICATE = 'g.game_type IN (1, 2, 3)'
        . ' AND ((g.pts >= 10) + (g.reb >= 10) + (g.ast >= 10) + (g.stl >= 10) + (g.blk >= 10)) >= 3';

    private const REF_RECORD_STREAM = "SELECT g.game_date, g.visitor_teamid, g.home_teamid, g.pid, g.teamid,
            g.season_year, g.pts, g.reb, g.ast, g.stl, g.blk
          FROM (" . self::REF_GUARDED . ") g
          WHERE g.game_type = 1
          ORDER BY g.game_date, g.visitor_teamid, g.home_teamid, g.pid";

    private const REF_FIRST_SEASON = 'SELECT MIN(season_year) AS first_season FROM `ibl_box_scores` WHERE game_type = 1';

    private const REF_TRADES = "WITH games AS (
                    SELECT g.pid, g.season_year, g.game_date, g.teamid, g.pts,
                      LAG(g.teamid) OVER (PARTITION BY g.pid, g.season_year ORDER BY g.game_date) AS prev_teamid
                    FROM (" . self::REF_GUARDED . ") g
                    WHERE g.game_type = 1
                  ),
                  moves AS (
                    SELECT pid, season_year, game_date AS debut_date, prev_teamid AS from_tid, teamid AS to_tid
                    FROM games
                    WHERE prev_teamid IS NOT NULL AND prev_teamid <> teamid
                  ),
                  movers AS (
                    SELECT m.pid, m.season_year, m.debut_date, m.from_tid, m.to_tid,
                      AVG(old.pts) AS old_ppg, COUNT(*) AS old_games
                    FROM moves m
                    JOIN games old ON old.pid = m.pid
                      AND old.season_year = m.season_year
                      AND old.teamid = m.from_tid
                      AND old.game_date < m.debut_date
                    GROUP BY m.pid, m.season_year, m.debut_date, m.from_tid, m.to_tid
                    HAVING AVG(old.pts) >= ? AND COUNT(*) >= ?
                  ),
                  ranked AS (
                    SELECT mv.*,
                      COUNT(*) OVER (PARTITION BY mv.season_year, mv.from_tid, mv.to_tid, mv.debut_date) AS players_moved,
                      ROW_NUMBER() OVER (
                        PARTITION BY mv.season_year, mv.from_tid, mv.to_tid, mv.debut_date
                        ORDER BY mv.old_ppg DESC, mv.pid ASC
                      ) AS headline_rank
                    FROM movers mv
                  )
                  SELECT r.pid, r.season_year, r.debut_date, r.from_tid, r.to_tid, r.players_moved
                  FROM ranked r
                  JOIN `ibl_plr` p ON p.pid = r.pid
                  WHERE r.headline_rank = 1";

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            self::PID_EARLY, self::PID_PLAYOFF50, self::PID_TD_FEB, self::PID_TRADE_LATE, self::PID_SHORT,
            self::PID_49, self::PID_TRADE_EARLY, self::PID_TD_OCT, self::PID_PHANTOM, self::PID_RECORD,
        ] as $pid) {
            $this->insertTestPlayer($pid, 'Golden ' . $pid);
        }

        // The first box-score season is 1990, so the later fixtures can set records.
        $this->game('1989-11-15', self::PID_EARLY, 17, 18);
        // June dates generate game_type 2 (playoffs): exactly 50 points.
        $this->game('2029-06-05', self::PID_PLAYOFF50, 21, 22, points2m: 19, ftm: 3, points3m: 3);
        $this->game('2029-02-01', self::PID_TD_FEB, 23, 24, ast: 12, stl: 10);
        $this->game('2029-01-20', self::PID_49, 25, 26, points2m: 19, ftm: 2, points3m: 3);
        // October dates generate game_type 3 (preseason).
        $this->game('2028-10-20', self::PID_TD_OCT, 27, 28, ast: 12, stl: 10);
        // A phantom duplicate of one real game: same pid, date and teams, a second ordinal.
        $this->game('2029-01-10', self::PID_PHANTOM, 7, 8, points2m: 20, ftm: 3, points3m: 3);
        $this->game('2029-01-10', self::PID_PHANTOM, 7, 8, points2m: 20, ftm: 3, points3m: 3, overrides: ['game_of_that_day' => 2]);
        $this->game('2029-03-20', self::PID_RECORD, 19, 20, blk: 250);

        foreach (['2029-11-02', '2029-11-03', '2029-11-04', '2029-11-05', '2029-11-06'] as $date) {
            $this->game($date, self::PID_TRADE_LATE, 9, 10);
        }
        $this->game('2029-11-08', self::PID_TRADE_LATE, 11, 12);

        foreach (['2029-01-12', '2029-01-13', '2029-01-14', '2029-01-15'] as $date) {
            $this->game($date, self::PID_SHORT, 13, 14);
        }
        $this->game('2029-01-17', self::PID_SHORT, 15, 16);

        foreach (['2029-01-02', '2029-01-03', '2029-01-04', '2029-01-05', '2029-01-06'] as $date) {
            $this->game($date, self::PID_TRADE_EARLY, 3, 4);
        }
        $this->game('2029-01-08', self::PID_TRADE_EARLY, 5, 6);
    }

    public function testFiftyPointGamesMatchTodaysQueryInOrder(): void
    {
        $expected = $this->sortedByKey($this->referencePlayerGames(MomentKind::FiftyPointGame, self::REF_FIFTY_PREDICATE));

        $this->assertNotSame([], $expected);
        $this->assertSame($expected, $this->sortedByKey($this->actual(MomentKind::FiftyPointGame)));
    }

    public function testTripleDoublesMatchTodaysQueryInOrder(): void
    {
        $expected = $this->sortedByKey($this->referencePlayerGames(MomentKind::TripleDouble, self::REF_TRIPLE_PREDICATE));

        $this->assertNotSame([], $expected);
        $this->assertSame($expected, $this->sortedByKey($this->actual(MomentKind::TripleDouble)));
    }

    public function testRecordGamesMatchTodaysStreamInOrder(): void
    {
        $expected = $this->referenceRecordGames();

        $this->assertNotSame([], $expected);
        $this->assertSame($expected, $this->actual(MomentKind::RecordGame));
    }

    public function testTradesMatchTodaysQueryInOrder(): void
    {
        $expected = $this->sortedByKey($this->referenceTrades());

        $this->assertNotSame([], $expected);
        $this->assertSame($expected, $this->sortedByKey($this->actual(MomentKind::Trade)));
    }

    public function testPhantomDuplicateYieldsOneFiftyPointMoment(): void
    {
        $keys = $this->keys(MomentKind::FiftyPointGame);

        $this->assertSame(
            1,
            count(array_filter($keys, static fn (string $key): bool => $key === MomentKind::FiftyPointGame->value . ':2029-01-10:' . self::PID_PHANTOM)),
        );
    }

    public function testPlayoffFiftyPointGameCountsAndFortyNineDoesNot(): void
    {
        $keys = $this->keys(MomentKind::FiftyPointGame);

        $this->assertContains(MomentKind::FiftyPointGame->value . ':2029-06-05:' . self::PID_PLAYOFF50, $keys);
        $this->assertNotContains(MomentKind::FiftyPointGame->value . ':2029-01-20:' . self::PID_49, $keys);
    }

    public function testOctoberTripleDoubleCounts(): void
    {
        $this->assertContains(MomentKind::TripleDouble->value . ':2028-10-20:' . self::PID_TD_OCT, $this->keys(MomentKind::TripleDouble));
    }

    public function testBlocksRecordGameCounts(): void
    {
        $this->assertContains(MomentKind::RecordGame->value . ':2029-03-20:' . self::PID_RECORD . ':blocks', $this->keys(MomentKind::RecordGame));
    }

    public function testTradeNeedsFiveOldTeamGames(): void
    {
        $keys = $this->keys(MomentKind::Trade);

        $this->assertContains(MomentKind::Trade->value . ':2029-01-08:3-5', $keys);
        $this->assertNotContains(MomentKind::Trade->value . ':2029-01-17:13-15', $keys);
    }

    /**
     * @param array<string, int|string> $overrides
     */
    private function game(
        string $date,
        int $pid,
        int $visitorTid,
        int $homeTid,
        int $points2m = 5,
        int $ftm = 4,
        int $points3m = 2,
        int $ast = 5,
        int $stl = 2,
        int $blk = 1,
        array $overrides = [],
    ): void {
        $this->insertPlayerBoxscoreRow(
            $date,
            $pid,
            'Golden',
            'PG',
            $visitorTid,
            $homeTid,
            $visitorTid,
            points2m: $points2m,
            ftm: $ftm,
            points3m: $points3m,
            ast: $ast,
            stl: $stl,
            blk: $blk,
            overrides: $overrides,
        );
    }

    /**
     * @return list<array{string, int, int, int, string}>
     */
    private function actual(MomentKind $kind): array
    {
        $shapes = [];
        foreach ((new OnThisDayMomentBuilder($this->db))->collect() as $moment) {
            if ($moment->kind === $kind) {
                $shapes[] = [
                    $moment->key,
                    $moment->seasonYear,
                    $moment->teamId,
                    $moment->opponentTeamId,
                    json_encode($moment->facts, JSON_THROW_ON_ERROR),
                ];
            }
        }

        return $shapes;
    }

    /**
     * @return list<string>
     */
    private function keys(MomentKind $kind): array
    {
        return array_map(static fn (array $shape): string => $shape[0], $this->actual($kind));
    }

    /**
     * @param list<array{string, int, int, int, string}> $shapes
     * @return list<array{string, int, int, int, string}>
     */
    private function sortedByKey(array $shapes): array
    {
        usort($shapes, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $shapes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql): array
    {
        $result = $this->db->query($sql);
        $this->assertInstanceOf(\mysqli_result::class, $result);

        return array_values($result->fetch_all(MYSQLI_ASSOC));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function num(array $row, string $column): int
    {
        $value = $row[$column] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $column): string
    {
        $value = $row[$column] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<array{string, int, int, int, string}>
     */
    private function referencePlayerGames(MomentKind $kind, string $predicate): array
    {
        $shapes = [];
        foreach ($this->rows(self::REF_PLAYER_GAMES_HEAD . $predicate) as $row) {
            $shapes[] = [
                $kind->value . ':' . $this->text($row, 'game_date') . ':' . $this->num($row, 'pid'),
                $this->num($row, 'season_year'),
                $this->num($row, 'teamid'),
                $this->num($row, 'opponent_teamid'),
                json_encode([
                    'points' => $this->num($row, 'pts'),
                    'rebounds' => $this->num($row, 'reb'),
                    'assists' => $this->num($row, 'ast'),
                    'steals' => $this->num($row, 'stl'),
                    'blocks' => $this->num($row, 'blk'),
                ], JSON_THROW_ON_ERROR),
            ];
        }

        return $shapes;
    }

    /**
     * @return list<array{string, int, int, int, string}>
     */
    private function referenceRecordGames(): array
    {
        $first = $this->rows(self::REF_FIRST_SEASON)[0]['first_season'] ?? null;
        if (!is_numeric($first)) {
            return [];
        }

        $known = [];
        foreach ($this->rows('SELECT pid FROM `ibl_plr`') as $row) {
            $known[$this->num($row, 'pid')] = true;
        }

        $tracker = new RecordGameTracker((int) $first);
        $shapes = [];
        foreach ($this->rows(self::REF_RECORD_STREAM) as $row) {
            $stats = [
                'points' => $this->num($row, 'pts'),
                'rebounds' => $this->num($row, 'reb'),
                'assists' => $this->num($row, 'ast'),
                'steals' => $this->num($row, 'stl'),
                'blocks' => $this->num($row, 'blk'),
            ];
            $labels = $tracker->observe($this->num($row, 'season_year'), $stats);
            $pid = $this->num($row, 'pid');
            if ($labels === [] || !isset($known[$pid])) {
                continue;
            }

            $teamId = $this->num($row, 'teamid');
            $opponent = $teamId === $this->num($row, 'visitor_teamid')
                ? $this->num($row, 'home_teamid')
                : $this->num($row, 'visitor_teamid');
            foreach ($labels as $label) {
                $shapes[] = [
                    MomentKind::RecordGame->value . ':' . $this->text($row, 'game_date') . ':' . $pid . ':' . $label,
                    $this->num($row, 'season_year'),
                    $teamId,
                    $opponent,
                    json_encode(['value' => $stats[$label]], JSON_THROW_ON_ERROR),
                ];
            }
        }

        return $shapes;
    }

    /**
     * @return list<array{string, int, int, int, string}>
     */
    private function referenceTrades(): array
    {
        $stmt = $this->db->prepare(self::REF_TRADES);
        $this->assertInstanceOf(\mysqli_stmt::class, $stmt);
        $ppg = 15;
        $games = 5;
        $stmt->bind_param('ii', $ppg, $games);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->assertInstanceOf(\mysqli_result::class, $result);

        $shapes = [];
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $from = $this->num($row, 'from_tid');
            $to = $this->num($row, 'to_tid');
            $shapes[] = [
                MomentKind::Trade->value . ':' . $this->text($row, 'debut_date') . ':' . $from . '-' . $to,
                $this->num($row, 'season_year'),
                $to,
                $from,
                json_encode(['players_moved' => $this->num($row, 'players_moved'), 'mover_pid' => $this->num($row, 'pid')], JSON_THROW_ON_ERROR),
            ];
        }
        $stmt->close();

        return $shapes;
    }
}
