<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Migration 142 (maintenance-41 — backlog 15.10): ibl_box_scores.teamid gains a
 * foreign key fk_boxscore_team -> ibl_team_info.teamid (ON UPDATE CASCADE), mirroring
 * the sibling fk_boxscore_home / fk_boxscore_visitor. The column already exists with
 * the correct name/signedness (migration 114), so this is an FK-add only — no rename.
 */
#[Group('database')]
class BoxScoresTeamidFkTest extends DatabaseTestCase
{
    public function testForeignKeyPresentOnTeamid(): void
    {
        $result = $this->db->query(
            "SELECT rc.CONSTRAINT_NAME, rc.UPDATE_RULE, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
             JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
              AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
               AND rc.TABLE_NAME = 'ibl_box_scores'
               AND kcu.COLUMN_NAME = 'teamid'"
        );
        self::assertNotFalse($result);

        $row = $result->fetch_assoc();
        self::assertNotNull($row, 'No FK found on ibl_box_scores.teamid');
        self::assertSame('ibl_team_info', $row['REFERENCED_TABLE_NAME']);
        self::assertSame('teamid', $row['REFERENCED_COLUMN_NAME']);
        self::assertSame('CASCADE', $row['UPDATE_RULE'], 'teamid FK must be ON UPDATE CASCADE');
    }

    public function testTeamidSignednessMatchesReferencedColumn(): void
    {
        $types = [];
        foreach ([['ibl_box_scores', 'teamid'], ['ibl_team_info', 'teamid']] as [$table, $col]) {
            $stmt = $this->db->prepare(
                "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            self::assertNotFalse($stmt);
            $stmt->bind_param('ss', $table, $col);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            self::assertNotNull($row, "$table.$col not found");
            $types["$table.$col"] = strtolower((string) $row['COLUMN_TYPE']);
        }

        foreach ($types as $key => $type) {
            self::assertStringContainsString('int(11)', $type, "$key must be int(11)");
            self::assertStringNotContainsString('unsigned', $type, "$key must be signed (FK signedness must match)");
        }
    }

    public function testValidTeamidAccepted(): void
    {
        $row = $this->db->query('SELECT teamid FROM ibl_team_info ORDER BY teamid LIMIT 1')->fetch_assoc();
        self::assertNotNull($row, 'seed has no ibl_team_info rows');
        $teamid = (int) $row['teamid'];

        $pid = 200420001;
        $this->insertTestPlayer($pid, 'BS FK Valid', ['teamid' => $teamid]);

        $id = $this->insertPlayerBoxscoreRow('2025-04-02', $pid, 'BS FK Valid', 'PG', 2, 1, $teamid);

        $check = $this->db->query("SELECT teamid FROM ibl_box_scores WHERE id = $id")->fetch_assoc();
        self::assertNotNull($check);
        self::assertSame($teamid, (int) $check['teamid']);
    }

    public function testInvalidTeamidRejectedByForeignKey(): void
    {
        $pid = 200420002;
        $this->insertTestPlayer($pid, 'BS FK Invalid');

        $this->expectException(\mysqli_sql_exception::class);
        // visitor/home teamids are valid seed rows, so the ONLY violated constraint
        // is the new fk_boxscore_team on teamid.
        $this->insertPlayerBoxscoreRow('2025-04-02', $pid, 'BS FK Invalid', 'PG', 2, 1, 2147483647);
    }
}
