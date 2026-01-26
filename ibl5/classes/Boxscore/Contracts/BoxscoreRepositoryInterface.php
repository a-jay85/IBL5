<?php

declare(strict_types=1);

namespace Boxscore\Contracts;

/**
 * BoxscoreRepositoryInterface - Contract for boxscore data access
 *
 * Defines methods for managing boxscore data in the database.
 *
 * @see \Boxscore\BoxscoreRepository For the concrete implementation
 */
interface BoxscoreRepositoryInterface
{
    /**
     * Delete preseason boxscores for both players and teams
     *
     * Removes all boxscore records from November (preseason month)
     * of the preseason year (9998).
     *
     * @return bool True if both deletions succeeded, false otherwise
     */
    public function deletePreseasonBoxScores(): bool;

    /**
     * Delete H.E.A.T. tournament boxscores for both players and teams
     *
     * Removes all boxscore records from October (HEAT month)
     * of the specified season starting year.
     *
     * @param int $seasonStartingYear The year the season starts (e.g., 2024 for 2024-25 season)
     * @return bool True if both deletions succeeded, false otherwise
     */
    public function deleteHeatBoxScores(int $seasonStartingYear): bool;

    /**
     * Delete regular season and playoff boxscores for both players and teams
     *
     * Removes all boxscore records from November of the starting year
     * through June of the following year.
     *
     * @param int $seasonStartingYear The year the season starts (e.g., 2024 for 2024-25 season)
     * @return bool True if both deletions succeeded, false otherwise
     */
    public function deleteRegularSeasonAndPlayoffsBoxScores(int $seasonStartingYear): bool;
}
