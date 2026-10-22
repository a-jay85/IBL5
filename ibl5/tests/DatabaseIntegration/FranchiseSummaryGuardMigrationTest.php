<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Pins the apply-time guard at the top of the franchise-summary-reads-season-records migration.
 *
 * Only the guard SELECT runs here. The rest of the file is CREATE OR REPLACE VIEW, and DDL
 * commits the per-test transaction.
 */
#[Group('database')]
class FranchiseSummaryGuardMigrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->execStatement("SET SESSION sql_mode = ''");
    }

    public function testGuardPassesWhenSeasonRecordsPresent(): void
    {
        $this->seedRegularSeasonBoxScore();
        $name = $this->teamOneName();
        $this->insertTeamSeasonRecordRow(1, 2092, 1, $name, $name, 1, 0);

        self::assertSame('0', $this->runGuard());
    }

    public function testGuardAbortsWhenBoxScoresExistButSeasonRecordsEmpty(): void
    {
        $this->execStatement('DELETE FROM ibl_team_season_records WHERE game_type = 1');
        $this->seedRegularSeasonBoxScore();

        $this->expectException(\mysqli_sql_exception::class);
        $this->expectExceptionCode(1242);
        $this->runGuard();
    }

    public function testGuardAbortsWhenOnlyHeatSeasonRecordsExist(): void
    {
        $this->execStatement('DELETE FROM ibl_team_season_records WHERE game_type = 1');
        $name = $this->teamOneName();
        $this->insertTeamSeasonRecordRow(1, 2092, 3, $name, $name, 1, 0);
        $this->seedRegularSeasonBoxScore();

        $this->expectException(\mysqli_sql_exception::class);
        $this->expectExceptionCode(1242);
        $this->runGuard();
    }

    public function testGuardPassesOnEmptyDatabase(): void
    {
        $this->execStatement('SET SESSION FOREIGN_KEY_CHECKS = 0');
        $this->execStatement('DELETE FROM ibl_box_scores_teams WHERE game_type = 1');
        $this->execStatement('DELETE FROM ibl_team_season_records WHERE game_type = 1');
        $this->execStatement('SET SESSION FOREIGN_KEY_CHECKS = 1');

        self::assertSame('0', $this->runGuard());
    }

    private function guardSql(): string
    {
        $files = glob(__DIR__ . '/../../migrations/*_franchise_summary_reads_season_records.sql');
        self::assertIsArray($files);
        self::assertCount(1, $files);

        $contents = file_get_contents($files[0]);
        self::assertIsString($contents);
        self::assertSame(1, preg_match('/-- GUARD-BEGIN\R(.*?)\R-- GUARD-END/s', $contents, $matches));

        return rtrim(trim($matches[1]), ';');
    }

    private function runGuard(): string
    {
        $result = $this->db->query($this->guardSql());
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        $value = $row['franchise_summary_guard'] ?? null;
        self::assertTrue(is_scalar($value));

        return (string) $value;
    }

    private function seedRegularSeasonBoxScore(): void
    {
        $this->insertTeamBoxscoreRow('2091-11-05', 'Visitor', 1, 1, 2);
        $this->insertTeamBoxscoreRow('2091-11-05', 'Home', 1, 1, 2);
    }

    private function teamOneName(): string
    {
        $result = $this->db->query('SELECT team_name FROM ibl_team_info WHERE teamid = 1');
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        $name = $row['team_name'] ?? null;
        self::assertIsString($name);

        return $name;
    }

    private function execStatement(string $sql): void
    {
        self::assertTrue($this->db->query($sql), 'statement failed: ' . $this->db->error);
    }
}
