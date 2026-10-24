<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Pins migration 195: the self-healing columns, their defaults, and the enum
 * types that reject a value outside the cause whitelist.
 */
#[Group('database')]
final class SimSummariesSelfHealingSchemaTest extends DatabaseTestCase
{
    public function testSelfHealingColumnsExistWithDefaults(): void
    {
        $expected = [
            'route' => ["enum('any','mac','actions')", "'any'"],
            'routed_at' => ['datetime', 'NULL'],
            'claimed_by' => ["enum('mac','actions')", 'NULL'],
            'last_cause' => ["enum('flaky','usage_limit','auth','transport','prompt')", 'NULL'],
            'flaky_failures' => ['tinyint(3) unsigned', '0'],
            'transport_failures' => ['tinyint(3) unsigned', '0'],
            'usage_waits' => ['tinyint(3) unsigned', '0'],
            'auth_failed_hosts' => ["set('mac','actions')", "''"],
            'notified_state' => ["enum('retrying','waiting','handed_mac','handed_actions','posted','recovered','unavailable','started')", 'NULL'],
        ];

        $result = $this->db->query(
            "SELECT `COLUMN_NAME` AS `name`, `COLUMN_TYPE` AS `type`, `COLUMN_DEFAULT` AS `dflt`"
            . " FROM information_schema.COLUMNS"
            . " WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'ibl_sim_summaries'"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $actual = [];
        while (($row = $result->fetch_assoc()) !== null) {
            self::assertIsString($row['name']);
            $actual[$row['name']] = [$row['type'], $row['dflt'] ?? 'NULL'];
        }

        foreach ($expected as $column => $shape) {
            self::assertArrayHasKey($column, $actual, "Column {$column} must exist");
            self::assertSame($shape, $actual[$column], "Column {$column} type/default");
        }
    }

    public function testNotifiedStateCommentSurvivesStartedMigration(): void
    {
        $result = $this->db->query(
            "SELECT `COLUMN_COMMENT` AS `comment` FROM information_schema.COLUMNS"
            . " WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'ibl_sim_summaries'"
            . " AND `COLUMN_NAME` = 'notified_state'"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertSame(
            'Last #admin-chat status posted for this sim; compare-and-set de-dup key.',
            $row['comment']
        );
    }

    public function testLegacyPendingRowReadsDefaultRoute(): void
    {
        $repo = new SimSummaryRepository($this->db);
        self::assertTrue($repo->queuePendingIfAbsent(999101));

        $row = $repo->find(999101);
        self::assertNotNull($row);
        self::assertSame('any', $row['route']);
        self::assertNull($row['routed_at']);
        self::assertNull($row['claimed_by']);
        self::assertNull($row['last_cause']);
        self::assertNull($row['notified_state']);
        self::assertSame('', $row['auth_failed_hosts']);
        self::assertSame(0, $row['flaky_failures']);
        self::assertSame(0, $row['transport_failures']);
        self::assertSame(0, $row['usage_waits']);
    }

    public function testCauseEnumRejectsUnknownValue(): void
    {
        $repo = new SimSummaryRepository($this->db);
        $repo->queuePendingIfAbsent(999102);
        $this->db->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");

        $this->expectException(\mysqli_sql_exception::class);
        $this->db->query("UPDATE `ibl_sim_summaries` SET `last_cause` = 'bogus' WHERE `sim` = 999102");
    }
}
