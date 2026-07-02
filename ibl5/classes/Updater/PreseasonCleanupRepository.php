<?php

declare(strict_types=1);

namespace Updater;

use Season\Season;

/**
 * Database queries for the preseason cleanup pipeline step.
 */
final class PreseasonCleanupRepository extends \BaseMysqliRepository
{
    public function hasPreseasonBoxScores(int $beginningYear): bool
    {
        $startDate = sprintf('%d-09-01', $beginningYear);
        $endDate = sprintf('%d-09-30', $beginningYear);

        /** @var array{cnt: int}|null $row */
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `ibl_box_scores_teams` WHERE game_date BETWEEN ? AND ?",
            "ss",
            $startDate,
            $endDate,
        );

        return $row !== null && $row['cnt'] > 0;
    }

    public function deletePreseasonSimDates(int $beginningYear): void
    {
        $startDate = sprintf('%d-09-01', $beginningYear);
        $endDate = sprintf('%d-09-30', $beginningYear);

        $this->execute(
            "DELETE FROM `ibl_sim_dates` WHERE start_date BETWEEN ? AND ? AND end_date BETWEEN ? AND ?",
            "ssss",
            $startDate,
            $endDate,
            $startDate,
            $endDate,
        );
    }

    public function deletePreseasonJsbData(int $endingYear): void
    {
        $this->transactional(function () use ($endingYear): void {
            $this->execute("DELETE FROM `ibl_team_awards` WHERE year = ?", "i", $endingYear);
            $this->execute("DELETE FROM `ibl_jsb_history` WHERE season_year = ?", "i", $endingYear);
            $this->execute("DELETE FROM `ibl_jsb_transactions` WHERE season_year = ?", "i", $endingYear);
            $this->execute("DELETE FROM `ibl_rcb_season_records` WHERE season_year = ?", "i", $endingYear);
            $this->execute(
                "DELETE FROM `ibl_plr_snapshots` WHERE season_year = ? AND snapshot_phase = 'mid-season'",
                "i",
                $endingYear,
            );
        });
    }
}
