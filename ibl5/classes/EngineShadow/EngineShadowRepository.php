<?php

declare(strict_types=1);

namespace EngineShadow;

use League\LeagueContext;

/**
 * Data-access layer for the native-engine SHADOW box-score tables
 * (ibl_box_scores_engine_shadow / _teams).
 *
 * Writes go exclusively through BaseMysqliRepository prepared statements with
 * native-int binding — no raw SQL, no string interpolation of values. The loader
 * wraps the per-game write set in transactional() so a mid-game failure rolls the
 * shadow writes back without ever touching the canonical tables.
 */
class EngineShadowRepository extends \BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * Public entry point to the base transactional() helper, so the loader can
     * make each game's shadow writes atomic (a mid-game failure rolls back that
     * game without touching canonical). Uses a SAVEPOINT when already inside a
     * transaction (e.g. DatabaseTestCase).
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        return $this->transactional($fn);
    }

    /**
     * Resolve teamid for each pid from ibl_plr (mirrors how the canonical .sco
     * import stamps a player's team). Returns pid => teamid; pids absent from
     * ibl_plr are omitted (the loader stamps NULL for those).
     *
     * @param list<int> $pids
     *
     * @return array<int, int>
     */
    public function getTeamIdsForPids(array $pids): array
    {
        $unique = array_values(array_unique($pids));
        if ($unique === []) {
            return [];
        }

        /** @var list<array{pid: int, teamid: int|null}> $rows */
        $rows = $this->fetchAllInList(
            'SELECT pid, teamid FROM `ibl_plr` WHERE pid IN ({IN})',
            'i',
            $unique,
        );

        $map = [];
        foreach ($rows as $row) {
            if ($row['teamid'] !== null) {
                $map[$row['pid']] = $row['teamid'];
            }
        }

        return $map;
    }

    /**
     * Delete any existing shadow rows for one game (from BOTH shadow tables) so a
     * re-run replaces rather than appends. Called by the loader at the top of each
     * per-game transaction, so the delete+insert for a game is atomic. Identity
     * keys are bound (`siii`) — never interpolated. Returns total rows removed.
     */
    public function deleteShadowGame(
        string $gameDate,
        int $visitorTeamId,
        int $homeTeamId,
        int $gameOfThatDay,
    ): int {
        $deleted = $this->execute(
            "DELETE FROM `ibl_box_scores_engine_shadow`
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_of_that_day = ?",
            'siii',
            $gameDate,
            $visitorTeamId,
            $homeTeamId,
            $gameOfThatDay,
        );
        $deleted += $this->execute(
            "DELETE FROM `ibl_box_scores_engine_shadow_teams`
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_of_that_day = ?",
            'siii',
            $gameDate,
            $visitorTeamId,
            $homeTeamId,
            $gameOfThatDay,
        );

        return $deleted;
    }

    /**
     * Insert one engine player box row. teamid/pos are nullable (a pid missing
     * from ibl_plr yields a NULL teamid).
     */
    public function insertShadowPlayerBox(
        string $gameDate,
        int $visitorTeamId,
        int $homeTeamId,
        int $gameOfThatDay,
        int $pid,
        ?int $teamId,
        ?string $pos,
        int $gameMin,
        int $game2gm,
        int $game2ga,
        int $gameFtm,
        int $gameFta,
        int $game3gm,
        int $game3ga,
        int $gameOrb,
        int $gameDrb,
        int $gameAst,
        int $gameStl,
        int $gameTov,
        int $gameBlk,
        int $gamePf,
        int $simSeed,
        int $simGameType,
    ): int {
        return $this->execute(
            "INSERT INTO `ibl_box_scores_engine_shadow`
                (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`,
                 `pid`, `teamid`, `pos`, `game_min`,
                 `game_2gm`, `game_2ga`, `game_ftm`, `game_fta`, `game_3gm`, `game_3ga`,
                 `game_orb`, `game_drb`, `game_ast`, `game_stl`, `game_tov`, `game_blk`, `game_pf`,
                 `sim_seed`, `sim_game_type`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "siiiiisiiiiiiiiiiiiiiii",
            $gameDate,
            $visitorTeamId,
            $homeTeamId,
            $gameOfThatDay,
            $pid,
            $teamId,
            $pos,
            $gameMin,
            $game2gm,
            $game2ga,
            $gameFtm,
            $gameFta,
            $game3gm,
            $game3ga,
            $gameOrb,
            $gameDrb,
            $gameAst,
            $gameStl,
            $gameTov,
            $gameBlk,
            $gamePf,
            $simSeed,
            $simGameType,
        );
    }

    /**
     * Insert one engine team box row. Two rows per game (visitor inserted first,
     * then home); each carries its own shooting stats plus both teams' quarter
     * points. `teamid` identifies which team this row's shooting stats belong to.
     */
    public function insertShadowTeamBox(
        string $gameDate,
        int $visitorTeamId,
        int $homeTeamId,
        int $gameOfThatDay,
        int $teamId,
        int $game2gm,
        int $game2ga,
        int $gameFtm,
        int $gameFta,
        int $game3gm,
        int $game3ga,
        int $gameOrb,
        int $gameDrb,
        int $gameAst,
        int $gameStl,
        int $gameTov,
        int $gameBlk,
        int $gamePf,
        int $visitorQ1,
        int $visitorQ2,
        int $visitorQ3,
        int $visitorQ4,
        int $visitorOt,
        int $homeQ1,
        int $homeQ2,
        int $homeQ3,
        int $homeQ4,
        int $homeOt,
        int $simSeed,
        int $simGameType,
    ): int {
        return $this->execute(
            "INSERT INTO `ibl_box_scores_engine_shadow_teams`
                (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `teamid`,
                 `game_2gm`, `game_2ga`, `game_ftm`, `game_fta`, `game_3gm`, `game_3ga`,
                 `game_orb`, `game_drb`, `game_ast`, `game_stl`, `game_tov`, `game_blk`, `game_pf`,
                 `visitor_q1_points`, `visitor_q2_points`, `visitor_q3_points`, `visitor_q4_points`, `visitor_ot_points`,
                 `home_q1_points`, `home_q2_points`, `home_q3_points`, `home_q4_points`, `home_ot_points`,
                 `sim_seed`, `sim_game_type`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "siiiiiiiiiiiiiiiiiiiiiiiiiiiii",
            $gameDate,
            $visitorTeamId,
            $homeTeamId,
            $gameOfThatDay,
            $teamId,
            $game2gm,
            $game2ga,
            $gameFtm,
            $gameFta,
            $game3gm,
            $game3ga,
            $gameOrb,
            $gameDrb,
            $gameAst,
            $gameStl,
            $gameTov,
            $gameBlk,
            $gamePf,
            $visitorQ1,
            $visitorQ2,
            $visitorQ3,
            $visitorQ4,
            $visitorOt,
            $homeQ1,
            $homeQ2,
            $homeQ3,
            $homeQ4,
            $homeOt,
            $simSeed,
            $simGameType,
        );
    }
}
