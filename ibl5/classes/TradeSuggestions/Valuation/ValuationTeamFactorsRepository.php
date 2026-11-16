<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

use League\League;
use League\LeagueContext;
use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\Valuation\Contracts\ValuationTeamFactorsRepositoryInterface;
use Trading\BuyoutLedgerRepository;

/**
 * Read-only loader for team win/loss factors and cash considerations. Both
 * statements are constant strings with no bound input.
 *
 * @see ValuationTeamFactorsRepositoryInterface
 */
final class ValuationTeamFactorsRepository extends \Database\BaseMysqliRepository implements ValuationTeamFactorsRepositoryInterface
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * Reads real franchises only. The Rookies, Sophomores and All-Star rows have
     * positive ids and all-zero records, and `TeamFactors::average` would count them.
     *
     * @see ValuationTeamFactorsRepositoryInterface::getTeamFactors()
     *
     * @return array<int, TeamFactors>
     */
    public function getTeamFactors(): array
    {
        $rows = $this->fetchAll(
            "SELECT teamid, contract_wins, contract_losses, contract_avg_w, contract_avg_l FROM `ibl_team_info` WHERE teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . " ORDER BY teamid"
        );

        $factors = [];
        foreach ($rows as $row) {
            $tid = self::scalarToInt($row['teamid']);
            $factors[$tid] = new TeamFactors(
                $tid,
                self::scalarToInt($row['contract_wins']),
                self::scalarToInt($row['contract_losses']),
                self::scalarToInt($row['contract_avg_w']),
                self::scalarToInt($row['contract_avg_l']),
            );
        }

        return $factors;
    }

    /**
     * The contract year steps by one when `$advancesContractYears` is true. F2
     * fixes that flag for v3; `TeamCapCalculator::getSalaryCapArray` takes the
     * same step from `isOffseasonPhase()`. A year past salary_yr6 reads 0 because
     * `salaryForContractYear` has no column for it.
     *
     * @see ValuationTeamFactorsRepositoryInterface::getCashByTeamAndOffset()
     *
     * @return array<int, array<int, int>>
     */
    public function getCashByTeamAndOffset(bool $advancesContractYears): array
    {
        $rows = $this->fetchAll(
            "SELECT teamid, cy, cyt, salary_yr1, salary_yr2, salary_yr3, salary_yr4, salary_yr5, salary_yr6 FROM `ibl_cash_considerations` ORDER BY teamid, id"
        );

        $step = $advancesContractYears ? 1 : 0;

        $cash = [];
        foreach ($rows as $row) {
            $tid = self::scalarToInt($row['teamid']);
            $effectiveCy = self::scalarToInt($row['cy']) + $step;
            foreach (Horizon::OFFSETS as $offset) {
                $cash[$tid][$offset] = ($cash[$tid][$offset] ?? 0)
                    + BuyoutLedgerRepository::salaryForContractYear($row, $effectiveCy + $offset);
            }
        }

        return $cash;
    }
}
