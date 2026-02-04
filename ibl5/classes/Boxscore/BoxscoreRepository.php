<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreRepositoryInterface;

/**
 * BoxscoreRepository - Data access layer for boxscore management
 *
 * Handles deletion of boxscore records for different season phases.
 * Operates on both ibl_box_scores (player stats) and ibl_box_scores_teams tables.
 *
 * @see BoxscoreRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class BoxscoreRepository extends \BaseMysqliRepository implements BoxscoreRepositoryInterface
{
    /**
     * Constructor
     *
     * @param object $db Active mysqli connection
     */
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see BoxscoreRepositoryInterface::deletePreseasonBoxScores()
     */
    public function deletePreseasonBoxScores(): bool
    {
        $preseasonYear = \Season::IBL_PRESEASON_YEAR;
        $startDate = "{$preseasonYear}-11-01";
        $endDate = "{$preseasonYear}-11-30";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteHeatBoxScores()
     */
    public function deleteHeatBoxScores(int $seasonStartingYear): bool
    {
        $heatMonth = str_pad((string) \Season::IBL_HEAT_MONTH, 2, '0', STR_PAD_LEFT);
        $startDate = "{$seasonStartingYear}-{$heatMonth}-01";
        $endDate = "{$seasonStartingYear}-{$heatMonth}-31";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteRegularSeasonAndPlayoffsBoxScores()
     */
    public function deleteRegularSeasonAndPlayoffsBoxScores(int $seasonStartingYear): bool
    {
        $seasonEndingYear = $seasonStartingYear + 1;
        $regularSeasonMonth = str_pad((string) \Season::IBL_REGULAR_SEASON_STARTING_MONTH, 2, '0', STR_PAD_LEFT);
        $playoffMonth = str_pad((string) \Season::IBL_PLAYOFF_MONTH, 2, '0', STR_PAD_LEFT);

        $startDate = "{$seasonStartingYear}-{$regularSeasonMonth}-01";
        $endDate = "{$seasonEndingYear}-{$playoffMonth}-30";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * Delete boxscores for both players and teams within a date range
     *
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate End date in Y-m-d format
     * @return true Always returns true since execute() returns int on success
     */
    private function deleteBoxScoresForDateRange(string $startDate, string $endDate): true
    {
        // Delete player boxscores
        $this->execute(
            "DELETE FROM ibl_box_scores WHERE Date BETWEEN ? AND ?",
            "ss",
            $startDate,
            $endDate
        );

        // Delete team boxscores
        $this->execute(
            "DELETE FROM ibl_box_scores_teams WHERE Date BETWEEN ? AND ?",
            "ss",
            $startDate,
            $endDate
        );

        return true;
    }
}
