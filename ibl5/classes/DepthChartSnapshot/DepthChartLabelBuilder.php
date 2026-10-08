<?php

declare(strict_types=1);

namespace DepthChartSnapshot;

use DepthChartSnapshot\Contracts\DepthChartLabelBuilderInterface;
use Season\Season;

/**
 * @phpstan-import-type SavedDepthChartRow from Contracts\DepthChartSnapshotRepositoryInterface
 *
 * @see DepthChartLabelBuilderInterface
 */
final class DepthChartLabelBuilder implements DepthChartLabelBuilderInterface
{
    private const DROPDOWN_SEPARATOR = ' | ';
    private const LIVE_SEPARATOR = ' ∙ ';
    private const DATE_FORMAT = 'M j';

    /**
     * @see DepthChartLabelBuilderInterface::buildDropdownLabel()
     * @param SavedDepthChartRow $dc
     * @param array{wins: int, losses: int} $record
     */
    public function buildDropdownLabel(array $dc, Season $season, array $record): string
    {
        $parts = [];
        if ($dc['name'] !== null && $dc['name'] !== '') {
            $parts[] = $dc['name'];
        }

        $phaseSimStart = $season->calculatePhaseSimNumber($dc['sim_number_start'], $dc['phase'], $dc['season_year']);
        if ($dc['sim_number_end'] !== null) {
            $phaseSimEnd = $season->calculatePhaseSimNumber($dc['sim_number_end'], $dc['phase'], $dc['season_year']);
            $parts[] = $this->formatSimRange($phaseSimStart, $phaseSimEnd);
        } else {
            $parts[] = 'Sim ' . $phaseSimStart;
        }

        $startDate = (new \DateTime($dc['sim_start_date']))->format(self::DATE_FORMAT);
        $endDateStr = $dc['sim_end_date'] !== null
            ? (new \DateTime($dc['sim_end_date']))->format(self::DATE_FORMAT)
            : '?';
        $parts[] = $startDate . ' - ' . $endDateStr;
        $parts[] = '(' . $record['wins'] . '-' . $record['losses'] . ')';

        return implode(self::DROPDOWN_SEPARATOR, $parts);
    }

    /**
     * @see DepthChartLabelBuilderInterface::buildLiveLabel()
     * @param SavedDepthChartRow|null $activeDc
     * @param array{wins: int, losses: int}|null $record
     */
    public function buildLiveLabel(?array $activeDc, Season $season, ?array $record): string
    {
        $currentPhaseSim = $season->getPhaseSpecificSimNumber();
        $parts = [];

        if ($activeDc !== null && $activeDc['name'] !== null && $activeDc['name'] !== '') {
            $parts[] = $activeDc['name'] . ' (Live)';
        } else {
            $parts[] = 'Current (Live)';
        }

        if ($activeDc !== null) {
            $phaseSimStart = $season->calculatePhaseSimNumber(
                $activeDc['sim_number_start'],
                $activeDc['phase'],
                $activeDc['season_year']
            );
            $parts[] = $this->formatSimRange($phaseSimStart, $currentPhaseSim);

            // Projected end when no sim has consumed this DC yet
            $rawStartDate = $activeDc['sim_start_date'];
            $rawEndDate = $season->lastSimEndDate;
            if ($rawStartDate > $rawEndDate) {
                $rawEndDate = $season->projectedNextSimEndDate->format('Y-m-d');
            }
            $startDate = (new \DateTime($rawStartDate))->format(self::DATE_FORMAT);
            $endDate = (new \DateTime($rawEndDate))->format(self::DATE_FORMAT);
        } else {
            $parts[] = 'Sim ' . $currentPhaseSim;
            $startDate = (new \DateTime($season->lastSimStartDate))->format(self::DATE_FORMAT);
            $endDate = (new \DateTime($season->lastSimEndDate))->format(self::DATE_FORMAT);
        }

        $parts[] = $startDate . ' - ' . $endDate;

        if ($record !== null) {
            $parts[] = '(' . $record['wins'] . '-' . $record['losses'] . ')';
        }

        return implode(self::LIVE_SEPARATOR, $parts);
    }

    /** "Sim N" for a single sim, "Sims A-B" for a range. */
    private function formatSimRange(int $start, int $end): string
    {
        if ($start === $end) {
            return 'Sim ' . $start;
        }

        return 'Sims ' . $start . '-' . $end;
    }
}
