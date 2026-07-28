<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Database integration tests for SimSummaryRepository methods that require a
 * real schema — specifically findBoxScoreTotals(), which sums quarter columns
 * from ibl_box_scores_teams.
 *
 * Each test runs inside the DatabaseTestCase transaction wrapper and is rolled
 * back automatically, so inserts here never persist.
 */
#[Group('database')]
final class SimSummaryRepositoryDbTest extends DatabaseTestCase
{
    private SimSummaryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SimSummaryRepository($this->db);
    }

    // ── findBoxScoreTotals() ───────────────────────────────────────────────────

    /**
     * Empty $dates guard: must return [] without touching the database.
     * MySQL rejects `IN ()` as a syntax error, so the early-return is load-bearing.
     */
    public function testFindBoxScoreTotalsReturnsEmptyArrayForEmptyDates(): void
    {
        $result = $this->repo->findBoxScoreTotals([]);
        self::assertSame([], $result);
    }

    /**
     * A normal regulation game (OT columns omitted, so they default to NULL).
     * Visitor: Q1=20, Q2=25, Q3=18, Q4=27 = 90.
     * Home:    Q1=22, Q2=19, Q3=24, Q4=30 = 95.
     * ibl_box_scores_teams stores one row per TEAM side; MAX() folds both rows.
     */
    public function testFindBoxScoreTotalsNormalGameReturnsSummedScore(): void
    {
        // Two rows per game (one per team side) — both carry identical game-level values.
        $this->insertRegulationRow('2099-01-15', 1, 1, 2, 20, 25, 18, 27, 22, 19, 24, 30);
        $this->insertRegulationRow('2099-01-15', 1, 1, 2, 20, 25, 18, 27, 22, 19, 24, 30);

        $result = $this->repo->findBoxScoreTotals(['2099-01-15']);

        self::assertArrayHasKey('2099-01-15|1|2|1', $result);
        $scores = $result['2099-01-15|1|2|1'];
        self::assertSame(90, $scores['visitor']);
        self::assertSame(95, $scores['home']);
    }

    /**
     * OT columns are NULL (omitted in the INSERT → default NULL in schema).
     * COALESCE(null, 0) must prevent the null from propagating into the sum.
     * The result must be a correct non-null integer, not null.
     */
    public function testFindBoxScoreTotalsNullOtColumnsYieldsCorrectRegulationTotal(): void
    {
        // Omitting OT columns from the INSERT leaves them NULL in the DB.
        $this->insertRegulationRow('2099-01-16', 1, 3, 4, 25, 22, 20, 23, 24, 21, 18, 27);
        $this->insertRegulationRow('2099-01-16', 1, 3, 4, 25, 22, 20, 23, 24, 21, 18, 27);

        $result = $this->repo->findBoxScoreTotals(['2099-01-16']);

        self::assertArrayHasKey('2099-01-16|3|4|1', $result);
        $scores = $result['2099-01-16|3|4|1'];
        // Visitor: 25+22+20+23 = 90 (OT NULL → 0 via COALESCE).
        self::assertSame(90, $scores['visitor'], 'null OT must not corrupt the regulation total');
        // Home: 24+21+18+27 = 90.
        self::assertSame(90, $scores['home'], 'null OT must not corrupt the regulation total');
    }

    /**
     * A date with no box-score rows must produce no key in the result.
     */
    public function testFindBoxScoreTotalsUnknownDateReturnsNoKey(): void
    {
        $result = $this->repo->findBoxScoreTotals(['2099-12-31']);

        self::assertSame([], $result);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Insert one regulation-game row into ibl_box_scores_teams.
     * OT columns are intentionally omitted — they default to NULL in the schema.
     *
     * Call twice per game (one per team side) to mirror production data shape.
     */
    private function insertRegulationRow(
        string $gameDate,
        int    $gotd,
        int    $visitorTeamid,
        int    $homeTeamid,
        int    $vq1,
        int    $vq2,
        int    $vq3,
        int    $vq4,
        int    $hq1,
        int    $hq2,
        int    $hq3,
        int    $hq4,
    ): void {
        $stmt = $this->db->prepare(
            "INSERT INTO `ibl_box_scores_teams`"
            . " (`game_date`, `name`, `game_of_that_day`, `visitor_teamid`, `home_teamid`,"
            . "  `visitor_q1_points`, `visitor_q2_points`, `visitor_q3_points`, `visitor_q4_points`,"
            . "  `home_q1_points`, `home_q2_points`, `home_q3_points`, `home_q4_points`)"
            . " VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if ($stmt === false) {
            self::fail('Failed to prepare INSERT for ibl_box_scores_teams: ' . $this->db->error);
        }

        $stmt->bind_param(
            'siiiiiiiiiiii',
            $gameDate,
            $gotd,
            $visitorTeamid,
            $homeTeamid,
            $vq1,
            $vq2,
            $vq3,
            $vq4,
            $hq1,
            $hq2,
            $hq3,
            $hq4
        );

        if ($stmt->execute() === false) {
            self::fail('Failed to INSERT test row into ibl_box_scores_teams: ' . $stmt->error);
        }
        $stmt->close();
    }
}
