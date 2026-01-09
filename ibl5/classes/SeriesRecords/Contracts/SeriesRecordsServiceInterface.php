<?php

declare(strict_types=1);

namespace SeriesRecords\Contracts;

/**
 * SeriesRecordsServiceInterface - Contract for Series Records business logic
 * 
 * Handles transformation of raw series records data into structured formats
 * suitable for rendering. Provides logic for determining record status
 * (winning, losing, tied) for display styling.
 */
interface SeriesRecordsServiceInterface
{
    /**
     * Build a lookup matrix of series records indexed by team matchups
     * 
     * Transforms flat series records array into a 2D lookup structure
     * for efficient access when rendering the grid table.
     * 
     * @param array<int, array<string, mixed>> $seriesRecords Raw series records from repository
     * @return array<int, array<int, array<string, int>>> Matrix indexed by [selfTeamId][opponentTeamId]
     * 
     * **Return Structure:**
     * [
     *     1 => [
     *         2 => ['wins' => 15, 'losses' => 10],
     *         3 => ['wins' => 12, 'losses' => 8],
     *         ...
     *     ],
     *     2 => [...],
     *     ...
     * ]
     * 
     * **Behaviors:**
     * - Returns empty array if input is empty
     * - Missing matchups should return ['wins' => 0, 'losses' => 0] when accessed
     */
    public function buildSeriesMatrix(array $seriesRecords): array;

    /**
     * Get the record status for styling purposes
     * 
     * @param int $wins Number of wins
     * @param int $losses Number of losses
     * @return string Status indicator: 'winning', 'losing', or 'tied'
     */
    public function getRecordStatus(int $wins, int $losses): string;

    /**
     * Get background color for a record based on win/loss status
     * 
     * @param int $wins Number of wins
     * @param int $losses Number of losses
     * @return string Hex color code with # prefix
     */
    public function getRecordBackgroundColor(int $wins, int $losses): string;
}
