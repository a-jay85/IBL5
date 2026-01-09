<?php

declare(strict_types=1);

namespace SeriesRecords;

use SeriesRecords\Contracts\SeriesRecordsServiceInterface;

/**
 * SeriesRecordsService - Business logic for series records
 * 
 * Handles transformation of raw series records data into structured formats
 * and provides logic for determining record status for display styling.
 * 
 * @see SeriesRecordsServiceInterface
 */
class SeriesRecordsService implements SeriesRecordsServiceInterface
{
    /** @var string Background color for winning records */
    private const COLOR_WINNING = '#8f8';

    /** @var string Background color for losing records */
    private const COLOR_LOSING = '#f88';

    /** @var string Background color for tied records */
    private const COLOR_TIED = '#bbb';

    /**
     * @see SeriesRecordsServiceInterface::buildSeriesMatrix()
     */
    public function buildSeriesMatrix(array $seriesRecords): array
    {
        $matrix = [];

        foreach ($seriesRecords as $record) {
            $self = (int) $record['self'];
            $opponent = (int) $record['opponent'];

            if (!isset($matrix[$self])) {
                $matrix[$self] = [];
            }

            $matrix[$self][$opponent] = [
                'wins' => (int) $record['wins'],
                'losses' => (int) $record['losses'],
            ];
        }

        return $matrix;
    }

    /**
     * @see SeriesRecordsServiceInterface::getRecordStatus()
     */
    public function getRecordStatus(int $wins, int $losses): string
    {
        if ($wins > $losses) {
            return 'winning';
        } elseif ($wins < $losses) {
            return 'losing';
        }
        return 'tied';
    }

    /**
     * @see SeriesRecordsServiceInterface::getRecordBackgroundColor()
     */
    public function getRecordBackgroundColor(int $wins, int $losses): string
    {
        $status = $this->getRecordStatus($wins, $losses);

        return match ($status) {
            'winning' => self::COLOR_WINNING,
            'losing' => self::COLOR_LOSING,
            default => self::COLOR_TIED,
        };
    }

    /**
     * Get the record for a specific matchup from the matrix
     * 
     * @param array<int, array<int, array<string, int>>> $matrix Series matrix
     * @param int $selfTeamId Team ID for the "self" team
     * @param int $opponentTeamId Team ID for the opponent team
     * @return array<string, int> Array with 'wins' and 'losses' keys
     */
    public function getRecordFromMatrix(array $matrix, int $selfTeamId, int $opponentTeamId): array
    {
        return $matrix[$selfTeamId][$opponentTeamId] ?? ['wins' => 0, 'losses' => 0];
    }
}
