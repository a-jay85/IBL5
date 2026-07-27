<?php

declare(strict_types=1);

namespace OneOnOneGame\Contracts;

use OneOnOneGame\OneOnOneGameResult;

/**
 * OneOnOneGameEngineInterface - Contract for One-on-One game simulation
 *
 * Defines the interface for simulating a One-on-One basketball game
 * between two players. The engine handles all game mechanics including
 * shot selection, shooting, blocking, stealing, fouls, and rebounds.
 *
 * @phpstan-type PlayerGameData array{pid: int, name: string, oo: int, r_drive_off: int, po: int, od: int, dd: int, pd: int, r_fga: int, r_fgp: int, r_fta: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_stl: int, r_tvr: int, r_blk: int, r_foul: int}
 */
interface OneOnOneGameEngineInterface
{
    /**
     * Simulate a complete One-on-One game between two players
     *
     * Runs a full game simulation to 21 points using player ratings
     * to determine outcomes. Generates play-by-play text and tracks
     * all statistics for both players.
     *
     * @param PlayerGameData $player1Data Player 1 ratings and attributes from database
     * @param PlayerGameData $player2Data Player 2 ratings and attributes from database
     * @param string $owner Username of the person running the game
     * @return OneOnOneGameResult Complete game result with stats and play-by-play
     */
    public function simulateGame(array $player1Data, array $player2Data, string $owner): OneOnOneGameResult;
}
