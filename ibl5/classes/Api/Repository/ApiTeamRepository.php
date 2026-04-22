<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;
use League\League;
use League\LeagueContext;

/**
 * @phpstan-type TeamListRow array{teamid: int, uuid: string, team_city: string, team_name: string, owner_name: string, arena: string, conference: string|null, division: string|null, discordID: int|null}
 * @phpstan-type TeamDetailRow array{teamid: int, uuid: string, team_city: string, team_name: string, owner_name: string, arena: string, conference: string|null, division: string|null, discordID: int|null, league_record: string|null, conference_record: string|null, division_record: string|null, home_wins: int|null, home_losses: int|null, away_wins: int|null, away_losses: int|null, win_percentage: float|null, conference_games_back: string|null, division_games_back: string|null, games_remaining: int|null}
 */
class ApiTeamRepository extends \BaseMysqliRepository
{
    private string $teamInfoTable;
    private string $standingsTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
        $this->standingsTable = $this->resolveTable('ibl_standings');
    }

    /**
     * Get paginated list of teams.
     *
     * @return list<TeamListRow>
     */
    public function getTeams(Paginator $paginator): array
    {
        $orderBy = $paginator->getOrderByClause();

        /** @var list<TeamListRow> */
        return $this->fetchAll(
            "SELECT t.teamid, t.uuid, t.team_city, t.team_name, t.owner_name, t.arena,
                    s.conference, s.division,
                    t.discordID
             FROM {$this->teamInfoTable} t
             LEFT JOIN {$this->standingsTable} s ON t.teamid = s.teamid
             WHERE t.teamid BETWEEN 1 AND ?
             ORDER BY {$orderBy}
             LIMIT ? OFFSET ?",
            'iii',
            League::MAX_REAL_TEAMID,
            $paginator->getLimit(),
            $paginator->getOffset()
        );
    }

    /**
     * Count total teams.
     */
    public function countTeams(): int
    {
        /** @var array{total: int}|null $row */
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->teamInfoTable} WHERE teamid BETWEEN 1 AND ?",
            'i',
            League::MAX_REAL_TEAMID
        );

        return $row !== null ? $row['total'] : 0;
    }

    /**
     * Get a single team by UUID with standings data.
     *
     * @return TeamDetailRow|null
     */
    public function getTeamByUuid(string $uuid): ?array
    {
        /** @var TeamDetailRow|null */
        return $this->fetchOne(
            "SELECT t.teamid, t.uuid, t.team_city, t.team_name, t.owner_name, t.arena,
                    s.conference, s.division,
                    t.discordID,
                    s.leagueRecord AS league_record,
                    s.pct AS win_percentage,
                    s.confRecord AS conference_record,
                    s.confGB AS conference_games_back,
                    s.divRecord AS division_record,
                    s.divGB AS division_games_back,
                    s.homeWins AS home_wins,
                    s.homeLosses AS home_losses,
                    s.awayWins AS away_wins,
                    s.awayLosses AS away_losses,
                    s.gamesUnplayed AS games_remaining
             FROM {$this->teamInfoTable} t
             LEFT JOIN {$this->standingsTable} s ON t.teamid = s.teamid
             WHERE t.uuid = ?",
            's',
            $uuid
        );
    }
}
