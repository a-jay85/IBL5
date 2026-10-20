<?php

declare(strict_types=1);

namespace Api\Repository;

use Api\Pagination\Paginator;
use League\LeagueContext;

/**
 * @phpstan-type GameViewRow array{game_uuid: string, season_year: int, game_date: string, game_status: string, box_score_id: int, game_of_that_day: int, visitor_uuid: string, visitor_city: string, visitor_name: string, visitor_full_name: string, visitor_score: int, visitor_team_id: int, home_uuid: string, home_city: string, home_name: string, home_full_name: string, home_score: int, home_team_id: int, ...<string, mixed>}
 * @phpstan-type BoxscoreTeamRow array{name: string, visitor_q1_points: int, visitor_q2_points: int, visitor_q3_points: int, visitor_q4_points: int, visitor_ot_points: int, home_q1_points: int, home_q2_points: int, home_q3_points: int, home_q4_points: int, home_ot_points: int, game_min: int|null, game_2gm: int, game_2ga: int, game_ftm: int, game_fta: int, game_3gm: int, game_3ga: int, game_orb: int, game_drb: int, game_ast: int, game_stl: int, game_tov: int, game_blk: int, game_pf: int, attendance: int, capacity: int, visitor_wins: int, visitor_losses: int, home_wins: int, home_losses: int, calc_points: int, calc_rebounds: int, calc_fg_made: int, ...<string, mixed>}
 * @phpstan-type BoxscorePlayerRow array{player_uuid: string|null, name: string, pos: string, game_min: int, game_2gm: int, game_2ga: int, game_ftm: int, game_fta: int, game_3gm: int, game_3ga: int, game_orb: int, game_drb: int, game_ast: int, game_stl: int, game_tov: int, game_blk: int, game_pf: int, calc_points: int, calc_rebounds: int, calc_fg_made: int, player_tid: int|null, ...<string, mixed>}
 */
class ApiGameRepository extends \Database\BaseMysqliRepository
{
    /** Public API sort key => SQL column. The controller's allowlist is array_keys() of this map. */
    public const SORT_COLUMNS = [
        'game_date' => 'game_date',
        'visitor_score' => 'visitor_score',
        'home_score' => 'home_score',
    ];

    /** Flag-guarded filter WHERE: each group is TRUE when its leading flag is 0. */
    private const GAME_FILTER_WHERE = ' WHERE (? = 0 OR season_year = ?) AND (? = 0 OR game_status = ?)'
        . ' AND (? = 0 OR (visitor_uuid = ? OR home_uuid = ?)) AND (? = 0 OR game_date = ?)'
        . ' AND (? = 0 OR game_date >= ?) AND (? = 0 OR game_date <= ?)';

    /** Bind types matching GAME_FILTER_WHERE placeholder order (13 slots). */
    private const GAME_FILTER_TYPES = 'iiisissisisis';

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * Get paginated list of games from the schedule view.
     *
     * @param array<string, string> $filters Optional filters (season, status, team, date, date_start, date_end)
     * @return list<GameViewRow>
     */
    public function getGames(Paginator $paginator, array $filters = []): array
    {
        $applied = $this->applyFilters($filters);

        $sortColumn = self::SORT_COLUMNS[$paginator->getSort()]
            ?? throw new \InvalidArgumentException('Invalid sort column: ' . $paginator->getSort());
        $direction = $paginator->getOrder() === 'desc' ? 'DESC' : 'ASC';

        // IDENTIFIER: $sortColumn = SORT_COLUMNS map value; $direction = literal ternary
        $query = 'SELECT * FROM vw_schedule_upcoming' . self::GAME_FILTER_WHERE . ' ORDER BY ' . $sortColumn . ' ' . $direction . ' LIMIT ? OFFSET ?';

        /** @var list<GameViewRow> */
        return $this->fetchAll(
            $query,
            self::GAME_FILTER_TYPES . 'ii',
            ...[...$applied['params'], $paginator->getLimit(), $paginator->getOffset()]
        );
    }

    /**
     * Count total games matching filters.
     *
     * @param array<string, string> $filters
     */
    public function countGames(array $filters = []): int
    {
        $applied = $this->applyFilters($filters);

        /** @var array{total: int}|null $row */
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM vw_schedule_upcoming' . self::GAME_FILTER_WHERE,
            self::GAME_FILTER_TYPES,
            ...$applied['params']
        );

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
            "SELECT * FROM `ibl_box_scores_teams` WHERE visitor_teamid = ? AND home_teamid = ? AND game_date = ? ORDER BY id ASC",
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
             FROM `ibl_box_scores` b
             LEFT JOIN `ibl_plr` p ON b.pid = p.pid
             WHERE b.game_date = ? AND b.visitor_teamid = ? AND b.home_teamid = ?
             ORDER BY b.id ASC",
            'sii',
            $date,
            $visitorTid,
            $homeTid
        );
    }

    /**
     * Build the bind list for GAME_FILTER_WHERE. Every predicate always binds a
     * flag (1 when the filter is present, 0 otherwise) followed by its value(s).
     *
     * @param array<string, string> $filters
     * @return array{params: list<int|string>}
     */
    private function applyFilters(array $filters): array
    {
        $season = $filters['season'] ?? '';
        $status = $filters['status'] ?? '';
        $team = $filters['team'] ?? '';
        $date = $filters['date'] ?? '';
        $from = $filters['date_start'] ?? '';
        $to = $filters['date_end'] ?? '';

        return [
            'params' => [
                $season !== '' ? 1 : 0, (int) $season,
                $status !== '' ? 1 : 0, $status,
                $team !== '' ? 1 : 0, $team, $team,
                $date !== '' ? 1 : 0, $date,
                $from !== '' ? 1 : 0, $from,
                $to !== '' ? 1 : 0, $to,
            ],
        ];
    }
}
