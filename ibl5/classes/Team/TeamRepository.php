<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamRepositoryInterface;

/**
 * @phpstan-import-type TeamInfoRow from \Services\CommonMysqliRepository
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type PowerRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type BannerRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type GMTenureRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type GMAwardRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type TeamAwardRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type WinLossRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type HEATWinLossRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type PlayoffResultRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type HistRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type FranchiseSeasonRow from Contracts\TeamRepositoryInterface
 *
 * @see TeamRepositoryInterface
 */
class TeamRepository extends \BaseMysqliRepository implements TeamRepositoryInterface
{
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see TeamRepositoryInterface::getTeam()
     * @return TeamInfoRow|null
     */
    public function getTeam(int $teamID): ?array
    {
        /** @var TeamInfoRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_team_info WHERE teamid = ?",
            "i",
            $teamID
        );
    }

    /**
     * @see TeamRepositoryInterface::getTeamPowerData()
     * @return PowerRow|null
     */
    public function getTeamPowerData(string $teamName): ?array
    {
        /** @var PowerRow|null */
        return $this->fetchOne(
            "SELECT s.tid, s.team_name, s.leagueRecord, s.wins, s.losses, s.pct,
                s.conference, s.division, s.confRecord, s.divRecord, s.divGB,
                s.homeRecord, s.awayRecord, s.gamesUnplayed,
                p.ranking, p.last_win, p.last_loss, p.streak_type, p.streak,
                p.sos, p.remaining_sos
            FROM ibl_standings s
            JOIN ibl_power p ON s.tid = p.TeamID
            JOIN ibl_team_info t ON s.tid = t.teamid
            WHERE t.team_name = ?",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getDivisionStandings()
     * @return list<PowerRow>
     */
    public function getDivisionStandings(string $division): array
    {
        /** @var list<PowerRow> */
        return $this->fetchAll(
            "SELECT s.tid, s.team_name, s.leagueRecord, s.wins, s.losses, s.pct,
                s.conference, s.division, s.confRecord, s.divRecord, s.divGB,
                s.homeRecord, s.awayRecord, s.gamesUnplayed,
                p.ranking, p.last_win, p.last_loss, p.streak_type, p.streak,
                p.sos, p.remaining_sos
            FROM ibl_standings s
            JOIN ibl_power p ON s.tid = p.TeamID
            WHERE s.division = ?
            ORDER BY s.divGB ASC",
            "s",
            $division
        );
    }

    /**
     * @see TeamRepositoryInterface::getConferenceStandings()
     * @return list<PowerRow>
     */
    public function getConferenceStandings(string $conference): array
    {
        /** @var list<PowerRow> */
        return $this->fetchAll(
            "SELECT s.tid, s.team_name, s.leagueRecord, s.wins, s.losses, s.pct,
                s.conference, s.division, s.confRecord, s.divRecord, s.divGB,
                s.homeRecord, s.awayRecord, s.gamesUnplayed,
                p.ranking, p.last_win, p.last_loss, p.streak_type, p.streak,
                p.sos, p.remaining_sos
            FROM ibl_standings s
            JOIN ibl_power p ON s.tid = p.TeamID
            WHERE s.conference = ?
            ORDER BY s.confGB ASC",
            "s",
            $conference
        );
    }

    /**
     * @see TeamRepositoryInterface::getChampionshipBanners()
     * @return list<BannerRow>
     */
    public function getChampionshipBanners(string $teamName): array
    {
        /** @var list<BannerRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_banners WHERE currentname = ? ORDER BY year ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getGMTenures()
     * @return list<GMTenureRow>
     */
    public function getGMTenures(int $franchiseId): array
    {
        /** @var list<GMTenureRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_gm_tenures WHERE franchise_id = ? ORDER BY start_season_year ASC",
            "i",
            $franchiseId
        );
    }

    /**
     * @see TeamRepositoryInterface::getGMAwards()
     * @return list<GMAwardRow>
     */
    public function getGMAwards(string $gmUsername): array
    {
        /** @var list<GMAwardRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_gm_awards WHERE name = ? ORDER BY year ASC",
            "s",
            $gmUsername
        );
    }

    /**
     * @see TeamRepositoryInterface::getTeamAccomplishments()
     * @return list<TeamAwardRow>
     */
    public function getTeamAccomplishments(string $teamName): array
    {
        /** @var list<TeamAwardRow> */
        return $this->fetchAll(
            "SELECT * FROM vw_team_awards WHERE name LIKE ? ORDER BY year DESC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getRegularSeasonHistory()
     * @return list<WinLossRow>
     */
    public function getRegularSeasonHistory(string $teamName): array
    {
        /** @var list<WinLossRow> */
        return $this->fetchAll(
            "SELECT year, currentname, namethatyear, wins, losses FROM ibl_team_win_loss WHERE currentname = ? ORDER BY year DESC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getHEATHistory()
     * @return list<HEATWinLossRow>
     */
    public function getHEATHistory(string $teamName): array
    {
        /** @var list<HEATWinLossRow> */
        return $this->fetchAll(
            "SELECT year, currentname, namethatyear, wins, losses FROM ibl_heat_win_loss WHERE currentname = ? ORDER BY year DESC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getPlayoffResults()
     * @return list<PlayoffResultRow>
     */
    public function getPlayoffResults(): array
    {
        /** @var list<PlayoffResultRow> */
        return $this->fetchAll(
            "SELECT pr.year, pr.round, pr.winner, pr.loser, pr.winner_games, pr.loser_games,
                    COALESCE(wfs.team_name, pr.winner) AS winner_name_that_year,
                    COALESCE(lfs.team_name, pr.loser) AS loser_name_that_year
             FROM vw_playoff_series_results pr
             LEFT JOIN ibl_franchise_seasons wfs ON wfs.franchise_id = pr.winner_tid AND wfs.season_ending_year = pr.year
             LEFT JOIN ibl_franchise_seasons lfs ON lfs.franchise_id = pr.loser_tid AND lfs.season_ending_year = pr.year
             ORDER BY pr.year DESC"
        );
    }

    /**
     * @see TeamRepositoryInterface::getFreeAgencyRoster()
     * @return list<PlayerRow>
     */
    public function getFreeAgencyRoster(int $teamID): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND cyt != cy
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC",
            "i",
            $teamID
        );
    }

    /**
     * @see TeamRepositoryInterface::getRosterUnderContract()
     * @return list<PlayerRow>
     */
    public function getRosterUnderContract(int $teamID): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC",
            "i",
            $teamID
        );
    }

    /**
     * @see TeamRepositoryInterface::getFreeAgents()
     * @return list<PlayerRow>
     */
    public function getFreeAgents(bool $includeFreeAgencyActive = false): array
    {
        if ($includeFreeAgencyActive) {
            /** @var list<PlayerRow> */
            return $this->fetchAll(
                "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC"
            );
        }
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 ORDER BY ordinal ASC"
        );
    }

    /**
     * @see TeamRepositoryInterface::getEntireLeagueRoster()
     * @return list<PlayerRow>
     */
    public function getEntireLeagueRoster(): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE retired = 0 AND name NOT LIKE '%Buyouts' ORDER BY ordinal ASC"
        );
    }

    /**
     * @see TeamRepositoryInterface::getHistoricalRoster()
     * @return list<HistRow>
     */
    public function getHistoricalRoster(int $teamID, string $year): array
    {
        /** @var list<HistRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_hist WHERE teamid = ? AND year = ? ORDER BY name ASC",
            "is",
            $teamID,
            $year
        );
    }

    /**
     * @see TeamRepositoryInterface::getFranchiseSeasons()
     * @return list<FranchiseSeasonRow>
     */
    public function getFranchiseSeasons(int $franchiseId): array
    {
        /** @var list<FranchiseSeasonRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_franchise_seasons WHERE franchise_id = ? ORDER BY season_year ASC",
            "i",
            $franchiseId
        );
    }
}
