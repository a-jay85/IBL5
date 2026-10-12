<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Verifies the WHERE clause of migration 185, which deletes blank-slot rows
 * (unused .sco game slots decoded as "<season>-10-01, team 1 @ team 1, game 1")
 * from schedule_guard_rejects.
 *
 * The DELETE statement is read from the migration file at test time, so the test
 * cannot drift from the shipped SQL. One true blank-slot row is seeded alongside
 * one near-miss row per guard (reason, visitor, home, game_of_that_day, game_date);
 * only the true blank row may be deleted.
 *
 * Rows are tagged through source_archive so assertions ignore any other rows in the
 * table. DatabaseTestCase rolls back each test's transaction.
 */
#[Group('database')]
final class ScheduleGuardRejectsBlankSlot185Test extends DatabaseTestCase
{
    private const MIGRATION = '185_delete_blank_slot_schedule_guard_rejects.sql';
    private const TAG_PREFIX = 'test185-';
    private const SEASON_YEAR = 2009;
    private const BLANK_REASON = 'preseason_shift_not_in_schedule';

    #[Test]
    public function deletesOnlyTheBlankSlotRowAndKeepsEveryNearMiss(): void
    {
        $this->seed('blank', []);
        $this->seed('other-reason', ['reason' => 'duplicate-triple']);
        $this->seed('visitor-not-1', ['visitor_teamid' => 2]);
        $this->seed('home-not-1', ['home_teamid' => 2]);
        $this->seed('game-of-day-not-1', ['game_of_that_day' => 2]);
        $this->seed('date-not-oct-1', ['game_date' => self::SEASON_YEAR . '-10-02']);
        $this->seed('date-wrong-year', ['game_date' => (self::SEASON_YEAR - 1) . '-10-01']);

        self::assertCount(7, $this->surviving(), 'all seeded rows present before the migration');

        $this->db->query($this->loadMigrationDelete());
        self::assertSame(0, $this->db->errno, 'migration DELETE failed: ' . $this->db->error);
        self::assertSame(1, $this->db->affected_rows, 'exactly one row (the blank slot) is deleted');

        self::assertSame(
            ['date-not-oct-1', 'date-wrong-year', 'game-of-day-not-1', 'home-not-1', 'other-reason', 'visitor-not-1'],
            $this->surviving(),
            'every near-miss survives; the blank row is gone'
        );
    }

    #[Test]
    public function migrationDeleteMatchesBlankRowOnEachSeasonYear(): void
    {
        // game_date is derived from the row's own season_year, so a blank slot for
        // season 2008 (2008-10-01) must match while season 2009 / 2008-10-01 must not.
        $this->seed('blank-2008', ['season_year' => 2008, 'game_date' => '2008-10-01']);
        $this->seed('mismatch', ['season_year' => 2009, 'game_date' => '2008-10-01']);

        $this->db->query($this->loadMigrationDelete());

        self::assertSame(['mismatch'], $this->surviving());
    }

    /**
     * Read the migration and return its single DELETE statement, with comments and
     * the trailing semicolon stripped.
     */
    private function loadMigrationDelete(): string
    {
        $path = dirname(__DIR__, 2) . '/migrations/' . self::MIGRATION;
        $contents = file_get_contents($path);
        self::assertNotFalse($contents, "cannot read $path");

        $lines = array_filter(
            explode("\n", $contents),
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );
        $sql = rtrim(trim(implode("\n", $lines)), ';');

        self::assertStringStartsWith('DELETE FROM schedule_guard_rejects', $sql);
        self::assertStringNotContainsString(';', $sql, 'migration is expected to hold one statement');

        return $sql;
    }

    /**
     * Seed a schedule_guard_rejects row that is a true blank slot unless overridden.
     *
     * @param array<string, int|string> $overrides
     */
    private function seed(string $label, array $overrides): void
    {
        $this->insertRow('schedule_guard_rejects', array_merge([
            'season_year' => self::SEASON_YEAR,
            'game_date' => self::SEASON_YEAR . '-10-01',
            'visitor_teamid' => 1,
            'home_teamid' => 1,
            'game_of_that_day' => 1,
            'reason' => self::BLANK_REASON,
            'source_archive' => self::TAG_PREFIX . $label,
        ], $overrides));
    }

    /**
     * @return list<string> sorted labels of the seeded rows still in the table
     */
    private function surviving(): array
    {
        $like = self::TAG_PREFIX . '%';
        $stmt = $this->db->prepare('SELECT source_archive FROM schedule_guard_rejects WHERE source_archive LIKE ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);

        $labels = [];
        while ($row = $result->fetch_assoc()) {
            $labels[] = substr((string) $row['source_archive'], strlen(self::TAG_PREFIX));
        }
        $stmt->close();
        sort($labels);

        return $labels;
    }
}
