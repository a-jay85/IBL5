<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation\Contracts;

use TradeSuggestions\Valuation\TeamFactors;

/**
 * League-wide valuation inputs that unit 1's player loader does not read.
 */
interface ValuationTeamFactorsRepositoryInterface
{
    /**
     * Every real team's `ibl_team_info.contract_*` factors.
     *
     * @return array<int, TeamFactors> keyed by tid
     */
    public function getTeamFactors(): array;

    /**
     * Cash considerations per team and horizon offset. A team with no rows is
     * absent, so callers read `?? 0`.
     *
     * @return array<int, array<int, int>> tid => offset 0..3 => cash salary
     */
    public function getCashByTeamAndOffset(bool $advancesContractYears): array;
}
