<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Valuation;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use TradeSuggestions\Valuation\ValuationTeamFactorsRepository;

/**
 * The repository is final, so these tests feed it canned rows through
 * MockDatabase, as unit 1's ProjectionPlayerRepositoryTest does.
 */
final class ValuationTeamFactorsRepositoryTest extends TestCase
{
    private MockDatabase $db;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
    }

    public function testTeamFactorsKeyedByTeamId(): void
    {
        $this->db->onQuery('FROM `?ibl_team_info', [
            ['teamid' => 1, 'contract_wins' => 50, 'contract_losses' => 32, 'contract_avg_w' => 44, 'contract_avg_l' => 38],
            ['teamid' => 5, 'contract_wins' => 20, 'contract_losses' => 62, 'contract_avg_w' => 30, 'contract_avg_l' => 52],
        ]);

        $factors = (new ValuationTeamFactorsRepository($this->db))->getTeamFactors();

        self::assertSame([1, 5], array_keys($factors));
        self::assertSame(1, $factors[1]->tid);
        self::assertSame(50, $factors[1]->wins);
        self::assertSame(32, $factors[1]->losses);
        self::assertSame(44, $factors[1]->tradWins);
        self::assertSame(38, $factors[1]->tradLosses);
        self::assertSame(5, $factors[5]->tid);
        self::assertSame(20, $factors[5]->wins);
    }

    public function testCashOffsetsFollowEffectiveContractYear(): void
    {
        $this->db->onQuery('FROM `?ibl_cash_considerations', [
            self::cashRow(3, 2, [2 => 100, 3 => 120, 4 => 140]),
        ]);
        $repo = new ValuationTeamFactorsRepository($this->db);

        self::assertSame([0 => 100, 1 => 120, 2 => 140, 3 => 0], $repo->getCashByTeamAndOffset(false)[3]);
        self::assertSame([0 => 120, 1 => 140, 2 => 0, 3 => 0], $repo->getCashByTeamAndOffset(true)[3]);
    }

    public function testCashBeyondSalaryYearSixIsZero(): void
    {
        $this->db->onQuery('FROM `?ibl_cash_considerations', [
            self::cashRow(3, 5, [5 => 10, 6 => 60]),
        ]);

        $cash = (new ValuationTeamFactorsRepository($this->db))->getCashByTeamAndOffset(true);

        self::assertSame([0 => 60, 1 => 0, 2 => 0, 3 => 0], $cash[3]);
    }

    public function testCashSumsMultipleRowsPerTeam(): void
    {
        $this->db->onQuery('FROM `?ibl_cash_considerations', [
            self::cashRow(3, 1, [1 => 10, 2 => 20]),
            self::cashRow(3, 1, [1 => 1, 2 => 2, 3 => 3]),
        ]);

        $cash = (new ValuationTeamFactorsRepository($this->db))->getCashByTeamAndOffset(false);

        self::assertSame([0 => 11, 1 => 22, 2 => 3, 3 => 0], $cash[3]);
    }

    public function testSelectsUsePreparedFetchAllWithNoInterpolatedInput(): void
    {
        $this->db->onQuery('FROM `?ibl_team_info', []);
        $this->db->onQuery('FROM `?ibl_cash_considerations', []);
        $repo = new ValuationTeamFactorsRepository($this->db);

        $repo->getTeamFactors();
        $repo->getCashByTeamAndOffset(true);

        $statements = $this->db->getPreparedQueries();
        self::assertCount(2, $statements);
        self::assertStringEndsWith('WHERE teamid BETWEEN 1 AND 28 ORDER BY teamid', $statements[0]);
        foreach ($statements as $statement) {
            self::assertStringNotContainsString('?', $statement);
            self::assertStringNotContainsString('$', $statement);
        }

        foreach (['getTeamFactors', 'getCashByTeamAndOffset'] as $method) {
            foreach ((new \ReflectionMethod($repo, $method))->getParameters() as $parameter) {
                self::assertNotSame('string', (string) $parameter->getType());
            }
        }
    }

    public function testTeamWithNoCashRowsIsAbsent(): void
    {
        $this->db->onQuery('FROM `?ibl_cash_considerations', [
            self::cashRow(3, 1, [1 => 10]),
            self::cashRow(5, 1, [1 => 20]),
        ]);

        $cash = (new ValuationTeamFactorsRepository($this->db))->getCashByTeamAndOffset(false);

        self::assertSame([3, 5], array_keys($cash));
        self::assertArrayNotHasKey(4, $cash);
    }

    /**
     * @param array<int, int> $salaries contract year => salary
     * @return array<string, int>
     */
    private static function cashRow(int $tid, int $cy, array $salaries): array
    {
        $row = ['teamid' => $tid, 'cy' => $cy, 'cyt' => 6];
        foreach ([1, 2, 3, 4, 5, 6] as $year) {
            $row['salary_yr' . $year] = $salaries[$year] ?? 0;
        }

        return $row;
    }
}
