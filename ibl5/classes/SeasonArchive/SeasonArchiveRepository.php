<?php

declare(strict_types=1);

namespace SeasonArchive;

use BaseMysqliRepository;
use SeasonArchive\Contracts\SeasonArchiveRepositoryInterface;

/**
 * SeasonArchiveRepository - Data access layer for season archive
 *
 * Retrieves awards, playoff results, team awards, HEAT standings,
 * GM history, and team colors from the database.
 *
 * @phpstan-import-type AwardRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type PlayoffRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type TeamAwardRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type GmHistoryRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type HeatWinLossRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 *
 * @see SeasonArchiveRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class SeasonArchiveRepository extends BaseMysqliRepository implements SeasonArchiveRepositoryInterface
{
    /**
     * @see SeasonArchiveRepositoryInterface::getAllSeasonYears()
     */
    public function getAllSeasonYears(): array
    {
        $rows = $this->fetchAll(
            "SELECT DISTINCT year FROM ibl_awards WHERE year > 1 ORDER BY year ASC"
        );

        $years = [];
        foreach ($rows as $row) {
            /** @var array{year: int} $row */
            $years[] = $row['year'];
        }

        return $years;
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getAwardsByYear()
     */
    public function getAwardsByYear(int $year): array
    {
        /** @var list<AwardRow> */
        return $this->fetchAll(
            "SELECT year, Award, name, table_ID FROM ibl_awards WHERE year = ? ORDER BY Award ASC, table_ID ASC",
            "i",
            $year
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getPlayoffResultsByYear()
     */
    public function getPlayoffResultsByYear(int $year): array
    {
        /** @var list<PlayoffRow> */
        return $this->fetchAll(
            "SELECT year, round, winner, loser, loser_games, id FROM ibl_playoff_results WHERE year = ? AND year > 1 ORDER BY round ASC, id ASC",
            "i",
            $year
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getTeamAwardsByYear()
     */
    public function getTeamAwardsByYear(int $year): array
    {
        $yearPattern = '%' . $year . '%';

        /** @var list<TeamAwardRow> */
        return $this->fetchAll(
            "SELECT year, name, Award, ID FROM ibl_team_awards WHERE year LIKE ?",
            "s",
            $yearPattern
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getAllGmHistory()
     */
    public function getAllGmHistory(): array
    {
        /** @var list<GmHistoryRow> */
        return $this->fetchAll(
            "SELECT year, name, Award, prim FROM ibl_gm_history"
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getHeatWinLossByYear()
     */
    public function getHeatWinLossByYear(int $heatYear): array
    {
        /** @var list<HeatWinLossRow> */
        return $this->fetchAll(
            "SELECT year, currentname, namethatyear, wins, losses, table_ID FROM ibl_heat_win_loss WHERE year = ? ORDER BY wins DESC, losses ASC",
            "i",
            $heatYear
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getTeamColors()
     */
    public function getTeamColors(): array
    {
        $rows = $this->fetchAll(
            "SELECT teamid, team_name, color1, color2 FROM ibl_team_info WHERE teamid != 0"
        );

        $colors = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, color1: string, color2: string} $row */
            $colors[$row['team_name']] = [
                'color1' => $row['color1'],
                'color2' => $row['color2'],
            ];
        }

        return $colors;
    }
}
