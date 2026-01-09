<?php

declare(strict_types=1);

namespace OneOnOne\Contracts;

use OneOnOne\OneOnOneGameResult;

/**
 * OneOnOneGameEngineInterface - Contract for One-on-One game simulation
 * 
 * Defines the interface for simulating a One-on-One basketball game
 * between two players. The engine handles all game mechanics including
 * shot selection, shooting, blocking, stealing, fouls, and rebounds.
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
     * @param array<string, mixed> $player1Data Player 1 ratings and attributes from database
     * @param array<string, mixed> $player2Data Player 2 ratings and attributes from database
     * @param string $owner Username of the person running the game
     * @return OneOnOneGameResult Complete game result with stats and play-by-play
     */
    public function simulateGame(array $player1Data, array $player2Data, string $owner): OneOnOneGameResult;

    /**
     * Check if a shot attempt is blocked
     * 
     * Compares defender's block rating against attacker's shot attempt rating.
     * Uses multiple random checks to determine if a block occurs.
     * 
     * @param int $blockRating Defender's block rating
     * @param int $attemptRating Attacker's shot attempt rating
     * @return bool True if the shot is blocked
     */
    public function checkBlock(int $blockRating, int $attemptRating): bool;

    /**
     * Check if the ball is stolen
     * 
     * Compares defender's steal rating against attacker's turnover tendency.
     * Uses multiple random checks to determine if a steal occurs.
     * 
     * @param int $stealRating Defender's steal rating
     * @param int $turnoverRating Attacker's turnover rating
     * @return bool True if the ball is stolen
     */
    public function checkSteal(int $stealRating, int $turnoverRating): bool;

    /**
     * Check if a foul occurs
     * 
     * Compares defender's foul tendency against attacker's ability to draw fouls.
     * Uses multiple random checks to determine if a foul is called.
     * 
     * @param int $foulRating Defender's foul rating (tendency to foul)
     * @param int $drawFoulRating Attacker's ability to draw fouls
     * @return bool True if a foul is called
     */
    public function checkFoul(int $foulRating, int $drawFoulRating): bool;

    /**
     * Determine if a shot attempt is made
     * 
     * Calculates shot success based on base percentage, offensive rating,
     * and defensive rating of the opponent.
     * 
     * @param int $basePercent Base shooting percentage for the shot type
     * @param int $offenseRating Attacker's offensive rating for shot type
     * @param int $defenseRating Defender's defensive rating for shot type
     * @return bool True if the shot is made
     */
    public function checkShot(int $basePercent, int $offenseRating, int $defenseRating): bool;

    /**
     * Determine who gets the rebound
     * 
     * Compares offensive and defensive rebounding ratings to determine
     * whether the shooter gets an offensive rebound or defender gets
     * a defensive rebound.
     * 
     * @param int $offensiveRebound Shooter's offensive rebound rating
     * @param int $defensiveRebound Defender's defensive rebound rating
     * @return bool True if offensive rebound (shooter), false if defensive
     */
    public function checkRebound(int $offensiveRebound, int $defensiveRebound): bool;
}
