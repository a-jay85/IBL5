<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises migration 160 (remove phantom playoff rows) against seeded fixtures.
 * Each test seeds its own rows, applies the real migration file, and rolls back.
 *
 * Applying the file itself rather than a transcription is deliberate: a test
 * holding its own copy of the SQL cannot detect the shipped file being wrong.
 */
#[Group('database')]
final class StalePlayoffRowsMigrationTest extends DatabaseTestCase
{
    private const SEASON_YEAR = 2025;
    private const UNPLAYED_BOX_ID = 100000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearSetting();
    }

    public function testMigrationRemovesPhantomPlayoffRowsForTheCurrentSeason(): void
    {
        $this->seedSetting(self::SEASON_YEAR);
        $id1 = $this->seedJuneRow(self::SEASON_YEAR, 50001);
        $id2 = $this->seedJuneRow(self::SEASON_YEAR, 50002);

        $affected = $this->applyMigration();

        self::assertSame(2, $affected, 'migration must delete both phantom rows');
        self::assertRowGone($id1);
        self::assertRowGone($id2);
    }

    public function testMigrationPreservesUnplayedPlayoffAndRegularSeasonRows(): void
    {
        $this->seedSetting(self::SEASON_YEAR);
        // Unplayed sentinel (box_id >= UNPLAYED_BOX_ID) — must survive
        $unplayedId = $this->seedJuneRow(self::SEASON_YEAR, self::UNPLAYED_BOX_ID);
        // Regular-season row in April — must survive (month != 6)
        $regularId  = $this->insertScheduleRow(self::SEASON_YEAR, '2025-04-10', 1, 100, 2, 95, 50003);
        // Phantom playoff row — must be deleted
        $phantomId  = $this->seedJuneRow(self::SEASON_YEAR, 50004);

        $this->applyMigration();

        self::assertRowExists($unplayedId, 'unplayed sentinel must survive');
        self::assertRowExists($regularId, 'regular-season row must survive');
        self::assertRowGone($phantomId);
    }

    public function testMigrationLeavesOtherSeasonsUntouched(): void
    {
        $this->seedSetting(self::SEASON_YEAR);
        $otherSeasonId = $this->seedJuneRow(2024, 50005);
        $phantomId     = $this->seedJuneRow(self::SEASON_YEAR, 50006);

        $this->applyMigration();

        self::assertRowExists($otherSeasonId, 'row from a different season must survive');
        self::assertRowGone($phantomId);
    }

    public function testMigrationIsIdempotent(): void
    {
        $this->seedSetting(self::SEASON_YEAR);
        $this->seedJuneRow(self::SEASON_YEAR, 50007);
        $this->seedJuneRow(self::SEASON_YEAR, 50008);

        $firstAffected = $this->applyMigration();
        $secondAffected = $this->applyMigration();

        self::assertSame(2, $firstAffected, 'first run must delete both phantom rows');
        self::assertSame(0, $secondAffected, 'second run must be a genuine no-op');
    }

    public function testMigrationIsANoOpWhenTheSeasonEndingYearSettingIsAbsent(): void
    {
        // No setting inserted — sub-select returns NULL; WHERE season_year = NULL matches nothing.
        $id = $this->seedJuneRow(self::SEASON_YEAR, 50009);

        $affected = $this->applyMigration();

        self::assertSame(0, $affected, 'migration without a setting row must affect nothing');
        self::assertRowExists($id, 'the seeded row must survive when setting is absent');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function applyMigration(): int
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/160_remove_stale_playoff_rows.sql');
        self::assertIsString($sql);
        self::assertTrue($this->db->multi_query($sql), $this->db->error);
        $affected = $this->db->affected_rows;
        while ($this->db->more_results()) {
            $this->db->next_result();
        }
        return $affected;
    }

    private function seedSetting(int $endingYear): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ibl_settings (setting_key, value, league) VALUES ('Current Season Ending Year', ?, 'ibl')"
        );
        self::assertNotFalse($stmt, 'Failed to prepare setting insert: ' . $this->db->error);
        $val = (string) $endingYear;
        $stmt->bind_param('s', $val);
        $stmt->execute();
        $stmt->close();
    }

    private function clearSetting(): void
    {
        $this->db->query("DELETE FROM ibl_settings WHERE setting_key = 'Current Season Ending Year' AND league = 'ibl'");
    }

    private function seedJuneRow(int $year, int $boxId): int
    {
        return $this->insertScheduleRow($year, "{$year}-06-15", 1, 110, 2, 105, $boxId);
    }

    private function assertRowGone(int $id): void
    {
        $result = $this->db->query("SELECT id FROM ibl_schedule WHERE id = $id");
        self::assertNotFalse($result);
        self::assertSame(0, $result->num_rows, "row id=$id should have been deleted by migration 160");
        $result->free();
    }

    private function assertRowExists(int $id, string $message = ''): void
    {
        $result = $this->db->query("SELECT id FROM ibl_schedule WHERE id = $id");
        self::assertNotFalse($result);
        self::assertSame(1, $result->num_rows, $message !== '' ? $message : "row id=$id should still exist");
        $result->free();
    }
}
