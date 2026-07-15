<?php

declare(strict_types=1);

namespace GameBoxscore;

use GameBoxscore\Contracts\GameBoxscoreRepositoryInterface;
use League\LeagueContext;

/**
 * GameBoxscoreRepository - Data access layer for a single game's box score
 *
 * Retrieves the game header row and per-player stat rows from the box score
 * tables. This repository is a pure data-access seam: it returns raw
 * associative rows from `fetchOne`/`fetchAll` with no normalization,
 * type-coercion, or display defaults — those live in the Service layer.
 *
 * @see GameBoxscoreRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class GameBoxscoreRepository extends \BaseMysqliRepository implements GameBoxscoreRepositoryInterface
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see GameBoxscoreRepositoryInterface::getGameInfo()
     *
     * @return array<string, int|float|string|null>|null
     */
    public function getGameInfo(string $date, int $gameOfThatDay): ?array
    {
        $sql = "SELECT DISTINCT
                game.game_date,
                game.visitor_teamid AS awayTeamId,
                game.home_teamid    AS homeTeamId,
                game.game_of_that_day,
                (game.visitor_q1_points + game.visitor_q2_points + game.visitor_q3_points
                    + game.visitor_q4_points + COALESCE(game.visitor_ot_points, 0)) AS awayScore,
                (game.home_q1_points + game.home_q2_points + game.home_q3_points
                    + game.home_q4_points + COALESCE(game.home_ot_points, 0))       AS homeScore,
                away.team_name AS awayTeamName, away.team_city AS awayTeamCity,
                away.color1    AS awayColor1,   away.color2    AS awayColor2,
                home.team_name AS homeTeamName, home.team_city AS homeTeamCity,
                home.color1    AS homeColor1,   home.color2    AS homeColor2
            FROM `ibl_box_scores_teams` game
            LEFT JOIN `ibl_team_info` home ON game.home_teamid    = home.teamid
            LEFT JOIN `ibl_team_info` away ON game.visitor_teamid = away.teamid
            WHERE DATE(game.game_date) = ? AND game.game_of_that_day = ?
            LIMIT 1";

        /** @var array<string, int|float|string|null>|null */
        return $this->fetchOne($sql, 'si', $date, $gameOfThatDay);
    }

    /**
     * @see GameBoxscoreRepositoryInterface::getPlayerRows()
     *
     * @return list<array<string, int|float|string|null>>
     */
    public function getPlayerRows(string $date, int $gameOfThatDay, int $awayTeamId, int $homeTeamId): array
    {
        $sql = "SELECT
                bp.game_date,
                COALESCE(plr.name, bp.name) AS name,
                bp.pos, bp.pid, bp.teamid,
                CASE WHEN bp.teamid = ? THEN 1 ELSE 0 END AS isAwayPlayer,
                bp.game_min       AS min,
                bp.calc_fg_made   AS fgm,
                (bp.game_2ga + bp.game_3ga) AS fga,
                bp.game_ftm       AS ftm,
                bp.game_fta       AS fta,
                bp.game_3gm       AS tpm,
                bp.game_3ga       AS tpa,
                bp.game_orb       AS orb,
                bp.game_drb       AS drb,
                bp.game_ast       AS ast,
                bp.game_stl       AS stl,
                bp.game_tov       AS tov,
                bp.game_blk       AS blk,
                bp.game_pf        AS pf,
                bp.calc_rebounds  AS reb,
                bp.calc_points    AS pts
            FROM `ibl_box_scores` bp
            LEFT JOIN `ibl_plr` plr ON bp.pid = plr.pid
            WHERE DATE(bp.game_date) = ? AND bp.game_of_that_day = ? AND bp.teamid IN (?, ?)
            ORDER BY bp.teamid = ? DESC, bp.game_min DESC, bp.pid ASC";

        /** @var list<array<string, int|float|string|null>> */
        return $this->fetchAll($sql, 'isiiii', $awayTeamId, $date, $gameOfThatDay, $awayTeamId, $homeTeamId, $awayTeamId);
    }
}
