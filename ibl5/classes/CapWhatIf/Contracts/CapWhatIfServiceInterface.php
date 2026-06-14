<?php

declare(strict_types=1);

namespace CapWhatIf\Contracts;

use Season\Season;

/**
 * CapWhatIfServiceInterface - hypothetical cap-scenario computation.
 *
 * Computes a sandbox scenario from a GM's real current contracts plus two
 * optional hypothetical deltas (waive one rostered player, add one signing of
 * N years at $X). Nothing is persisted; the result is derived entirely from the
 * passed inputs via the league's authoritative cap walk.
 *
 * @phpstan-type CapYearMap array<string, int>
 * @phpstan-type CapSide array{spent: CapYearMap, space: CapYearMap}
 * @phpstan-type ScenarioResult array{baseline: CapSide, scenario: CapSide, overCap: array<string, bool>, waivedName: ?string, years: int, salary: int}
 */
interface CapWhatIfServiceInterface
{
    /**
     * Compute the baseline-vs-scenario cap totals for a team.
     *
     * @param string $teamName Owner's team name (resolved server-side)
     * @param int $teamId Owner's team id (resolved server-side, never from request)
     * @param Season $season Current season (drives offseason contract-year rollover)
     * @param int|null $waivePid Hypothetically waived player id, or null for no waive
     * @param int $years Hypothetical signing length in years (clamped 0–6)
     * @param int $salary Hypothetical flat per-year salary (clamped 0–HARD_CAP_MAX)
     * @return ScenarioResult
     */
    public function computeScenario(
        string $teamName,
        int $teamId,
        Season $season,
        ?int $waivePid,
        int $years,
        int $salary
    ): array;
}
