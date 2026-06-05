<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Migration 136 (maintenance-28 — backlog 15.15): ASG/EOY ballot columns
 * resized from varchar(255) to varchar(32) for player-name ballots and
 * varchar(25) for GM-of-Year ballots (matching ibl_plr.name / gm_username).
 */
#[Group('database')]
class BallotColumnWidthTest extends DatabaseTestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function ballotColumnProvider(): array
    {
        $asgPlayerCols = [
            'east_f1', 'east_f2', 'east_f3', 'east_f4',
            'east_b1', 'east_b2', 'east_b3', 'east_b4',
            'west_f1', 'west_f2', 'west_f3', 'west_f4',
            'west_b1', 'west_b2', 'west_b3', 'west_b4',
        ];
        $eoyPlayerCols = ['mvp_1', 'mvp_2', 'mvp_3', 'six_1', 'six_2', 'six_3', 'roy_1', 'roy_2', 'roy_3'];
        $eoyGmCols = ['gm_1', 'gm_2', 'gm_3'];

        $cases = [];
        foreach ($asgPlayerCols as $col) {
            $cases["ASG $col"] = ['ibl_votes_ASG', $col, 32];
        }
        foreach ($eoyPlayerCols as $col) {
            $cases["EOY $col"] = ['ibl_votes_EOY', $col, 32];
        }
        foreach ($eoyGmCols as $col) {
            $cases["EOY $col"] = ['ibl_votes_EOY', $col, 25];
        }

        return $cases;
    }

    #[DataProvider('ballotColumnProvider')]
    public function testBallotColumnWidth(string $table, string $column, int $expectedWidth): void
    {
        $stmt = $this->db->prepare(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS maxlen
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, "Column $table.$column not found");
        self::assertSame(
            $expectedWidth,
            (int) $row['maxlen'],
            "$table.$column should be varchar($expectedWidth)"
        );
    }
}
