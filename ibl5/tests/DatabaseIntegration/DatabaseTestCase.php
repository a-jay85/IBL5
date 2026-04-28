<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Base class for database integration tests against real MariaDB.
 *
 * - Connects via env vars: DB_HOST, DB_USER, DB_PASS, DB_NAME
 * - Sets MYSQLI_OPT_INT_AND_FLOAT_NATIVE to match production
 * - Wraps each test in begin_transaction() / rollback() for isolation
 * - Excluded from default PHPUnit suite via #[Group('database')]
 */
#[Group('database')]
abstract class DatabaseTestCase extends TestCase
{
    protected \mysqli $db;

    protected function setUp(): void
    {
        parent::setUp();

        $host = $this->requireEnv('DB_HOST');
        $user = $this->requireEnv('DB_USER');
        $pass = $this->requireEnv('DB_PASS');
        $name = $this->requireEnv('DB_NAME');

        $this->db = new \mysqli();
        $this->db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        $this->db->real_connect($host, $user, $pass, $name);

        if ($this->db->connect_errno !== 0) {
            $this->fail('Database connection failed: ' . $this->db->connect_error);
        }

        $this->db->begin_transaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            try {
                $this->db->rollback();
                $this->db->close();
            } catch (\Throwable) {
                // Connection may already be closed or in an unrecoverable state
            }
        }

        parent::tearDown();
    }

    /**
     * Insert a row into a table and return the last insert ID (0 if no auto-increment).
     *
     * @param array<string, int|float|string|null> $data Column => value pairs
     */
    protected function insertRow(string $table, array $data): int
    {
        $columns = implode(', ', array_map(static fn (string $col): string => "`$col`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
                $values[] = $value;
            } elseif (is_float($value)) {
                $types .= 'd';
                $values[] = $value;
            } elseif (is_string($value)) {
                $types .= 's';
                $values[] = $value;
            } else {
                // null — bind as string with empty value (caller should use sentinel values)
                $types .= 's';
                $values[] = '';
            }
        }

        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt, "Failed to prepare INSERT into $table: " . $this->db->error);

        if ($types !== '') {
            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();

        return $id;
    }

    protected function insertTeamBoxscoreRow(string $date, string $name, int $gameOfDay, int $visitorTid, int $homeTid): int
    {
        return $this->insertRow('ibl_box_scores_teams', [
            'game_date' => $date,
            'name' => $name,
            'game_of_that_day' => $gameOfDay,
            'visitor_teamid' => $visitorTid,
            'home_teamid' => $homeTid,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitor_wins' => 20,
            'visitor_losses' => 10,
            'home_wins' => 25,
            'home_losses' => 5,
            'visitor_q1_points' => 20,
            'visitor_q2_points' => 22,
            'visitor_q3_points' => 18,
            'visitor_q4_points' => 25,
            'visitor_ot_points' => 0,
            'home_q1_points' => 28,
            'home_q2_points' => 24,
            'home_q3_points' => 22,
            'home_q4_points' => 30,
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

    /**
     * Insert a player boxscore row with reasonable defaults for the 29-column table.
     * Generated columns (game_type, season_year, calc_points, calc_rebounds, calc_fg_made)
     * are computed automatically by MariaDB.
     *
     * @param array<string, int|string> $overrides Additional column overrides (e.g. uuid)
     */
    protected function insertPlayerBoxscoreRow(
        string $date,
        int $pid,
        string $name,
        string $pos,
        int $visitorTid,
        int $homeTid,
        int $teamId,
        int $minutes = 30,
        int $points2m = 5,
        int $points2a = 10,
        int $ftm = 4,
        int $fta = 5,
        int $points3m = 2,
        int $points3a = 6,
        int $orb = 2,
        int $drb = 6,
        int $ast = 5,
        int $stl = 2,
        int $tov = 2,
        int $blk = 1,
        int $pf = 3,
        array $overrides = [],
    ): int {
        $data = array_merge([
            'game_date' => $date,
            'name' => $name,
            'pos' => $pos,
            'pid' => $pid,
            'visitor_teamid' => $visitorTid,
            'home_teamid' => $homeTid,
            'teamid' => $teamId,
            'game_min' => $minutes,
            'game_2gm' => $points2m,
            'game_2ga' => $points2a,
            'game_ftm' => $ftm,
            'game_fta' => $fta,
            'game_3gm' => $points3m,
            'game_3ga' => $points3a,
            'game_orb' => $orb,
            'game_drb' => $drb,
            'game_ast' => $ast,
            'game_stl' => $stl,
            'game_tov' => $tov,
            'game_blk' => $blk,
            'game_pf' => $pf,
            'game_of_that_day' => 1,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitor_wins' => 20,
            'visitor_losses' => 10,
            'home_wins' => 25,
            'home_losses' => 5,
            'uuid' => bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(6)),
        ], $overrides);

        return $this->insertRow('ibl_box_scores', $data);
    }

    /**
     * Insert a test player into ibl_plr with sensible defaults.
     * Uses high PIDs (200000000+) to avoid conflicts with production data.
     *
     * @param array<string, int|string|float> $overrides Column overrides
     */
    protected function insertTestPlayer(int $pid, string $name, array $overrides = []): void
    {
        $defaults = [
            'pid' => $pid,
            'name' => $name,
            'age' => 27,
            'teamid' => 1,
            'pos' => 'PG',
            'stamina' => 80,
            'exp' => 5,
            'bird' => 3,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 1500,
            'salary_yr2' => 1600,
            'retired' => 0,
            'ordinal' => 1,
            'droptime' => 0,
            'uuid' => sprintf('test-%09d-0000-000000000001', $pid),
        ];

        $this->insertRow('ibl_plr', array_merge($defaults, $overrides));
    }

    /**
     * Insert a row into the materialized ibl_hist table with sensible defaults.
     *
     * @param array<string, int|string> $overrides Column overrides (uses ibl_hist column names)
     */
    protected function insertHistRow(int $pid, string $name, int $year, array $overrides = []): void
    {
        $defaults = [
            'pid' => $pid,
            'name' => $name,
            'year' => $year,
            'teamid' => $overrides['teamid'] ?? 1,
            'team' => $overrides['team'] ?? '',
            'games' => $overrides['games'] ?? 50,
            'minutes' => $overrides['minutes'] ?? 1600,
            'fgm' => $overrides['fgm'] ?? 300,
            'fga' => $overrides['fga'] ?? 600,
            'ftm' => $overrides['ftm'] ?? 100,
            'fta' => $overrides['fta'] ?? 120,
            'tgm' => $overrides['tgm'] ?? 50,
            'tga' => $overrides['tga'] ?? 130,
            'orb' => $overrides['orb'] ?? 40,
            'reb' => $overrides['reb'] ?? 200,
            'ast' => $overrides['ast'] ?? 150,
            'stl' => $overrides['stl'] ?? 50,
            'blk' => $overrides['blk'] ?? 20,
            'tvr' => $overrides['tvr'] ?? 80,
            'pf' => $overrides['pf'] ?? 100,
            'pts' => $overrides['pts'] ?? 750,
            'salary' => $overrides['salary'] ?? 0,
        ];

        $this->insertRow('ibl_hist', $defaults);
    }

    /**
     * Insert a row into ibl_playoff_series_results (materialized table that
     * backs the vw_playoff_series_results view).
     */
    protected function insertPlayoffSeriesResultRow(
        int $year,
        int $round,
        int $winnerTid,
        int $loserTid,
        string $winner,
        string $loser,
        int $winnerGames,
        int $loserGames,
    ): void {
        $this->insertRow('ibl_playoff_series_results', [
            'year' => $year,
            'round' => $round,
            'winner_tid' => $winnerTid,
            'loser_tid' => $loserTid,
            'winner' => $winner,
            'loser' => $loser,
            'winner_games' => $winnerGames,
            'loser_games' => $loserGames,
            'total_games' => $winnerGames + $loserGames,
        ]);
    }

    /**
     * Insert a row into ibl_team_season_records (materialized per-team
     * win/loss totals refreshed by RefreshTeamSeasonRecordsStep).
     */
    protected function insertTeamSeasonRecordRow(
        int $teamId,
        int $year,
        int $gameType,
        string $currentname,
        string $namethatyear,
        int $wins,
        int $losses,
    ): void {
        $this->insertRow('ibl_team_season_records', [
            'team_id' => $teamId,
            'year' => $year,
            'game_type' => $gameType,
            'currentname' => $currentname,
            'namethatyear' => $namethatyear,
            'wins' => $wins,
            'losses' => $losses,
        ]);
    }

    /**
     * Insert a row into ibl_franchise_seasons.
     * Needed by tests that activate VIEW JOINs through franchise_seasons.
     */
    protected function insertFranchiseSeasonRow(int $franchiseId, int $seasonEndingYear, string $teamName = 'Metros'): void
    {
        $seasonYear = $seasonEndingYear - 1;
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id' => $franchiseId,
            'season_year' => $seasonYear,
            'season_ending_year' => $seasonEndingYear,
            'team_city' => 'New York',
            'team_name' => $teamName,
        ]);
    }

    /**
     * Insert a draft row into ibl_draft.
     * FK on teamid → ibl_team_info requires a valid team ID.
     *
     * @param array<string, int|string> $overrides
     */
    protected function insertDraftRow(int $year, int $round, int $pick, int $teamid, string $player = '', array $overrides = []): int
    {
        $defaults = [
            'year' => $year,
            'round' => $round,
            'pick' => $pick,
            'teamid' => $teamid,
            'team' => 'Metros',
            'player' => $player,
            'uuid' => sprintf('draft-%04d-%d-%02d-%s', $year, $round, $pick, bin2hex(random_bytes(4))),
        ];

        return $this->insertRow('ibl_draft', array_merge($defaults, $overrides));
    }

    /**
     * Insert a draft class prospect row into ibl_draft_class.
     *
     * @param array<string, int|string> $overrides
     */
    protected function insertDraftClassRow(string $name, string $pos = 'PG', array $overrides = []): int
    {
        $defaults = [
            'name' => $name,
            'pos' => $pos,
            'age' => 19,
            'team' => '',
            'fga' => 50,
            'fgp' => 50,
            'fta' => 50,
            'ftp' => 50,
            'r_3ga' => 50,
            'r_3gp' => 50,
            'orb' => 50,
            'drb' => 50,
            'ast' => 50,
            'stl' => 50,
            'tvr' => 50,
            'blk' => 50,
            'oo' => 50,
            'od' => 50,
            'po' => 50,
            'r_trans_off' => 50,
            'r_drive_off' => 50,
            'dd' => 50,
            'pd' => 50,
            'td' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'drafted' => 0,
            'stamina' => 80,
        ];

        return $this->insertRow('ibl_draft_class', array_merge($defaults, $overrides));
    }

    /**
     * Insert a draft pick ownership row into ibl_draft_picks.
     * FK on owner_teamid and teampick_teamid → ibl_team_info.
     *
     * @param array<string, int|string> $overrides
     */
    protected function insertDraftPickRow(int $ownerTid, int $teampickTid, int $year, int $round, array $overrides = []): int
    {
        $defaults = [
            'ownerofpick' => 'Metros',
            'owner_teamid' => $ownerTid,
            'teampick' => 'Metros',
            'teampick_teamid' => $teampickTid,
            'year' => $year,
            'round' => $round,
        ];

        return $this->insertRow('ibl_draft_picks', array_merge($defaults, $overrides));
    }

    /**
     * Insert a schedule row into ibl_schedule with auto-generated uuid.
     *
     * @param array<string, int|string> $overrides Column overrides
     */
    protected function insertScheduleRow(
        int $year,
        string $date,
        int $visitorTid,
        int $visitorScore,
        int $homeTid,
        int $homeScore,
        int $boxId = 0,
        array $overrides = [],
    ): int {
        $defaults = [
            'season_year' => $year,
            'game_date' => $date,
            'visitor_teamid' => $visitorTid,
            'visitor_score' => $visitorScore,
            'home_teamid' => $homeTid,
            'home_score' => $homeScore,
            'box_id' => $boxId,
            'uuid' => sprintf('sched-%s-%s', $date, bin2hex(random_bytes(6))),
        ];

        return $this->insertRow('ibl_schedule', array_merge($defaults, $overrides));
    }

    /**
     * Insert an award row into ibl_awards.
     */
    protected function insertAwardRow(string $playerName, string $award, int $year): int
    {
        return $this->insertRow('ibl_awards', [
            'name' => $playerName,
            'award' => $award,
            'year' => $year,
        ]);
    }

    /**
     * Insert a row into ibl_trade_offers (auto-increment only) and return the new ID.
     */
    protected function insertTradeOfferRow(): int
    {
        $stmt = $this->db->prepare("INSERT INTO ibl_trade_offers () VALUES ()");
        self::assertNotFalse($stmt, 'Failed to prepare trade offer insert: ' . $this->db->error);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Insert a row into ibl_trade_info.
     */
    protected function insertTradeInfoRow(int $offerId, int $itemId, string $itemType, string $from, string $to, string $approval = ''): void
    {
        $this->insertRow('ibl_trade_info', [
            'tradeofferid' => $offerId,
            'itemid' => $itemId,
            'itemtype' => $itemType,
            'trade_from' => $from,
            'trade_to' => $to,
            'approval' => $approval,
        ]);
    }

    /**
     * Insert a row into ibl_trade_cash (offer-based pattern).
     *
     * @param array{salary_yr1?: int, salary_yr2?: int, salary_yr3?: int, salary_yr4?: int, salary_yr5?: int, salary_yr6?: int} $years
     */
    protected function insertTradeCashRow(int $offerId, string $sending, string $receiving, array $years = []): void
    {
        $this->insertRow('ibl_trade_cash', [
            'trade_offer_id' => $offerId,
            'sending_team' => $sending,
            'receiving_team' => $receiving,
            'salary_yr1' => $years['salary_yr1'] ?? 0,
            'salary_yr2' => $years['salary_yr2'] ?? 0,
            'salary_yr3' => $years['salary_yr3'] ?? 0,
            'salary_yr4' => $years['salary_yr4'] ?? 0,
            'salary_yr5' => $years['salary_yr5'] ?? 0,
            'salary_yr6' => $years['salary_yr6'] ?? 0,
        ]);
    }

    /**
     * Insert a row into ibl_trade_queue and return the new ID.
     *
     * @param array<string, mixed> $params JSON-encodable parameters
     */
    protected function insertTradeQueueRow(string $opType, array $params, string $tradeline): int
    {
        return $this->insertRow('ibl_trade_queue', [
            'operation_type' => $opType,
            'params' => json_encode($params, JSON_THROW_ON_ERROR),
            'tradeline' => $tradeline,
        ]);
    }

    /**
     * Insert a row into ibl_fa_offers with sensible defaults.
     * Returns the auto-increment primary_key.
     *
     * @param array<string, int|float|string> $overrides Column overrides
     */
    protected function insertFaOfferRow(int $pid, int $teamid, string $playerName, string $teamName, array $overrides = []): int
    {
        $defaults = [
            'name' => $playerName,
            'pid' => $pid,
            'team' => $teamName,
            'teamid' => $teamid,
            'offer1' => 1500,
            'offer2' => 0,
            'offer3' => 0,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'modifier' => 1.0,
            'random' => 0.5,
            'perceivedvalue' => 3000.0,
            'mle' => 0,
            'lle' => 0,
            'offer_type' => 0,
        ];

        return $this->insertRow('ibl_fa_offers', array_merge($defaults, $overrides));
    }

    /**
     * Insert a row into ibl_demands. PK is `name` (varchar).
     *
     * @param array<string, int> $overrides Column overrides for dem1–dem6
     */
    protected function insertDemandRow(string $name, int $pid, array $overrides = []): void
    {
        $defaults = [
            'name' => $name,
            'pid' => $pid,
            'dem1' => 1500,
            'dem2' => 0,
            'dem3' => 0,
            'dem4' => 0,
            'dem5' => 0,
            'dem6' => 0,
        ];

        $this->insertRow('ibl_demands', array_merge($defaults, $overrides));
    }

    /**
     * Insert a row into ibl_team_awards (team-level awards, not individual player awards).
     * Returns the auto-increment ID.
     */
    protected function insertTeamAwardRow(string $teamName, string $award, int $year): int
    {
        return $this->insertRow('ibl_team_awards', [
            'year' => $year,
            'name' => $teamName,
            'award' => $award,
        ]);
    }

    private function requireEnv(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            self::fail("Environment variable $name is not set — database integration tests require a real MariaDB connection.");
        }
        return $value;
    }
}
