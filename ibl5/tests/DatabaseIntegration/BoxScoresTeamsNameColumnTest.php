<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Migration 138 (maintenance-28 — backlog 15.13): box_scores_teams.name is
 * NOT NULL DEFAULT '' on both Olympics-parity tables, documented as an
 * intentionally denormalized team label with no FK.
 */
#[Group('database')]
class BoxScoresTeamsNameColumnTest extends DatabaseTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function parityTableProvider(): array
    {
        return [
            'ibl_box_scores_teams' => ['ibl_box_scores_teams'],
            'ibl_olympics_box_scores_teams' => ['ibl_olympics_box_scores_teams'],
        ];
    }

    #[DataProvider('parityTableProvider')]
    public function testNameColumnIsNotNull(string $table): void
    {
        $stmt = $this->db->prepare(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS maxlen, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'name'"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, "$table.name column not found");
        self::assertSame(16, (int) $row['maxlen']);
        self::assertSame('NO', $row['IS_NULLABLE'], "$table.name must be NOT NULL");
        // MariaDB reports an empty-string default as the quoted literal ('').
        self::assertSame('', trim((string) $row['COLUMN_DEFAULT'], "'"), "$table.name must default to ''");
        self::assertStringContainsString('no FK', (string) $row['COLUMN_COMMENT']);
    }
}
