<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Repositories\SalaryCapRepository;

/**
 * Tests SalaryCapRepository::getPositionSalaryCommitmentNextYear() against
 * real MariaDB via vw_current_salary.
 */
#[Group('database')]
class SalaryCapRepositoryTest extends DatabaseTestCase
{
    private SalaryCapRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SalaryCapRepository($this->db);
    }

    public function testGetPositionSalaryCommitmentNextYearExcludesByPid(): void
    {
        $baseline = $this->repo->getPositionSalaryCommitmentNextYear('Metros', 'PG', 999999999);
        $this->insertTestPlayer(200140001, 'SCR Commit PG1', [
            'teamid' => 1, 'pos' => 'PG', 'cy' => 1, 'cyt' => 3, 'salary_yr1' => 1500, 'salary_yr2' => 1600,
        ]);
        $this->insertTestPlayer(200140002, 'SCR Commit PG2', [
            'teamid' => 1, 'pos' => 'PG', 'cy' => 1, 'cyt' => 3, 'salary_yr1' => 2000, 'salary_yr2' => 2100,
        ]);

        self::assertSame($baseline + 2100, $this->repo->getPositionSalaryCommitmentNextYear('Metros', 'PG', 200140001));
    }

    public function testGetPositionSalaryCommitmentNextYearReturnsZeroForUnknownTeam(): void
    {
        self::assertSame(0, $this->repo->getPositionSalaryCommitmentNextYear('NoSuchTeam9999', 'PG', 200140001));
    }

    public function testGetPositionSalaryCommitmentNextYearKeepsSameNamedTeammate(): void
    {
        $baseline = $this->repo->getPositionSalaryCommitmentNextYear('Metros', 'PG', 999999999);
        $this->insertTestPlayer(200140003, 'SCR Dup Name', [
            'teamid' => 1, 'pos' => 'PG', 'cy' => 1, 'cyt' => 3, 'salary_yr1' => 1650, 'salary_yr2' => 1700,
        ]);
        $this->insertTestPlayer(200140004, 'SCR Dup Name', [
            'teamid' => 1, 'pos' => 'PG', 'cy' => 1, 'cyt' => 3, 'salary_yr1' => 1750, 'salary_yr2' => 1800,
        ]);

        self::assertSame($baseline + 1800, $this->repo->getPositionSalaryCommitmentNextYear('Metros', 'PG', 200140003));
    }
}
