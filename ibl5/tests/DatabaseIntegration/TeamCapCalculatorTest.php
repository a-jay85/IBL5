<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use League\League;
use Team\TeamCapCalculator;
use Team\TeamQueryRepository;
use Season\Season;

/**
 * Characterization + behavior coverage for the cap-compliance logic extracted
 * out of {@see TeamQueryRepository} into {@see TeamCapCalculator}. These cases
 * were moved verbatim from TeamQueryRepositoryTest so the extraction is provably
 * behavior-preserving (same boundary verdicts, same aggregate sums).
 */
#[Group('database')]
class TeamCapCalculatorTest extends DatabaseTestCase
{
    private TeamCapCalculator $calculator;
    private TeamQueryRepository $repo;

    /** Team ID used for test data — must be a real team in the DB (1-28) */
    private const TEST_TID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamQueryRepository($this->db);
        $this->calculator = new TeamCapCalculator($this->db, $this->repo);
    }

    // --- Salary Cap ---

    public function testGetSalaryCapArrayCalculatesMultiYearBreakdown(): void
    {
        $season = new Season($this->db);

        $result = $this->calculator->getSalaryCapArray('Test', self::TEST_TID, $season);

        self::assertIsArray($result);
        // Should have at least year1 if there are players under contract
        if ($result !== []) {
            self::assertArrayHasKey('year1', $result);
            self::assertGreaterThan(0, $result['year1']);
        }
    }

    // --- Hard Cap Check ---

    public function testCanAddContractWithoutGoingOverHardCapReturnsCorrectly(): void
    {
        // Adding 0 should always be under the cap
        $resultUnder = $this->calculator->canAddContractWithoutGoingOverHardCap(self::TEST_TID, 0);
        self::assertTrue($resultUnder);

        // Adding the entire hard cap should exceed it (since team already has players)
        $resultOver = $this->calculator->canAddContractWithoutGoingOverHardCap(self::TEST_TID, League::HARD_CAP_MAX);
        self::assertFalse($resultOver);
    }

    // ── getTotalCurrentSeasonSalaries ───────────────────────────

    public function testGetTotalCurrentSeasonSalariesSumsContracts(): void
    {
        // Use a real team with isolated test player. First clear any existing
        // salary_yr1>0 players on team 28 to get a predictable sum.
        $this->db->query('UPDATE ibl_plr SET salary_yr1 = 0 WHERE teamid = 28');

        $this->insertTestPlayer(200000150, 'TQ CurSal', [
            'teamid' => 28,
            'cy' => 1,
            'cyt' => 2,
            'salary_yr1' => 3000,
            'salary_yr2' => 3200,
        ]);

        $rows = $this->repo->getAllPlayersUnderContract(28);
        $total = $this->calculator->getTotalCurrentSeasonSalaries($rows);

        self::assertIsInt($total);
        self::assertSame(3000, $total);
    }

    // ── getTotalNextSeasonSalaries ──────────────────────────────

    public function testGetTotalNextSeasonSalariesSumsContracts(): void
    {
        $this->db->query('UPDATE ibl_plr SET salary_yr1 = 0 WHERE teamid = 28');

        $this->insertTestPlayer(200000151, 'TQ NxtSal', [
            'teamid' => 28,
            'cy' => 1,
            'cyt' => 2,
            'salary_yr1' => 1500,
            'salary_yr2' => 2200,
        ]);

        $rows = $this->repo->getAllPlayersUnderContract(28);
        $total = $this->calculator->getTotalNextSeasonSalaries($rows);

        self::assertIsInt($total);
        self::assertSame(2200, $total);
    }

    // ── canAddBuyoutWithoutExceedingBuyoutLimit ─────────────────

    public function testCanAddBuyoutWithinLimitReturnsTrue(): void
    {
        // Passing the Season the caller already holds reproduces the prior
        // internal `new Season($this->db)` path exactly (same verdict).
        $season = new Season($this->db);
        // Team 99999 has no players → buyout sum is 0, adding 0 is within limit
        self::assertTrue($this->calculator->canAddBuyoutWithoutExceedingBuyoutLimit(99999, 0, $season));
    }

    public function testCanAddBuyoutExceedingLimitReturnsFalse(): void
    {
        $season = new Season($this->db);
        // Adding the entire hard cap always exceeds the buyout percentage limit
        self::assertFalse($this->calculator->canAddBuyoutWithoutExceedingBuyoutLimit(99999, League::HARD_CAP_MAX, $season));
    }
}
