<?php

declare(strict_types=1);

namespace OneOnOneGame;

use OneOnOneGame\Contracts\OneOnOneGamePossessionResolverInterface;
use OneOnOneGame\Contracts\OneOnOneGameShotResultResolverInterface;

/**
 * OneOnOneGamePossessionResolver - Resolves a single possession
 *
 * WARNING: This is a fan-created mini-game. It is NOT a representation of how the
 * Jump Shot Basketball (JSB) simulation engine works.
 *
 * Checks for a foul, then a steal, then selects a shot type and delegates shot
 * resolution to OneOnOneGameShotResultResolverInterface. The order of these
 * checks is a simulation-fidelity contract — see the characterization pins in
 * OneOnOneGameEngineCharacterizationTest.
 *
 * @see OneOnOneGamePossessionResolverInterface For method contracts
 * @phpstan-import-type PlayerGameData from \OneOnOneGame\Contracts\OneOnOneGameEngineInterface
 */
class OneOnOneGamePossessionResolver implements OneOnOneGamePossessionResolverInterface
{
    private OneOnOneGameShotResultResolverInterface $shotResultResolver;

    public function __construct(?OneOnOneGameShotResultResolverInterface $shotResultResolver = null)
    {
        $this->shotResultResolver = $shotResultResolver ?? new OneOnOneGameShotResultResolver();
    }

    /**
     * Calculate the result of a possession
     *
     * @param PlayerGameData $offenseData Offensive player's data
     * @param PlayerGameData $defenseData Defensive player's data
     * @return int Possession result constant
     */
    public function resolve(array $offenseData, array $defenseData): int
    {
        // Check for foul first
        if ($this->shotResultResolver->checkFoul($defenseData['r_foul'], $offenseData['r_fta'])) {
            return OneOnOneGameShotResultResolverInterface::RESULT_FOUL;
        }

        // Check for steal
        if ($this->checkSteal($defenseData['r_stl'], $offenseData['r_tvr'])) {
            return OneOnOneGameShotResultResolverInterface::RESULT_STEAL;
        }

        // Select shot type
        $shotType = $this->selectShotType(
            $offenseData['oo'],
            $offenseData['r_drive_off'],
            $offenseData['po'],
            $offenseData['r_fga'],
            $offenseData['r_3ga']
        );

        return $this->shotResultResolver->resolve($shotType, $offenseData, $defenseData);
    }

    /**
     * @see OneOnOneGamePossessionResolverInterface::selectShotType()
     */
    public function selectShotType(int $outside, int $drive, int $post, int $twoChance, int $threeChance): int
    {
        $shotSelection = $outside + $drive + $post;
        $shotType = rand(0, $shotSelection - 1);

        if ($shotType < $outside) {
            $twoOrThree = $twoChance + $threeChance;
            $pickTwoOrThree = rand(0, $twoOrThree - 1);
            return $pickTwoOrThree > $twoChance
                ? OneOnOneGameShotResultResolverInterface::SHOT_THREE_POINTER
                : OneOnOneGameShotResultResolverInterface::SHOT_OUTSIDE_TWO;
        } elseif ($shotType < ($outside + $drive)) {
            return OneOnOneGameShotResultResolverInterface::SHOT_DRIVE;
        }

        return OneOnOneGameShotResultResolverInterface::SHOT_POST;
    }

    /**
     * @see OneOnOneGamePossessionResolverInterface::checkSteal()
     */
    public function checkSteal(int $stealRating, int $turnoverRating): bool
    {
        $stealCount = 0;

        for ($i = 0; $i < 3; $i++) {
            $makeSteal = $stealRating + rand(1, 100) + rand(1, 100);
            $avoidSteal = $turnoverRating + rand(1, 100) + rand(1, 100);
            if ($makeSteal > $avoidSteal) {
                $stealCount++;
            }
        }

        return $stealCount === 3;
    }

    /**
     * @see OneOnOneGamePossessionResolverInterface::checkRebound()
     */
    public function checkRebound(int $offensiveRebound, int $defensiveRebound): bool
    {
        $reboundMatrix = $offensiveRebound + $defensiveRebound + 50;
        $reboundResult = rand(1, $reboundMatrix);

        return $reboundResult <= $offensiveRebound;
    }
}
