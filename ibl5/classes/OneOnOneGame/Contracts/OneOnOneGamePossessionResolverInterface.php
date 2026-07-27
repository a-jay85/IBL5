<?php

declare(strict_types=1);

namespace OneOnOneGame\Contracts;

/**
 * OneOnOneGamePossessionResolverInterface - Contract for resolving a possession
 *
 * Determines the outcome of a single possession: it checks for a foul, then a
 * steal, then selects a shot type and delegates shot resolution to the
 * OneOnOneGameShotResultResolverInterface. Also owns the rebound roll that
 * decides who gets the ball after a loose ball.
 *
 * @phpstan-import-type PlayerGameData from OneOnOneGameEngineInterface
 */
interface OneOnOneGamePossessionResolverInterface
{
    /**
     * Resolve a single possession and return its result
     *
     * @param PlayerGameData $offenseData Offensive player's data
     * @param PlayerGameData $defenseData Defensive player's data
     * @return int one of OneOnOneGameShotResultResolverInterface::RESULT_*
     */
    public function resolve(array $offenseData, array $defenseData): int;

    /**
     * Select the type of shot to attempt
     *
     * @return int one of OneOnOneGameShotResultResolverInterface::SHOT_*
     */
    public function selectShotType(int $outside, int $drive, int $post, int $twoChance, int $threeChance): int;

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
