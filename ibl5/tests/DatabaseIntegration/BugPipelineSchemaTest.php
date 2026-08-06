<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Characterization test for migration 153 (Discord bug-pipeline queue tables).
 *
 * Asserts the three tables, key columns, snowflake column types, status enum set,
 * and the load-bearing composite lease index exist after migration.
 */
#[Group('database')]
final class BugPipelineSchemaTest extends DatabaseTestCase
{
    #[Test]
    public function bugReportsTableHasExpectedColumns(): void
    {
        $columns = $this->columnsOf('ibl_bug_reports');
        self::assertNotSame([], $columns, 'ibl_bug_reports does not exist after migrations');

        foreach ([
            'id', 'discord_author_id', 'channel_id', 'original_message_id', 'original_text',
            'thread_id', 'class', 'status', 'lease_owner', 'lease_expires', 'hunt_attempts',
            'pr_number', 'issue_number', 'approval_message_id', 'blocked_until',
            'last_gm_reply_at', 'last_processed_at', 'reminder_sent_at', 'created_at', 'updated_at',
        ] as $col) {
            self::assertArrayHasKey($col, $columns, "Missing column $col on ibl_bug_reports");
        }

        self::assertStringContainsStringIgnoringCase('auto_increment', $columns['id'], 'id must be AUTO_INCREMENT');
    }

    #[Test]
    public function bugReporterProfileTableHasExpectedColumns(): void
    {
        $columns = $this->columnsOf('ibl_bug_reporter_profile');
        self::assertNotSame([], $columns, 'ibl_bug_reporter_profile does not exist after migrations');

        foreach (['discord_author_id', 'tech_level', 'created_at', 'updated_at'] as $col) {
            self::assertArrayHasKey($col, $columns, "Missing column $col on ibl_bug_reporter_profile");
        }
    }

    #[Test]
    public function bugPipelineStateTableHasExpectedColumns(): void
    {
        $columns = $this->columnsOf('ibl_bug_pipeline_state');
        self::assertNotSame([], $columns, 'ibl_bug_pipeline_state does not exist after migrations');

        foreach (['channel_id', 'last_processed_message_id', 'updated_at'] as $col) {
            self::assertArrayHasKey($col, $columns, "Missing column $col on ibl_bug_pipeline_state");
        }
    }

    #[Test]
    public function bugReportAttachmentsTableHasExpectedColumns(): void
    {
        $columns = $this->columnsOf('ibl_bug_report_attachments');
        self::assertNotSame([], $columns, 'ibl_bug_report_attachments does not exist after migrations');

        foreach ([
            'id', 'report_id', 'attachment_id', 'original_url', 'local_path',
            'filename', 'content_type', 'file_size', 'created_at',
        ] as $col) {
            self::assertArrayHasKey($col, $columns, "Missing column $col on ibl_bug_report_attachments");
        }

        // attachment_id MUST be varchar(20), never an integer type. A BIGINT snowflake
        // reads back as a native PHP int (MYSQLI_OPT_INT_AND_FLOAT_NATIVE) and json_encode
        // corrupts any 19-digit id above 2^53. This assertion fails loudly if someone
        // "corrects" the column back to BIGINT and reintroduces the precision bug.
        $stmt = $this->db->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        self::assertNotFalse($stmt);
        $table = 'ibl_bug_report_attachments';
        $column = 'attachment_id';
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        /** @var array{COLUMN_TYPE: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row, 'ibl_bug_report_attachments.attachment_id column not found');
        self::assertSame(
            'varchar(20)',
            $row['COLUMN_TYPE'],
            'attachment_id must be varchar(20), NOT an integer type — a BIGINT snowflake corrupts on native-int read'
        );
        $stmt->close();
    }

    #[Test]
    public function bugReportAttachmentsHasUniqueKeyAndForeignKey(): void
    {
        // ux_report_attachment must exist and be UNIQUE (Non_unique = 0) — this backs the
        // INSERT IGNORE replay-idempotency in BugReportRepository::insertAttachments().
        $stmt = $this->db->prepare(
            'SELECT NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             GROUP BY INDEX_NAME, NON_UNIQUE'
        );
        self::assertNotFalse($stmt);
        $table = 'ibl_bug_report_attachments';
        $indexName = 'ux_report_attachment';
        $stmt->bind_param('ss', $table, $indexName);
        $stmt->execute();
        /** @var array{NON_UNIQUE: int|string, cols: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row, 'ux_report_attachment index is missing — backs INSERT IGNORE idempotency');
        self::assertSame(0, (int) $row['NON_UNIQUE'], 'ux_report_attachment must be UNIQUE (Non_unique = 0)');
        self::assertSame('report_id,attachment_id', $row['cols'], 'ux_report_attachment column order must be report_id,attachment_id');
        $stmt->close();

        // The FK fk_bug_attachment_report must be live, not merely declared.
        $fkStmt = $this->db->prepare(
            'SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?'
        );
        self::assertNotFalse($fkStmt);
        $fkName = 'fk_bug_attachment_report';
        $fkStmt->bind_param('ss', $table, $fkName);
        $fkStmt->execute();
        /** @var array{REFERENCED_TABLE_NAME: ?string, REFERENCED_COLUMN_NAME: ?string}|null $fkRow */
        $fkRow = $fkStmt->get_result()->fetch_assoc();
        self::assertNotNull($fkRow, 'fk_bug_attachment_report foreign key is missing');
        self::assertSame('ibl_bug_reports', $fkRow['REFERENCED_TABLE_NAME'], 'FK must reference ibl_bug_reports');
        self::assertSame('id', $fkRow['REFERENCED_COLUMN_NAME'], 'FK must reference ibl_bug_reports.id');
        $fkStmt->close();
    }

    #[Test]
    public function snowflakeColumnsAreBigintUnsigned(): void
    {
        $cases = [
            ['ibl_bug_reports', 'discord_author_id'],
            ['ibl_bug_reports', 'channel_id'],
            ['ibl_bug_reports', 'original_message_id'],
            ['ibl_bug_reports', 'thread_id'],
            ['ibl_bug_reports', 'approval_message_id'],
            ['ibl_bug_reporter_profile', 'discord_author_id'],
            ['ibl_bug_pipeline_state', 'channel_id'],
            ['ibl_bug_pipeline_state', 'last_processed_message_id'],
        ];

        $stmt = $this->db->prepare(
            'SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        self::assertNotFalse($stmt);

        foreach ($cases as [$table, $column]) {
            $stmt->bind_param('ss', $table, $column);
            $stmt->execute();
            /** @var array{DATA_TYPE: string, COLUMN_TYPE: string}|null $row */
            $row = $stmt->get_result()->fetch_assoc();
            self::assertNotNull($row, "$table.$column column not found");
            self::assertSame('bigint', $row['DATA_TYPE'], "$table.$column must be bigint, not int (32-bit would truncate snowflakes)");
            self::assertStringContainsString('unsigned', $row['COLUMN_TYPE'], "$table.$column must be UNSIGNED");
        }

        $stmt->close();
    }

    #[Test]
    public function statusEnumHasExactValueSet(): void
    {
        $stmt = $this->db->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        self::assertNotFalse($stmt);

        $table = 'ibl_bug_reports';
        $column = 'status';
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        /** @var array{COLUMN_TYPE: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row, 'ibl_bug_reports.status column not found');
        self::assertSame(
            "enum('queued','awaiting_info','hunting','blocked','pr_open','fixed','needs_human','parked_idle','gathering','awaiting_ajay','planned','dropped')",
            $row['COLUMN_TYPE'],
            'ibl_bug_reports.status enum set must match exactly (missing/extra/misspelled value fails here)'
        );

        $table = 'ibl_bug_reporter_profile';
        $column = 'tech_level';
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        /** @var array{COLUMN_TYPE: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row, 'ibl_bug_reporter_profile.tech_level column not found');
        self::assertSame(
            "enum('technical','nontechnical')",
            $row['COLUMN_TYPE'],
            'ibl_bug_reporter_profile.tech_level enum set must match exactly'
        );

        $stmt->close();
    }

    #[Test]
    public function leaseCompositeIndexHasStatusThenExpires(): void
    {
        $stmt = $this->db->prepare(
            "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             GROUP BY INDEX_NAME"
        );
        self::assertNotFalse($stmt);

        $table = 'ibl_bug_reports';
        $indexName = 'idx_lease';
        $stmt->bind_param('ss', $table, $indexName);
        $stmt->execute();
        /** @var array{cols: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row, "idx_lease on ibl_bug_reports is missing — backs the atomic single-flight lease claim");
        self::assertSame('status,lease_expires', $row['cols'], "idx_lease column order must be status,lease_expires (load-bearing for the query plan)");

        foreach (['idx_status', 'idx_thread', 'idx_author'] as $idx) {
            $stmt->bind_param('ss', $table, $idx);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            self::assertNotNull($row, "$idx on ibl_bug_reports is missing");
        }

        $stmt->close();
    }

    #[Test]
    public function migrationIsForwardOnlyAndIdempotent(): void
    {
        $matches = glob(__DIR__ . '/../../migrations/*_create_bug_pipeline_tables.sql');
        self::assertNotFalse($matches);
        self::assertCount(1, $matches, 'Expected exactly one *_create_bug_pipeline_tables.sql migration');

        $sql = (string) file_get_contents($matches[0]);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `ibl_bug_reports`', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `ibl_bug_reporter_profile`', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `ibl_bug_pipeline_state`', $sql);
        self::assertStringNotContainsString('DROP TABLE', $sql);
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
