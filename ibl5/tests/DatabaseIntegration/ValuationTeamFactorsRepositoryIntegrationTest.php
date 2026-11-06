<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeSuggestions\Valuation\ValuationTeamFactorsRepository;

/**
 * Runs the loader's two statements against real tables, with one team's
 * factors and one cash row staged inside the test transaction.
 */
#[Group('database')]
final class ValuationTeamFactorsRepositoryIntegrationTest extends DatabaseTestCase
{
    private const int STAGED_TID = 2;
    private const int NON_FRANCHISE_TID = 998;

    public function testLoadsStagedTeamFactorsAndCashRows(): void
    {
        $this->db->query(
            'UPDATE ibl_team_info SET contract_wins = 50, contract_losses = 32, contract_avg_w = 44, contract_avg_l = 38 WHERE teamid = ' . self::STAGED_TID
        );
        $this->db->query('DELETE FROM ibl_cash_considerations WHERE teamid = ' . self::STAGED_TID);
        $this->insertRow('ibl_cash_considerations', [
            'teamid' => self::STAGED_TID,
            'cy' => 1,
            'cyt' => 2,
            'salary_yr1' => 75,
            'salary_yr2' => 80,
        ]);
        $this->insertRow('ibl_team_info', [
            'teamid' => self::NON_FRANCHISE_TID,
            'team_city' => 'Test',
            'team_name' => 'Exhibition',
            'contract_wins' => 99,
        ]);

        $repo = new ValuationTeamFactorsRepository($this->db);
        $allFactors = $repo->getTeamFactors();
        $factors = $allFactors[self::STAGED_TID];

        self::assertArrayNotHasKey(self::NON_FRANCHISE_TID, $allFactors);
        self::assertSame(self::STAGED_TID, $factors->tid);
        self::assertSame(50, $factors->wins);
        self::assertSame(32, $factors->losses);
        self::assertSame(44, $factors->tradWins);
        self::assertSame(38, $factors->tradLosses);
        self::assertSame(
            [0 => 75, 1 => 80, 2 => 0, 3 => 0],
            $repo->getCashByTeamAndOffset(false)[self::STAGED_TID],
        );
    }
}
