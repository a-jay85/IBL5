<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Migration 137 (maintenance-28 — backlog 15.23): ibl_gm_history.name is
 * documented as a GM username (ref ibl_team_info.gm_username) and deliberately
 * kept at varchar(50) — NOT shrunk to 25 — because historical usernames cannot
 * be proven <= 25 (the seed is empty; ibl_gm_tenures.gm_username is varchar(50)).
 */
#[Group('database')]
class GmHistoryNameColumnTest extends DatabaseTestCase
{
    public function testNameColumnIsDocumentedAndNotShrunk(): void
    {
        $stmt = $this->db->prepare(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS maxlen, IS_NULLABLE, COLUMN_COMMENT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ibl_gm_history' AND COLUMN_NAME = 'name'"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, 'ibl_gm_history.name column not found');
        self::assertSame(50, (int) $row['maxlen'], 'name must stay varchar(50) (shrink is not provably safe)');
        self::assertSame('NO', $row['IS_NULLABLE']);
        self::assertStringContainsString('GM username', (string) $row['COLUMN_COMMENT']);
        self::assertStringContainsString('gm_username', (string) $row['COLUMN_COMMENT']);
    }
}
