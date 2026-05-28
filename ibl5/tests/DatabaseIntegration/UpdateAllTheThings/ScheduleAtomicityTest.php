<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use PHPUnit\Framework\Attributes\Group;
use Season\Season;
use Updater\Contracts\JsbSourceResolverInterface;

/**
 * Verifies that ScheduleUpdater::update() rebuilds ibl_schedule atomically:
 * a mid-rebuild failure must leave the prior schedule intact rather than the
 * DELETE'd / partially re-inserted state that produced the prod truncation bug.
 */
#[Group('database')]
class ScheduleAtomicityTest extends PipelineIntegrationTestCase
{
    public function testFailedRebuildLeavesPriorScheduleIntact(): void
    {
        $this->updateSetting('Current Season Phase', 'Regular Season');
        $this->updateSetting('Current Season Ending Year', '2026');
        $this->seedSimDates(4, '2026-01-05', '2026-01-09');
        $this->seedSimDates(5, '2026-01-10', '2026-01-15');
        $this->seedLeagueConfig(2026);
        $season = $this->buildSeason('Regular Season', 2026);

        // Establish a known baseline schedule of exactly 3 rows.
        $this->db->query("DELETE FROM ibl_schedule");
        $this->seedScheduleRow('2026-02-01', 1, 2);
        $this->seedScheduleRow('2026-02-02', 3, 4);
        $this->seedScheduleRow('2026-02-03', 5, 6);
        self::assertSame(3, $this->countRows('ibl_schedule'), 'baseline should be 3 rows');

        // Second game's visitor_score = 201 violates chk_schedule_vscore (0..200),
        // forcing a failure AFTER the DELETE and after one good insert.
        $schPath = $this->buildSchFile([
            ['date_slot' => 103, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 100, 'home_score' => 95],
            ['date_slot' => 104, 'game_index' => 0, 'visitor' => 3, 'home' => 4, 'visitor_score' => 201, 'home_score' => 98],
        ]);
        $updater = $this->makeScheduleUpdater($season, $schPath);

        $threw = false;
        $level = ob_get_level();
        ob_start();
        try {
            $updater->update();
        } catch (\RuntimeException) {
            $threw = true;
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        self::assertTrue($threw, 'update() must throw when an insert violates a constraint');
        self::assertSame(
            3,
            $this->countRows('ibl_schedule'),
            'the DELETE must roll back — the prior 3-row schedule should be intact, not empty or partial',
        );
    }

    public function testSuccessfulRebuildCommitsFullSchedule(): void
    {
        $this->updateSetting('Current Season Phase', 'Regular Season');
        $this->updateSetting('Current Season Ending Year', '2026');
        $this->seedSimDates(4, '2026-01-05', '2026-01-09');
        $this->seedSimDates(5, '2026-01-10', '2026-01-15');
        $this->seedLeagueConfig(2026);
        $season = $this->buildSeason('Regular Season', 2026);

        // Jan 11 → date_slot 103, Jan 12 → date_slot 104.
        $schPath = $this->buildSchFile([
            ['date_slot' => 103, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 105, 'home_score' => 98],
            ['date_slot' => 103, 'game_index' => 1, 'visitor' => 3, 'home' => 4, 'visitor_score' => 110, 'home_score' => 102],
            ['date_slot' => 104, 'game_index' => 0, 'visitor' => 5, 'home' => 6, 'visitor_score' => 99, 'home_score' => 101],
        ]);
        $updater = $this->makeScheduleUpdater($season, $schPath);

        $level = ob_get_level();
        ob_start();
        try {
            $updater->update();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        self::assertSame(
            3,
            $this->countRows('ibl_schedule', "game_date LIKE '2026-01-%'"),
            'a successful rebuild should commit all parsed games',
        );
    }

    private function makeScheduleUpdater(Season $season, string $schPath): \Updater\ScheduleUpdater
    {
        $schResolver = $this->createStub(JsbSourceResolverInterface::class);
        $schResolver->method('getContents')->willReturnCallback(
            static function (string $ext) use ($schPath): ?string {
                if ($ext === 'sch' && is_file($schPath)) {
                    $data = file_get_contents($schPath);
                    return $data !== false ? $data : null;
                }
                return null;
            },
        );

        return new \Updater\ScheduleUpdater($this->db, $season, null, $schResolver);
    }

    private function seedScheduleRow(string $gameDate, int $visitor, int $home): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ibl_schedule
                (season_year, box_id, game_date, visitor_teamid, visitor_score, home_teamid, home_score, uuid)
             VALUES (2026, 100000, ?, ?, 0, ?, 0, ?)"
        );
        self::assertNotFalse($stmt);
        $uuid = bin2hex(random_bytes(8));
        $stmt->bind_param('siis', $gameDate, $visitor, $home, $uuid);
        $stmt->execute();
        $stmt->close();
    }
}
