<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;
use League\LeagueContext;

/**
 * @phpstan-type GameViewRow array{game_uuid: string, season_year: int, game_date: string, game_status: string, box_score_id: int, game_of_that_day: int, visitor_uuid: string, visitor_city: string, visitor_name: string, visitor_full_name: string, visitor_score: int, visitor_team_id: int, home_uuid: string, home_city: string, home_name: string, home_full_name: string, home_score: int, home_team_id: int, ...}
 * @phpstan-type BoxscoreTeamRow array{name: string, visitorQ1points: int, visitorQ2points: int, visitorQ3points: int, visitorQ4points: int, visitorOTpoints: int, homeQ1points: int, homeQ2points: int, homeQ3points: int, homeQ4points: int, homeOTpoints: int, gameMIN: int|null, game2GM: int, game2GA: int, gameFTM: int, gameFTA: int, game3GM: int, game3GA: int, gameORB: int, gameDRB: int, gameAST: int, gameSTL: int, gameTOV: int, gameBLK: int, gamePF: int, attendance: int, capacity: int, visitorWins: int, visitorLosses: int, homeWins: int, homeLosses: int, calc_points: int, calc_rebounds: int, calc_fg_made: int, ...}
 * @phpstan-type BoxscorePlayerRow array{player_uuid: string|null, name: string, pos: string, gameMIN: int, game2GM: int, game2GA: int, gameFTM: int, gameFTA: int, game3GM: int, game3GA: int, gameORB: int, gameDRB: int, gameAST: int, gameSTL: int, gameTOV: int, gameBLK: int, gamePF: int, calc_points: int, calc_rebounds: int, calc_fg_made: int, player_tid: int|null, ...}
 */
class ApiGameRepository extends \BaseMysqliRepository
{
    private string $boxScoresTable;
    private string $boxScoresTeamsTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->boxScoresTable = $this->resolveTable('ibl_box_scores');
        $this->boxScoresTeamsTable = $this->resolveTable('ibl_box_scores_teams');
    }

    /**
     * Get paginated list of games from the schedule view.
     *
     * @param array<string, string> $filters Optional filters (season, status, team, date, date_start, date_end)
     * @return list<GameViewRow>
     */
    public function getGames(Paginator $paginator, array $filters = []): array
    {
        $where = [];
        $types = '';
        $params = [];

        $this->applyFilters($filters, $where, $types, $params);

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderBy = $paginator->getOrderByClause();

        $query = "SELECT * FROM vw_schedule_upcoming {$whereClause} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $paginator->getLimit();
        $params[] = $paginator->getOffset();

        /** @var list<GameViewRow> */
        return $this->fetchAll($query, $types, ...$params);
    }

    /**
     * Count total games matching filters.
     *
     * @param array<string, string> $filters
     */
    public function countGames(array $filters = []): int
    {
        $where = [];
        $types = '';
        $params = [];

        $this->applyFilters($filters, $where, $types, $params);

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        /** @var array{total: int}|null $row */
        $row = $this->fetchOne("SELECT COUNT(*) AS total FROM vw_schedule_upcoming {$whereClause}", $types, ...$params);

        return $row !== null ? $row['total'] : 0;
    }

    /**
     * Get a single game by UUID.
     *
     * @return GameViewRow|null
     */
    public function getGameByUuid(string $uuid): ?array
    {
        /** @var GameViewRow|null */
        return $this->fetchOne(
            'SELECT * FROM vw_schedule_upcoming WHERE game_uuid = ?',
            's',
            $uuid
        );
    }

    /**
     * Get team box score stats for a game identified by date and team IDs.
     *
     * @return list<BoxscoreTeamRow>
     */
    public function getBoxscoreTeams(int $visitorTeamId, int $homeTeamId, string $date): array
    {
        /** @var list<BoxscoreTeamRow> */
        return $this->fetchAll(
            "SELECT * FROM {$this->boxScoresTeamsTable} WHERE visitor_teamid = ? AND home_teamid = ? AND Date = ? ORDER BY id ASC",
            'iis',
            $visitorTeamId,
            $homeTeamId,
            $date
        );
    }

    /**
     * Get player box score lines for a game identified by date and team IDs.
     *
     * @return list<BoxscorePlayerRow>
     */
    public function getBoxscorePlayers(int $visitorTid, int $homeTid, string $date): array
    {
        /** @var list<BoxscorePlayerRow> */
        return $this->fetchAll(
            "SELECT b.*, COALESCE(p.name, b.name) AS name, p.uuid AS player_uuid, p.teamid AS player_tid
             FROM {$this->boxScoresTable} b
             LEFT JOIN ibl_plr p ON b.pid = p.pid
             WHERE b.Date = ? AND b.visitor_teamid = ? AND b.home_teamid = ?
             ORDER BY b.id ASC",
            'sii',
            $date,
            $visitorTid,
            $homeTid
        );
    }

    /**
     * Apply common filters to query building arrays.
     *
     * @param array<string, string> $filters
     * @param list<string> $where
     * @param list<mixed> $params
     */
    private function applyFilters(array $filters, array &$where, string &$types, array &$params): void
    {
        if (isset($filters['season']) && $filters['season'] !== '') {
            $where[] = 'season_year = ?';
            $types .= 'i';
            $params[] = (int) $filters['season'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'game_status = ?';
            $types .= 's';
            $params[] = $filters['status'];
        }

        if (isset($filters['team']) && $filters['team'] !== '') {
            $where[] = '(visitor_uuid = ? OR home_uuid = ?)';
            $types .= 'ss';
            $params[] = $filters['team'];
            $params[] = $filters['team'];
        }

        if (isset($filters['date']) && $filters['date'] !== '') {
            $where[] = 'game_date = ?';
            $types .= 's';
            $params[] = $filters['date'];
        }

        if (isset($filters['date_start']) && $filters['date_start'] !== '') {
            $where[] = 'game_date >= ?';
            $types .= 's';
            $params[] = $filters['date_start'];
        }

        if (isset($filters['date_end']) && $filters['date_end'] !== '') {
            $where[] = 'game_date <= ?';
            $types .= 's';
            $params[] = $filters['date_end'];
        }
    }
}
