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
 * @phpstan-import-type GmAwardWithTeamRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type GmTenureWithTeamRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
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
        /** @var list<TeamAwardRow> */
        return $this->fetchAll(
            "SELECT year, name, Award, ID FROM ibl_team_awards WHERE year = ?",
            "i",
            $year
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getAllGmAwardsWithTeams()
     */
    public function getAllGmAwardsWithTeams(): array
    {
        /** @var list<GmAwardWithTeamRow> */
        return $this->fetchAll(
            "SELECT ga.year, ga.Award, ga.name AS gm_username, ti.team_name, ga.table_ID
            FROM ibl_gm_awards ga
            JOIN ibl_gm_tenures gt ON ga.name = gt.gm_username
                AND ga.year >= gt.start_season_year
                AND (gt.end_season_year IS NULL OR ga.year <= gt.end_season_year)
            JOIN ibl_team_info ti ON gt.franchise_id = ti.teamid
            ORDER BY ga.year ASC"
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getAllGmTenuresWithTeams()
     */
    public function getAllGmTenuresWithTeams(): array
    {
        /** @var list<GmTenureWithTeamRow> */
        return $this->fetchAll(
            "SELECT gt.gm_username, gt.start_season_year, gt.end_season_year, ti.team_name
            FROM ibl_gm_tenures gt
            JOIN ibl_team_info ti ON gt.franchise_id = ti.teamid
            ORDER BY gt.start_season_year ASC"
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
            /** @var array{teamid: int, team_name: string, color1: string, color2: string} $row */
            $colors[$row['team_name']] = [
                'color1' => $row['color1'],
                'color2' => $row['color2'],
                'teamid' => $row['teamid'],
            ];
        }

        return $colors;
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getPlayerIdsByNames()
     */
    public function getPlayerIdsByNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));

        /** @var \mysqli $db */
        $db = $this->db;
        $stmt = $db->prepare("SELECT pid, name FROM ibl_plr WHERE name IN ({$placeholders})");
        if ($stmt === false) {
            return [];
        }

        $stmt->execute($names);
        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();
            return [];
        }

        /** @var array<string, int> $map */
        $map = [];
        while (true) {
            $row = $result->fetch_assoc();
            if (!is_array($row)) {
                break;
            }
            $map[(string) $row['name']] = (int) $row['pid'];
        }
        $stmt->close();

        return $map;
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getTeamConferences()
     */
    public function getTeamConferences(): array
    {
        $rows = $this->fetchAll(
            "SELECT team_name, conference FROM ibl_standings WHERE conference != ''"
        );

        $map = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, conference: string} $row */
            $map[$row['team_name']] = $row['conference'];
        }

        return $map;
    }
}
