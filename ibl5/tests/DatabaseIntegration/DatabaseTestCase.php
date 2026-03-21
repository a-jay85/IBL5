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
            'Date' => $date,
            'name' => $name,
            'gameOfThatDay' => $gameOfDay,
            'visitorTeamID' => $visitorTid,
            'homeTeamID' => $homeTid,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitorWins' => 20,
            'visitorLosses' => 10,
            'homeWins' => 25,
            'homeLosses' => 5,
            'visitorQ1points' => 20,
            'visitorQ2points' => 22,
            'visitorQ3points' => 18,
            'visitorQ4points' => 25,
            'visitorOTpoints' => 0,
            'homeQ1points' => 28,
            'homeQ2points' => 24,
            'homeQ3points' => 22,
            'homeQ4points' => 30,
            'homeOTpoints' => 0,
            'game2GM' => 30,
            'game2GA' => 60,
            'gameFTM' => 15,
            'gameFTA' => 20,
            'game3GM' => 8,
            'game3GA' => 22,
            'gameORB' => 10,
            'gameDRB' => 30,
            'gameAST' => 20,
            'gameSTL' => 8,
            'gameTOV' => 12,
            'gameBLK' => 5,
            'gamePF' => 18,
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
            'Date' => $date,
            'name' => $name,
            'pos' => $pos,
            'pid' => $pid,
            'visitorTID' => $visitorTid,
            'homeTID' => $homeTid,
            'teamID' => $teamId,
            'gameMIN' => $minutes,
            'game2GM' => $points2m,
            'game2GA' => $points2a,
            'gameFTM' => $ftm,
            'gameFTA' => $fta,
            'game3GM' => $points3m,
            'game3GA' => $points3a,
            'gameORB' => $orb,
            'gameDRB' => $drb,
            'gameAST' => $ast,
            'gameSTL' => $stl,
            'gameTOV' => $tov,
            'gameBLK' => $blk,
            'gamePF' => $pf,
            'gameOfThatDay' => 1,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitorWins' => 20,
            'visitorLosses' => 10,
            'homeWins' => 25,
            'homeLosses' => 5,
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
            'tid' => 1,
            'pos' => 'PG',
            'sta' => 80,
            'exp' => 5,
            'bird' => 3,
            'cy' => 1,
            'cyt' => 3,
            'cy1' => 1500,
            'cy2' => 1600,
            'retired' => 0,
            'ordinal' => 1,
            'droptime' => 0,
            'uuid' => sprintf('test-%09d-0000-000000000001', $pid),
        ];

        $this->insertRow('ibl_plr', array_merge($defaults, $overrides));
    }

    /**
     * Insert a row into ibl_hist with sensible defaults.
     *
     * @param array<string, int|string> $overrides Column overrides
     */
    protected function insertHistRow(int $pid, string $name, int $year, array $overrides = []): void
    {
        $defaults = [
            'pid' => $pid,
            'name' => $name,
            'year' => $year,
            'team' => 'Metros',
            'teamid' => 1,
            'games' => 50,
            'minutes' => 1600,
            'fgm' => 300,
            'fga' => 600,
            'ftm' => 100,
            'fta' => 120,
            'tgm' => 50,
            'tga' => 130,
            'orb' => 40,
            'reb' => 200,
            'ast' => 150,
            'stl' => 50,
            'blk' => 20,
            'tvr' => 80,
            'pf' => 100,
            'pts' => 750,
            'salary' => 1500,
        ];

        $this->insertRow('ibl_hist', array_merge($defaults, $overrides));
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
     * FK on tid → ibl_team_info requires a valid team ID.
     *
     * @param array<string, int|string> $overrides
     */
    protected function insertDraftRow(int $year, int $round, int $pick, int $tid, string $player = '', array $overrides = []): int
    {
        $defaults = [
            'year' => $year,
            'round' => $round,
            'pick' => $pick,
            'tid' => $tid,
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
            'tga' => 50,
            'tgp' => 50,
            'orb' => 50,
            'drb' => 50,
            'ast' => 50,
            'stl' => 50,
            'tvr' => 50,
            'blk' => 50,
            'oo' => 50,
            'od' => 50,
            'po' => 50,
            'to' => 50,
            'do' => 50,
            'dd' => 50,
            'pd' => 50,
            'td' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'drafted' => 0,
            'sta' => 80,
        ];

        return $this->insertRow('ibl_draft_class', array_merge($defaults, $overrides));
    }

    /**
     * Insert a draft pick ownership row into ibl_draft_picks.
     * FK on owner_tid and teampick_tid → ibl_team_info.
     *
     * @param array<string, int|string> $overrides
     */
    protected function insertDraftPickRow(int $ownerTid, int $teampickTid, int $year, int $round, array $overrides = []): int
    {
        $defaults = [
            'ownerofpick' => 'Metros',
            'owner_tid' => $ownerTid,
            'teampick' => 'Metros',
            'teampick_tid' => $teampickTid,
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
            'Year' => $year,
            'Date' => $date,
            'Visitor' => $visitorTid,
            'VScore' => $visitorScore,
            'Home' => $homeTid,
            'HScore' => $homeScore,
            'BoxID' => $boxId,
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
            'Award' => $award,
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
     * @param array{cy1?: int, cy2?: int, cy3?: int, cy4?: int, cy5?: int, cy6?: int} $years
     */
    protected function insertTradeCashRow(int $offerId, string $sending, string $receiving, array $years = []): void
    {
        $this->insertRow('ibl_trade_cash', [
            'tradeOfferID' => $offerId,
            'sendingTeam' => $sending,
            'receivingTeam' => $receiving,
            'cy1' => $years['cy1'] ?? 0,
            'cy2' => $years['cy2'] ?? 0,
            'cy3' => $years['cy3'] ?? 0,
            'cy4' => $years['cy4'] ?? 0,
            'cy5' => $years['cy5'] ?? 0,
            'cy6' => $years['cy6'] ?? 0,
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
    protected function insertFaOfferRow(int $pid, int $tid, string $playerName, string $teamName, array $overrides = []): int
    {
        $defaults = [
            'name' => $playerName,
            'pid' => $pid,
            'team' => $teamName,
            'tid' => $tid,
            'offer1' => 1500,
            'offer2' => 0,
            'offer3' => 0,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'modifier' => 1.0,
            'random' => 0.5,
            'perceivedvalue' => 3000.0,
            'MLE' => 0,
            'LLE' => 0,
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

    private function requireEnv(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            self::fail("Environment variable $name is not set — database integration tests require a real MariaDB connection.");
        }
        return $value;
    }
}
