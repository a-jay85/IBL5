<?php

declare(strict_types=1);

namespace OneOnOneGame\Contracts;

/**
 * OneOnOneGameShotResultResolverInterface - Contract for resolving a shot attempt
 *
 * Given a selected shot type and the offensive/defensive player data, resolves
 * the outcome of the attempt (blocked, missed, made, or fouled) as one of the
 * RESULT_* constants. Owns the shared foul, block, and shot dice rolls.
 *
 * @phpstan-import-type PlayerGameData from OneOnOneGameEngineInterface
 */
interface OneOnOneGameShotResultResolverInterface
{
    /**
     * Shot type constants for selectShotType return values
     */
    public const SHOT_THREE_POINTER = 0;
    public const SHOT_OUTSIDE_TWO = 1;
    public const SHOT_DRIVE = 2;
    public const SHOT_POST = 3;

    /**
     * Possession result constants
     */
    public const RESULT_FOUL = 1;
    public const RESULT_STEAL = 2;
    public const RESULT_BLOCKED_THREE = 3;
    public const RESULT_MISSED_THREE = 4;
    public const RESULT_MADE_THREE = 5;
    public const RESULT_BLOCKED_OUTSIDE_TWO = 6;
    public const RESULT_MISSED_OUTSIDE_TWO = 7;
    public const RESULT_MADE_OUTSIDE_TWO = 8;
    public const RESULT_BLOCKED_DRIVE = 9;
    public const RESULT_MISSED_DRIVE = 10;
    public const RESULT_MADE_DRIVE = 11;
    public const RESULT_BLOCKED_POST = 12;
    public const RESULT_MISSED_POST = 13;
    public const RESULT_MADE_POST = 14;

    /**
     * Resolve a shot attempt and return the result
     *
     * @param int $shotType One of the SHOT_* constants
     * @param PlayerGameData $offenseData
     * @param PlayerGameData $defenseData
     * @return int one of the RESULT_* constants
     */
    public function resolve(int $shotType, array $offenseData, array $defenseData): int;

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
}
