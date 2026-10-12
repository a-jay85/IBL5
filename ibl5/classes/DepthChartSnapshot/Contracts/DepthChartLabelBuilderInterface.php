<?php

declare(strict_types=1);

namespace DepthChartSnapshot\Contracts;

use Season\Season;

/**
 * Pure string assembly for depth-chart dropdown and live labels.
 * Callers pre-fetch the depth-chart row and win-loss record; the builder never reads the DB itself.
 *
 * @phpstan-import-type SavedDepthChartRow from DepthChartSnapshotRepositoryInterface
 */
interface DepthChartLabelBuilderInterface
{
    /**
     * Saved-DC dropdown label, parts joined with " | ":
     * [name] | Sim N or Sims A-B | M j - (M j or ?) | (W-L). Name omitted when null or ''.
     *
     * @param SavedDepthChartRow $dc
     * @param array{wins: int, losses: int} $record
     */
    public function buildDropdownLabel(array $dc, Season $season, array $record): string;

    /**
     * "Current (Live)" label, parts joined with " ∙ ".
     * With an active DC: "<name> (Live)" (or "Current (Live)" when unnamed), phase sim range from the DC start
     * through the current sim ("Sim N" when the DC starts on or after the current sim, N = the DC's start sim),
     * date range (projected next-sim end when the DC starts after the last sim end),
     * then "(W-L)" when $record is non-null. Without one: "Current (Live)", "Sim N", last sim's date range.
     *
     * @param SavedDepthChartRow|null $activeDc
     * @param array{wins: int, losses: int}|null $record null when there is no active DC
     */
    public function buildLiveLabel(?array $activeDc, Season $season, ?array $record): string;
}
