<?php

declare(strict_types=1);

namespace CapWhatIf;

use CapWhatIf\Contracts\CapWhatIfServiceInterface;
use League\League;
use Season\Season;
use Team\Contracts\TeamCapCalculatorInterface;
use Team\Contracts\TeamQueryRepositoryInterface;

/**
 * CapWhatIfService - hypothetical cap-scenario computation (sandbox, no persistence).
 *
 * Starts from a GM's real contract rows and applies two optional deltas — waive
 * one rostered player and/or add one flat-salary signing of N years at $X — then
 * costs both the baseline and the resulting scenario through the league's
 * authoritative cap walk ({@see TeamCapCalculatorInterface::getSalaryCapArrayFromContractRows()}).
 * No SQL is added: baseline rows come from the existing roster query and cash
 * considerations stay pinned to the real team ledger.
 *
 * @phpstan-import-type ScenarioResult from CapWhatIfServiceInterface
 * @phpstan-import-type CapYearMap from CapWhatIfServiceInterface
 * @phpstan-import-type CapContractRow from TeamCapCalculatorInterface
 *
 * @see CapWhatIfServiceInterface
 */
class CapWhatIfService implements CapWhatIfServiceInterface
{
    /** Number of contract years the salary model supports (salary_yr1..salary_yr6). */
    private const MAX_YEARS = 6;

    /** Sentinel pid for the hypothetical signing — never collides with a real roster pid. */
    private const SYNTHETIC_PID = -1;

    private TeamQueryRepositoryInterface $teamQueryRepo;
    private TeamCapCalculatorInterface $teamCapCalculator;

    /**
     * @param \mysqli $db Database connection (used to construct default collaborators)
     * @param TeamQueryRepositoryInterface|null $teamQueryRepo Roster source (created internally if not provided)
     * @param TeamCapCalculatorInterface|null $teamCapCalculator Cap walk (created internally if not provided)
     */
    public function __construct(
        \mysqli $db,
        ?TeamQueryRepositoryInterface $teamQueryRepo = null,
        ?TeamCapCalculatorInterface $teamCapCalculator = null
    ) {
        $this->teamQueryRepo = $teamQueryRepo ?? new \Team\TeamQueryRepository($db);
        $this->teamCapCalculator = $teamCapCalculator ?? new \Team\TeamCapCalculator($db, $this->teamQueryRepo);
    }

    /**
     * @see CapWhatIfServiceInterface::computeScenario()
     *
     * @return ScenarioResult
     */
    public function computeScenario(
        string $teamName,
        int $teamId,
        Season $season,
        ?int $waivePid,
        int $years,
        int $salary
    ): array {
        $years = $this->clamp($years, 0, self::MAX_YEARS);
        $salary = $this->clamp($salary, 0, League::HARD_CAP_MAX);

        $rows = $this->teamQueryRepo->getRosterUnderContractOrderedByName($teamId);

        $baselineSpent = $this->normalizeYears(
            $this->teamCapCalculator->getSalaryCapArrayFromContractRows($rows, $teamId, $season)
        );

        // Apply the waive delta (no-op if the pid is not on the roster).
        $waivedName = null;
        $modifiedRows = [];
        foreach ($rows as $row) {
            if ($waivePid !== null && ($row['pid'] ?? null) === $waivePid && $waivedName === null) {
                $name = $row['name'] ?? null;
                $waivedName = is_string($name) ? $name : '';
                continue;
            }
            $modifiedRows[] = $row;
        }

        // Apply the add-signing delta (flat $X for N years), phase-aware so the
        // first paid year always lands in year1.
        if ($years >= 1 && $salary >= 1) {
            $modifiedRows[] = $this->buildSyntheticRow($season, $years, $salary);
        }

        $scenarioSpent = $this->normalizeYears(
            $this->teamCapCalculator->getSalaryCapArrayFromContractRows($modifiedRows, $teamId, $season)
        );

        $overCap = [];
        foreach ($scenarioSpent as $key => $spent) {
            $overCap[$key] = $spent > League::HARD_CAP_MAX;
        }

        return [
            'baseline' => [
                'spent' => $baselineSpent,
                'space' => $this->toSpace($baselineSpent),
            ],
            'scenario' => [
                'spent' => $scenarioSpent,
                'space' => $this->toSpace($scenarioSpent),
            ],
            'overCap' => $overCap,
            'waivedName' => $waivedName,
            'years' => $years,
            'salary' => $salary,
        ];
    }

    /**
     * Build the hypothetical signing row so its first paid year lands in year1
     * under both season phases. The cap walk starts at `cy` (regular) or `cy+1`
     * (offseason); seeding `cy = 1` (regular) / `cy = 0` (offseason) makes the
     * first walked year `salary_yr1` either way, and `cyt = years` walks exactly
     * `years` slots — never indexing past `salary_yr6`.
     *
     * @return CapContractRow
     */
    private function buildSyntheticRow(Season $season, int $years, int $salary): array
    {
        // Static keys keep the shape concrete (no dynamic-key @var cast). `cyt`
        // gates how many years the cap walk sums, so years beyond `$years` are
        // funded 0 and never read.
        return [
            'pid' => self::SYNTHETIC_PID,
            'cy' => $season->isOffseasonPhase() ? 0 : 1,
            'cyt' => $years,
            'salary_yr1' => $years >= 1 ? $salary : 0,
            'salary_yr2' => $years >= 2 ? $salary : 0,
            'salary_yr3' => $years >= 3 ? $salary : 0,
            'salary_yr4' => $years >= 4 ? $salary : 0,
            'salary_yr5' => $years >= 5 ? $salary : 0,
            'salary_yr6' => $years >= 6 ? $salary : 0,
        ];
    }

    /**
     * Materialize all six year keys (year1..year6), defaulting missing years to 0.
     * The cap walk emits a sparse array (only years with commitments), so callers
     * computing cap space must not index missing keys directly.
     *
     * @param array<string, int> $spent
     * @return CapYearMap
     */
    private function normalizeYears(array $spent): array
    {
        $normalized = [];
        for ($i = 1; $i <= self::MAX_YEARS; $i++) {
            $key = 'year' . $i;
            $normalized[$key] = $spent[$key] ?? 0;
        }

        return $normalized;
    }

    /**
     * Convert a per-year spent map into a per-year cap-space map.
     *
     * @param CapYearMap $spent
     * @return CapYearMap
     */
    private function toSpace(array $spent): array
    {
        $space = [];
        foreach ($spent as $key => $value) {
            $space[$key] = League::HARD_CAP_MAX - $value;
        }

        return $space;
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
