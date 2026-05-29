<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use RookieOption\RookieOptionRepository;

/**
 * Verifies rookie-option exercise writes the correct contract column.
 *
 * The existing RookieOptionRepositoryTest confirms the target column is SET.
 * This test additionally asserts the *other* salary column is NOT mutated,
 * killing the column-swap mutant (`salary_yr4` ↔ `salary_yr3`) that would
 * survive a test checking only the written column.
 *
 * Key sources:
 *   classes/RookieOption/RookieOptionRepository.php:19  — column selection branch
 *   tests/DatabaseIntegration/DatabaseTestCase.php:213  — insertTestPlayer helper
 */
#[Group('database')]
class RookieOptionExerciseTest extends DatabaseTestCase
{
    private RookieOptionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new RookieOptionRepository($this->db);
    }

    /**
     * Round-1 pick: salary_yr4 must be updated to the extension amount
     * AND salary_yr3 must remain at the seeded value (500) — ensuring the
     * column-selection branch picks `salary_yr4`, not `salary_yr3`.
     */
    public function testRound1ExerciseSetsYr4AndLeavesYr3Unchanged(): void
    {
        $pid = 900001;
        $this->insertTestPlayer($pid, 'Exercise Rnd1 Player', [
            'draftround' => 1,
            'exp' => 2,
            'salary_yr3' => 500,
            'salary_yr4' => 0,
            'teamid' => 1,
        ]);

        $result = $this->repo->updatePlayerRookieOption($pid, 1, 1200);

        self::assertTrue($result);

        $stmt = $this->db->prepare('SELECT salary_yr3, salary_yr4 FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        // Primary assertion: extension amount written to yr4
        self::assertSame(1200, (int) $row['salary_yr4']);
        // Guard assertion: yr3 untouched (kills column-swap mutant)
        self::assertSame(500, (int) $row['salary_yr3']);
    }

    /**
     * Round-2 pick: salary_yr3 must be updated to the extension amount
     * AND salary_yr4 must remain at the seeded value (400) — ensuring the
     * column-selection branch picks `salary_yr3`, not `salary_yr4`.
     *
     * @see classes/RookieOption/RookieOptionRepository.php:19
     */
    public function testRound2ExerciseSetsYr3AndLeavesYr4Unchanged(): void
    {
        $pid = 900002;
        $this->insertTestPlayer($pid, 'Exercise Rnd2 Player', [
            'draftround' => 2,
            'exp' => 2,
            'salary_yr3' => 0,
            'salary_yr4' => 400,
            'teamid' => 1,
        ]);

        $result = $this->repo->updatePlayerRookieOption($pid, 2, 950);

        self::assertTrue($result);

        $stmt = $this->db->prepare('SELECT salary_yr3, salary_yr4 FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        // Primary assertion: extension amount written to yr3
        self::assertSame(950, (int) $row['salary_yr3']);
        // Guard assertion: yr4 untouched (kills column-swap mutant)
        self::assertSame(400, (int) $row['salary_yr4']);
    }
}
