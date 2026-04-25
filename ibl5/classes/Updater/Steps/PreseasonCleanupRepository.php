<?php

declare(strict_types=1);

namespace Updater\Steps;

use Season\Season;

/**
 * Database queries for the preseason cleanup pipeline step.
 */
final class PreseasonCleanupRepository extends \BaseMysqliRepository
{
    public function hasRegularSeasonSimDates(Season $season): bool
    {
        $startDate = sprintf('%d-%02d-01', $season->beginningYear, Season::IBL_REGULAR_SEASON_STARTING_MONTH);
        $endDate = sprintf('%d-%02d-30', $season->endingYear, Season::IBL_PLAYOFF_MONTH);

        /** @var array{cnt: int}|null $row */
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ibl_sim_dates WHERE end_date BETWEEN ? AND ?",
            "ss",
            $startDate,
            $endDate,
        );

        return $row !== null && $row['cnt'] > 0;
    }

    public function hasPreseasonBoxScores(int $beginningYear): bool
    {
        $boxScoresTable = $this->resolveTable('ibl_box_scores_teams');
        $startDate = sprintf('%d-11-01', $beginningYear);
        $endDate = sprintf('%d-12-31', $beginningYear);

        /** @var array{cnt: int}|null $row */
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$boxScoresTable} WHERE game_date BETWEEN ? AND ?",
            "ss",
            $startDate,
            $endDate,
        );

        return $row !== null && $row['cnt'] > 0;
    }

    public function deletePreseasonJsbData(int $endingYear): void
    {
        $this->transactional(function () use ($endingYear): void {
            $this->execute("DELETE FROM ibl_team_awards WHERE year = ?", "i", $endingYear);
            $this->execute("DELETE FROM ibl_jsb_history WHERE season_year = ?", "i", $endingYear);
            $this->execute("DELETE FROM ibl_jsb_transactions WHERE season_year = ?", "i", $endingYear);
            $this->execute("DELETE FROM ibl_rcb_season_records WHERE season_year = ?", "i", $endingYear);
            $this->execute(
                "DELETE FROM ibl_plr_snapshots WHERE season_year = ? AND snapshot_phase = 'mid-season'",
                "i",
                $endingYear,
            );
        });
    }
}
