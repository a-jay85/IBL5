<?php

declare(strict_types=1);

namespace OneOnOne;

use BaseMysqliRepository;
use OneOnOne\Contracts\OneOnOneRepositoryInterface;

/**
 * OneOnOneRepository - Database operations for One-on-One games
 * 
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * 
 * @see OneOnOneRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 */
class OneOnOneRepository extends BaseMysqliRepository implements OneOnOneRepositoryInterface
{
    /**
     * @see OneOnOneRepositoryInterface::getActivePlayers()
     */
    public function getActivePlayers(): array
    {
        return $this->fetchAll(
            "SELECT pid, name FROM ibl_plr WHERE retired = '0' AND name NOT LIKE '|%' AND name != '(no starter)' ORDER BY name ASC",
            ""
        );
    }

    /**
     * @see OneOnOneRepositoryInterface::getPlayerForGame()
     */
    public function getPlayerForGame(int $playerId): ?array
    {
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
     * @see OneOnOneRepositoryInterface::getNextGameId()
     */
    public function getNextGameId(): int
    {
        $row = $this->fetchOne(
            "SELECT gameid FROM ibl_one_on_one ORDER BY gameid DESC LIMIT 1",
            ""
        );
        
        return ($row !== null ? (int) $row['gameid'] : 0) + 1;
    }

    /**
     * @see OneOnOneRepositoryInterface::saveGame()
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
     * @see OneOnOneRepositoryInterface::getGameById()
     */
    public function getGameById(int $gameId): ?array
    {
        return $this->fetchOne(
            "SELECT gameid, playbyplay, winner, loser, winscore, lossscore, owner 
             FROM ibl_one_on_one 
             WHERE gameid = ?",
            "i",
            $gameId
        );
    }
}
