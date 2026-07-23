<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
final class SimSummarySchemaTest extends DatabaseTestCase
{
    #[Test]
    public function tableHasExpectedColumns(): void
    {
        $columns = $this->columnsOf('ibl_sim_summaries');
        self::assertNotSame([], $columns, 'ibl_sim_summaries does not exist after migrations');

        foreach ([
            'sim', 'status', 'recap_text', 'themes_used', 'claimed_at',
            'generated_at', 'attempts', 'blocked_until', 'created_at',
            'intro_text', 'outro_text',
        ] as $col) {
            self::assertArrayHasKey($col, $columns, "Missing column {$col} on ibl_sim_summaries");
        }
    }

    #[Test]
    public function statusEnumHasExactlyTheFourStates(): void
    {
        $stmt = $this->db->prepare(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ibl_sim_summaries'
               AND COLUMN_NAME = 'status'"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{COLUMN_TYPE: string}|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, 'status column not found on ibl_sim_summaries');
        self::assertSame("enum('pending','generating','done','failed')", $row['COLUMN_TYPE']);
    }

    #[Test]
    public function claimIndexExists(): void
    {
        $stmt = $this->db->prepare(
            "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ibl_sim_summaries'
               AND INDEX_NAME = 'idx_claim'
             ORDER BY SEQ_IN_INDEX ASC"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{INDEX_NAME: string, COLUMN_NAME: string, SEQ_IN_INDEX: int}|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, 'idx_claim index does not exist on ibl_sim_summaries');
        self::assertSame('idx_claim', $row['INDEX_NAME']);
        self::assertSame('status', $row['COLUMN_NAME'], 'First column of idx_claim must be status');
    }

    #[Test]
    public function seedHappyPath(): void
    {
        $this->db->query("INSERT INTO `ibl_sim_dates` (sim, start_date, end_date) VALUES (999001, '2099-01-01', '2099-01-07')");

        $seedSql = "INSERT IGNORE INTO `ibl_sim_summaries` (`sim`, `status`, `generated_at`)
SELECT MAX(`sim`), 'done', NOW() FROM `ibl_sim_dates` HAVING MAX(`sim`) IS NOT NULL";
        $this->db->query($seedSql);

        $stmt = $this->db->prepare(
            "SELECT `sim`, `status`, `generated_at`
             FROM `ibl_sim_summaries` WHERE `sim` = ?"
        );
        self::assertNotFalse($stmt);
        $sim = 999001;
        $stmt->bind_param('i', $sim);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{sim: int, status: string, generated_at: string|null}|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, 'Seed should have inserted a row for sim 999001');
        self::assertSame('done', $row['status']);
        self::assertNotNull($row['generated_at']);
    }

    #[Test]
    public function seedIsIdempotent(): void
    {
        $this->db->query("INSERT INTO `ibl_sim_dates` (sim, start_date, end_date) VALUES (999001, '2099-01-01', '2099-01-07')");

        $seedSql = "INSERT IGNORE INTO `ibl_sim_summaries` (`sim`, `status`, `generated_at`)
SELECT MAX(`sim`), 'done', NOW() FROM `ibl_sim_dates` HAVING MAX(`sim`) IS NOT NULL";

        $this->db->query($seedSql);

        // Capture generated_at from first run
        $stmt = $this->db->prepare(
            "SELECT `generated_at` FROM `ibl_sim_summaries` WHERE `sim` = ?"
        );
        self::assertNotFalse($stmt);
        $sim = 999001;
        $stmt->bind_param('i', $sim);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{generated_at: string|null}|null $firstRow */
        $firstRow = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($firstRow);
        $firstGeneratedAt = $firstRow['generated_at'];

        // Run seed a second time
        $this->db->query($seedSql);

        // Assert still exactly one row and generated_at unchanged
        $stmt2 = $this->db->prepare(
            "SELECT COUNT(*) AS cnt, `generated_at`
             FROM `ibl_sim_summaries` WHERE `sim` = ?"
        );
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('i', $sim);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        /** @var array{cnt: int, generated_at: string|null}|null $secondRow */
        $secondRow = $result2->fetch_assoc();
        $stmt2->close();

        self::assertNotNull($secondRow);
        self::assertSame(1, $secondRow['cnt'], 'Idempotent seed must not insert a second row');
        self::assertSame(
            $firstGeneratedAt,
            $secondRow['generated_at'],
            'generated_at must not change on a duplicate seed run'
        );
    }

    #[Test]
    public function seedWithEmptySourceInsertsZeroRows(): void
    {
        $this->db->query('DELETE FROM `ibl_sim_dates`');

        $seedSql = "INSERT IGNORE INTO `ibl_sim_summaries` (`sim`, `status`, `generated_at`)
SELECT MAX(`sim`), 'done', NOW() FROM `ibl_sim_dates` HAVING MAX(`sim`) IS NOT NULL";
        $this->db->query($seedSql);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM `ibl_sim_summaries` WHERE `sim` = ?"
        );
        self::assertNotFalse($stmt);
        $sim = 999001;
        $stmt->bind_param('i', $sim);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{cnt: int}|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, $row['cnt'], 'Seed with empty ibl_sim_dates must insert zero rows');
    }

    #[Test]
    public function childTableHasExpectedColumns(): void
    {
        $columns = $this->columnsOf('ibl_sim_game_recaps');
        self::assertNotSame([], $columns, 'ibl_sim_game_recaps does not exist after migrations');

        foreach ([
            'id', 'sim', 'season_year', 'game_date', 'visitor_teamid',
            'home_teamid', 'game_of_that_day', 'box_id', 'sort_order',
            'recap_text', 'created_at',
        ] as $col) {
            self::assertArrayHasKey($col, $columns, "Missing column {$col} on ibl_sim_game_recaps");
        }

        self::assertStringContainsString(
            'auto_increment',
            $columns['id'],
            'id column on ibl_sim_game_recaps must carry auto_increment'
        );
    }

    #[Test]
    public function childUniqueKeyIsTheNaturalGameKey(): void
    {
        $stmt = $this->db->prepare(
            "SELECT COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ibl_sim_game_recaps'
               AND INDEX_NAME = 'uniq_game'
             ORDER BY SEQ_IN_INDEX ASC"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while (true) {
            /** @var array{COLUMN_NAME: string, SEQ_IN_INDEX: int, NON_UNIQUE: int}|null $row */
            $row = $result->fetch_assoc();
            if (!is_array($row)) {
                break;
            }
            $rows[] = $row;
        }
        $stmt->close();

        self::assertNotSame([], $rows, 'uniq_game index does not exist on ibl_sim_game_recaps');
        self::assertSame(0, (int) $rows[0]['NON_UNIQUE'], 'uniq_game must be a unique index');

        $expectedColumns = ['season_year', 'game_date', 'visitor_teamid', 'home_teamid', 'game_of_that_day'];
        $actualColumns = array_column($rows, 'COLUMN_NAME');
        self::assertSame($expectedColumns, $actualColumns, 'uniq_game must cover exactly the natural game key in SEQ_IN_INDEX order');
    }

    #[Test]
    public function childForeignKeyCascades(): void
    {
        $stmt = $this->db->prepare(
            "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME = 'fk_sgr_sim'"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{CONSTRAINT_NAME: string, REFERENCED_TABLE_NAME: string, DELETE_RULE: string}|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, 'fk_sgr_sim foreign key does not exist');
        self::assertSame('ibl_sim_summaries', $row['REFERENCED_TABLE_NAME']);
        self::assertSame('CASCADE', $row['DELETE_RULE']);
    }

    #[Test]
    public function childIndexesExist(): void
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ibl_sim_game_recaps'
               AND INDEX_NAME IN ('idx_sim', 'idx_game')"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $result = $stmt->get_result();

        $found = [];
        while (true) {
            /** @var array{INDEX_NAME: string}|null $row */
            $row = $result->fetch_assoc();
            if (!is_array($row)) {
                break;
            }
            $found[] = $row['INDEX_NAME'];
        }
        $stmt->close();

        self::assertContains('idx_sim', $found, 'idx_sim index missing on ibl_sim_game_recaps');
        self::assertContains('idx_game', $found, 'idx_game index missing on ibl_sim_game_recaps');
    }

    #[Test]
    public function childForeignKeyRejectsAnOrphanSim(): void
    {
        $orphanSim = 999999;

        // Confirm no envelope row exists for this sim
        $checkStmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM `ibl_sim_summaries` WHERE `sim` = ?'
        );
        self::assertNotFalse($checkStmt);
        $checkStmt->bind_param('i', $orphanSim);
        $checkStmt->execute();
        /** @var array{cnt: int}|null $checkRow */
        $checkRow = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        self::assertSame(0, (int) ($checkRow['cnt'] ?? 0), 'Precondition: no envelope row for orphan sim');

        // Attempt insert into child with orphan sim — must throw FK violation (errno 1452)
        try {
            $this->db->query(
                "INSERT INTO `ibl_sim_game_recaps`
                 (`sim`, `season_year`, `game_date`, `visitor_teamid`, `home_teamid`,
                  `game_of_that_day`, `sort_order`, `recap_text`)
                 VALUES ({$orphanSim}, 2099, '2099-01-01', 1, 2, 0, 1, 'orphan test')"
            );
            self::fail('INSERT with orphan sim was not rejected by FK constraint');
        } catch (\mysqli_sql_exception $e) {
            self::assertSame(1452, $e->getCode(), 'Expected FK violation errno 1452 (ER_NO_REFERENCED_ROW_2)');
        }

        // Row count for orphan sim must still be 0
        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM `ibl_sim_game_recaps` WHERE `sim` = ?'
        );
        self::assertNotFalse($countStmt);
        $countStmt->bind_param('i', $orphanSim);
        $countStmt->execute();
        /** @var array{cnt: int}|null $countRow */
        $countRow = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        self::assertSame(0, (int) ($countRow['cnt'] ?? 0), 'No child row must exist for an orphan sim after FK rejection');
    }

    /**
     * @return array<string, string> column name => EXTRA (e.g. 'auto_increment'), empty if table absent
     */
    private function columnsOf(string $table): array
    {
        $stmt = $this->db->prepare(
            'SELECT COLUMN_NAME, EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();

        $columns = [];
        while (true) {
            /** @var array{COLUMN_NAME: string, EXTRA: string}|null $row */
            $row = $result->fetch_assoc();
            if (!is_array($row)) {
                break;
            }
            $columns[$row['COLUMN_NAME']] = $row['EXTRA'];
        }
        $stmt->close();

        return $columns;
    }
}
