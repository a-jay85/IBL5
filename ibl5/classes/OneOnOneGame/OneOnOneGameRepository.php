<?php

declare(strict_types=1);

namespace OneOnOneGame;

use BaseMysqliRepository;
use OneOnOneGame\Contracts\OneOnOneGameEngineInterface;
use OneOnOneGame\Contracts\OneOnOneGameRepositoryInterface;

/**
 * OneOnOneGameRepository - Database operations for One-on-One games
 *
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 *
 * @see OneOnOneGameRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 * @phpstan-import-type PlayerGameData from OneOnOneGameEngineInterface
 * @phpstan-import-type GameRecord from OneOnOneGameRepositoryInterface
 */
class OneOnOneGameRepository extends BaseMysqliRepository implements OneOnOneGameRepositoryInterface
{
    /**
     * @see OneOnOneGameRepositoryInterface::getActivePlayers()
     *
     * @return array<int, array{pid: int, name: string}>
     */
    public function getActivePlayers(): array
    {
        /** @var array<int, array{pid: int, name: string}> */
        return $this->fetchAll(
            "SELECT pid, name FROM ibl_plr WHERE retired = '0' AND name NOT LIKE '|%' AND name != '(no starter)' ORDER BY name ASC",
            ""
        );
    }

    /**
     * @see OneOnOneGameRepositoryInterface::getPlayerForGame()
     *
     * @return PlayerGameData|null
     */
    public function getPlayerForGame(int $playerId): ?array
    {
        /** @var PlayerGameData|null */
        return $this->fetchOne(
            "SELECT pid, name,
                    oo, `do`, po, od, dd, pd,
                    r_fga, r_fgp, r_fta, r_tga, r_tgp,
                    r_orb, r_drb, r_stl, r_to, r_blk, r_foul
             FROM ibl_plr
             WHERE pid = ?",
            "i",
            $playerId
        );
    }

    /**
     * @see OneOnOneGameRepositoryInterface::getNextGameId()
     */
    public function getNextGameId(): int
    {
        /** @var array{gameid: int}|null $row */
        $row = $this->fetchOne(
            "SELECT gameid FROM ibl_one_on_one ORDER BY gameid DESC LIMIT 1",
            ""
        );

        return ($row !== null ? $row['gameid'] : 0) + 1;
    }

    /**
     * @see OneOnOneGameRepositoryInterface::saveGame()
     */
    public function saveGame(OneOnOneGameResult $result): int
    {
        $gameId = $this->getNextGameId();
        $result->gameId = $gameId;

        $winner = $result->getWinnerName();
        $loser = $result->getLoserName();
        $winScore = $result->getWinnerScore();
        $lossScore = $result->getLoserScore();

        $this->execute(
            "INSERT INTO ibl_one_on_one (gameid, playbyplay, winner, loser, winscore, lossscore, owner)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            "isssiss",
            $gameId,
            $result->playByPlay,
            $winner,
            $loser,
            $winScore,
            $lossScore,
            $result->owner
        );

        return $gameId;
    }

    /**
     * @see OneOnOneGameRepositoryInterface::getGameById()
     *
     * @return GameRecord|null
     */
    public function getGameById(int $gameId): ?array
    {
        /** @var GameRecord|null */
        return $this->fetchOne(
            "SELECT gameid, playbyplay, winner, loser, winscore, lossscore, owner
             FROM ibl_one_on_one
             WHERE gameid = ?",
            "i",
            $gameId
        );
    }
}
