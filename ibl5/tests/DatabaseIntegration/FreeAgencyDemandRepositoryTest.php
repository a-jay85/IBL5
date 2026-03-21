<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use FreeAgency\FreeAgencyDemandRepository;

/**
 * Tests FreeAgencyDemandRepository against real MariaDB — team performance
 * lookups, position salary commitment via vw_current_salary, and player demands.
 */
class FreeAgencyDemandRepositoryTest extends DatabaseTestCase
{
    private FreeAgencyDemandRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FreeAgencyDemandRepository($this->db);
    }

    // ── getTeamPerformance ──────────────────────────────────────

    public function testGetTeamPerformanceReturnsPerformanceData(): void
    {
        // Metros is in the seed — set known Contract values within the transaction
        $stmt = $this->db->prepare(
            'UPDATE ibl_team_info SET Contract_Wins = ?, Contract_Losses = ?, Contract_AvgW = ?, Contract_AvgL = ? WHERE team_name = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('iiiis', $w, $l, $aw, $al, $tn);
        $w = 45;
        $l = 37;
        $aw = 42;
        $al = 40;
        $tn = 'Metros';
        $stmt->execute();
        $stmt->close();

        $result = $this->repo->getTeamPerformance('Metros');

        self::assertSame(45, $result['wins']);
        self::assertSame(37, $result['losses']);
        self::assertSame(42, $result['tradWins']);
        self::assertSame(40, $result['tradLosses']);
    }

    public function testGetTeamPerformanceReturnsZerosForUnknownTeam(): void
    {
        $result = $this->repo->getTeamPerformance('NoSuchTeam9999');

        self::assertSame(0, $result['wins']);
        self::assertSame(0, $result['losses']);
        self::assertSame(0, $result['tradWins']);
        self::assertSame(0, $result['tradLosses']);
    }

    // ── getPositionSalaryCommitment ─────────────────────────────

    public function testGetPositionSalaryCommitmentReturnsSumExcludingPlayer(): void
    {
        // Insert two PG players on Metros (tid=1) with known salaries
        // vw_current_salary computes next_year_salary: when cy=1, next_year=cy2
        $this->insertTestPlayer(200070001, 'FA Demand PG1', [
            'tid' => 1, 'pos' => 'PG', 'cy' => 1, 'cyt' => 3, 'cy1' => 1500, 'cy2' => 1600,
        ]);
        $this->insertTestPlayer(200070002, 'FA Demand PG2', [
            'tid' => 1, 'pos' => 'PG', 'cy' => 1, 'cyt' => 3, 'cy1' => 2000, 'cy2' => 2100,
        ]);

        // Exclude player 200070001 — should return only 200070002's next_year_salary (2100)
        $result = $this->repo->getPositionSalaryCommitment('Metros', 'PG', 200070001);

        // Result includes any pre-existing PG players on Metros + our test player 200070002
        self::assertGreaterThanOrEqual(2100, $result);
    }

    // ── getPlayerDemands ────────────────────────────────────────

    public function testGetPlayerDemandsReturnsDemandValues(): void
    {
        $this->insertTestPlayer(200070003, 'FA Demand Plyr');
        $this->insertDemandRow('FA Demand Plyr', 200070003, ['dem1' => 3000, 'dem3' => 2500]);

        $result = $this->repo->getPlayerDemands(200070003);

        self::assertSame(3000, $result['dem1']);
        self::assertSame(2500, $result['dem3']);
        self::assertSame(0, $result['dem2']);
    }

    public function testGetPlayerDemandsReturnsZerosWhenNotFound(): void
    {
        $result = $this->repo->getPlayerDemands(999999999);

        self::assertSame(0, $result['dem1']);
        self::assertSame(0, $result['dem2']);
        self::assertSame(0, $result['dem3']);
        self::assertSame(0, $result['dem4']);
        self::assertSame(0, $result['dem5']);
        self::assertSame(0, $result['dem6']);
    }
}
