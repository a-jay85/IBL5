<?php

declare(strict_types=1);

namespace Team\Contracts;

use Season\Season;

/**
 * TeamCapCalculatorInterface - Salary-cap compliance decisions for a team
 *
 * Owns the cap *business logic* (hard-cap / buyout-limit verdicts and salary
 * aggregation) that was previously embedded in {@see TeamQueryRepositoryInterface}.
 * The repository now exposes only raw row/aggregate fetches; this collaborator
 * turns those fetches into cap decisions. Mirrors the calculator-collaborator
 * shape of {@see \FreeAgency\FreeAgencyCapCalculator} (ADR-0028: a domain
 * calculator, not a generic Service/Shared bucket).
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 *
 * @phpstan-type CapContractRow array{cy?: int|null, cyt?: int|null, salary_yr1?: int|null, salary_yr2?: int|null, salary_yr3?: int|null, salary_yr4?: int|null, salary_yr5?: int|null, salary_yr6?: int|null, ...}
 */
interface TeamCapCalculatorInterface
{
    /**
     * Get salary cap array for all contract years
     *
     * @return array<string, int> Array of salary cap spent by year
     */
    public function getSalaryCapArray(string $teamName, int $teamId, Season $season): array;

    /**
     * Get salary cap array for all contract years from passed-in contract rows.
     *
     * Runs the same per-season cap walk as {@see self::getSalaryCapArray()} but over
     * caller-supplied contract rows, so hypothetical scenarios (waived / added
     * contracts) can be costed with the league's authoritative cap math. Cash
     * considerations are still pulled by {@see $teamId} (the what-if mutates only
     * contracts, never cash).
     *
     * @param list<CapContractRow> $contractRows Contract rows to walk (real or hypothetical)
     * @return array<string, int> Array of salary cap spent by year
     */
    public function getSalaryCapArrayFromContractRows(array $contractRows, int $teamId, Season $season): array;

    /**
     * Get total current season salaries from player result array
     *
     * @param list<PlayerRow> $result Array of player rows
     * @return int Total current season salaries
     */
    public function getTotalCurrentSeasonSalaries(array $result): int;

    /**
     * Get total next season salaries from player result array
     *
     * @param list<array<string, mixed>> $result Array of player rows
     * @return int Total next season salaries
     */
    public function getTotalNextSeasonSalaries(array $result): int;

    /**
     * Check if team can add contract without going over hard cap
     *
     * @param int $contractValue Contract value to add
     * @return bool True if under hard cap, false otherwise
     */
    public function canAddContractWithoutGoingOverHardCap(int $teamId, int $contractValue): bool;

    /**
     * Check if team can add buyout without exceeding buyout limit
     *
     * @param int $buyoutValue Buyout value to add
     * @param Season $season Current season (used to determine contract-year rollover)
     * @return bool True if under buyout limit, false otherwise
     */
    public function canAddBuyoutWithoutExceedingBuyoutLimit(int $teamId, int $buyoutValue, Season $season): bool;
}
