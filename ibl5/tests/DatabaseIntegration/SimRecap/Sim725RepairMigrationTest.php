<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Exercises the shipped migration file 157 against seeded fixtures. Each test
 * seeds its own rows, applies the real migration, and rolls back — the migration
 * is pure DML so DatabaseTestCase's per-test transaction covers it.
 *
 * Applying the file itself (not a transcription) is deliberate: a test holding
 * its own copy of the SQL cannot detect the shipped file being wrong.
 */
#[Group('database')]
final class Sim725RepairMigrationTest extends DatabaseTestCase
{
    private SimSummaryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SimSummaryRepository($this->db);
    }

    public function testRepairAssignsBoxScoreIndicesToSim725(): void
    {
        $this->seedSim725InTheAllOnesShape();
        $this->seedBoxScoresAtIndices1To7();

        $this->applyMigration();

        $recaps = $this->repo->findGameRecaps(725);
        self::assertSame([1, 2, 3, 4, 5, 6, 7], array_column($recaps, 'game_of_that_day'));
    }

    public function testRepairIsIdempotent(): void
    {
        $this->seedSim725InTheAllOnesShape();
        $this->seedBoxScoresAtIndices1To7();

        $this->applyMigration();
        $afterFirst = $this->repo->findGameRecaps(725);

        $secondAffected = $this->applyMigration();
        $afterSecond = $this->repo->findGameRecaps(725);

        self::assertSame($afterFirst, $afterSecond, 'a second application must change nothing');
        self::assertSame(0, $secondAffected, 'the second application must be a genuine no-op');
    }

    public function testRepairTouchesNoOtherSim(): void
    {
        // Sim 724 carries the same seven matchups on a DIFFERENT date, all at index
        // 1, WITH box scores that would repair it — uniq_game (season_year, date,
        // teams, game_of_that_day) excludes sim, so 724 must use its own date or its
        // rows collide with 725's. Seeding 724's box scores makes it a repair
        // candidate, so its staying at index 1 proves WHERE gr.sim = 725 is doing
        // the work, not the absence of a join match.
        $this->seedAllOnesShape(724, '2025-03-01');
        $this->seedBoxScoresForDate('2025-03-01');
        $this->seedSim725InTheAllOnesShape();
        $this->seedBoxScoresAtIndices1To7();

        $this->applyMigration();

        $other = $this->repo->findGameRecaps(724);
        self::assertSame([1, 1, 1, 1, 1, 1, 1], array_column($other, 'game_of_that_day'), 'sim 724 must be untouched');

        $target = $this->repo->findGameRecaps(725);
        self::assertSame([1, 2, 3, 4, 5, 6, 7], array_column($target, 'game_of_that_day'), 'sim 725 must be repaired');
    }

    public function testRepairSkipsAMatchupCarryingTwoIndicesOnOneDate(): void
    {
        // Two matchups on 2025-02-15: (20,21) legitimately played twice (indices 2
        // and 5) and (22,23) once (index 1). Both stored in the all-ones shape.
        $games = [
            $this->recapRow(0, '2025-02-15', 20, 21, 1),
            $this->recapRow(1, '2025-02-15', 22, 23, 1),
        ];
        $this->repo->markDone(725, 'Intro.', 'Outro.', 'Recap.', $games, null);

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('2025-02-15', 20, 21, 2, 'DHa'), ('2025-02-15', 20, 21, 5, 'DHb')," .
            "        ('2025-02-15', 22, 23, 1, 'Single')"
        );

        // Must NOT abort on uniq_game: the HAVING guard excludes the ambiguous matchup.
        $this->applyMigration();

        $recaps = $this->repo->findGameRecaps(725);
        $byMatchup = [];
        foreach ($recaps as $row) {
            $byMatchup[$row['visitor_teamid'] . '-' . $row['home_teamid']] = $row['game_of_that_day'];
        }

        self::assertSame(1, $byMatchup['20-21'], 'the ambiguous matchup is left at its stored index, not collided');
        self::assertSame(1, $byMatchup['22-23'], 'the unambiguous matchup maps to its single box-score index');
    }

    public function testRepairedRowsAreAllDisplayable(): void
    {
        $this->seedSim725InTheAllOnesShape();
        $this->seedBoxScoresAtIndices1To7();

        $this->applyMigration();

        $displayable = $this->repo->findDisplayableGameRecaps(725);
        self::assertCount(7, $displayable, 'every repaired row must satisfy the display filter that rejected it');
    }

    public function testRepairIsANoOpWhenSim725HasNoRows(): void
    {
        // The state of every fresh CI database: no sim-725 rows at all.
        self::assertSame(0, $this->applyMigration(), 'applying to a database with no sim-725 rows affects nothing');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Applies the shipped migration file and returns the UPDATE's affected-row
     * count. mysqli::$affected_rows must be read immediately after multi_query() —
     * the migration is a single UPDATE, and draining the result set with
     * next_result() below resets affected_rows to -1.
     */
    private function applyMigration(): int
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/migrations/157_repair_sim725_game_of_that_day.sql');
        self::assertIsString($sql);
        self::assertTrue($this->db->multi_query($sql), $this->db->error);
        $affected = $this->db->affected_rows;
        while ($this->db->more_results()) {
            $this->db->next_result();
        }
        return $affected;
    }

    private function seedSim725InTheAllOnesShape(): void
    {
        $this->seedAllOnesShape(725, '2025-02-14');
    }

    /**
     * Seven distinct matchups on $date, every recap stored at game_of_that_day 1 —
     * the exact shape sim 725 hit. Distinct (visitor, home) pairs keep them off
     * uniq_game despite the shared index.
     */
    private function seedAllOnesShape(int $sim, string $date): void
    {
        $games = [
            $this->recapRow(0, $date, 1,  2,  1),
            $this->recapRow(1, $date, 3,  4,  1),
            $this->recapRow(2, $date, 5,  6,  1),
            $this->recapRow(3, $date, 7,  8,  1),
            $this->recapRow(4, $date, 9,  10, 1),
            $this->recapRow(5, $date, 11, 12, 1),
            $this->recapRow(6, $date, 13, 14, 1),
        ];
        $this->repo->markDone($sim, 'Intro.', 'Outro.', 'Recap.', $games, null);
    }

    private function seedBoxScoresAtIndices1To7(): void
    {
        $this->seedBoxScoresForDate('2025-02-14');
    }

    /**
     * Box scores for the seven all-ones matchups on $date, at indices 1..7 — the
     * archive the repair reads from.
     */
    private function seedBoxScoresForDate(string $date): void
    {
        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)" .
            " VALUES ('$date', 1, 2, 1, 'T1'), ('$date', 3, 4, 2, 'T2'), ('$date', 5, 6, 3, 'T3')," .
            "        ('$date', 7, 8, 4, 'T4'), ('$date', 9, 10, 5, 'T5'), ('$date', 11, 12, 6, 'T6')," .
            "        ('$date', 13, 14, 7, 'T7')"
        );
    }

    /**
     * @return array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}
     */
    private function recapRow(int $sortOrder, string $gameDate, int $visitor, int $home, int $gameOfThatDay): array
    {
        return [
            'season_year'      => 2025,
            'game_date'        => $gameDate,
            'visitor_teamid'   => $visitor,
            'home_teamid'      => $home,
            'game_of_that_day' => $gameOfThatDay,
            'box_id'           => 42,
            'sort_order'       => $sortOrder,
            'recap_text'       => "Recap for {$visitor} at {$home}.",
        ];
    }
}
