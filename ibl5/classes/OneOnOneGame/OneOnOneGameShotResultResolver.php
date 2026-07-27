<?php

declare(strict_types=1);

namespace OneOnOneGame;

use OneOnOneGame\Contracts\OneOnOneGameShotResultResolverInterface;

/**
 * OneOnOneGameShotResultResolver - Resolves the outcome of a shot attempt
 *
 * WARNING: This is a fan-created mini-game. It is NOT a representation of how the
 * Jump Shot Basketball (JSB) simulation engine works.
 *
 * Given a shot type and player data, resolves the attempt to one of the RESULT_*
 * constants via an ordered sequence of block, foul, and shot dice rolls. The draw
 * order inside resolve() is a simulation-fidelity contract — see the
 * characterization pins in OneOnOneGameEngineCharacterizationTest.
 *
 * @see OneOnOneGameShotResultResolverInterface For method contracts
 * @phpstan-import-type PlayerGameData from \OneOnOneGame\Contracts\OneOnOneGameEngineInterface
 */
class OneOnOneGameShotResultResolver implements OneOnOneGameShotResultResolverInterface
{
    private const FOUL_DIFFICULTY = 5;

    /**
     * Process a shot attempt and return the result
     *
     * @param PlayerGameData $offenseData
     * @param PlayerGameData $defenseData
     */
    public function resolve(int $shotType, array $offenseData, array $defenseData): int
    {
        $fgp = $offenseData['r_fgp'];
        $tgp = $offenseData['r_3gp'];
        $fta = $offenseData['r_fta'];
        $fga = $offenseData['r_fga'];
        $tga = $offenseData['r_3ga'];
        $blk = $defenseData['r_blk'];
        $foul = $defenseData['r_foul'];

        switch ($shotType) {
            case self::SHOT_THREE_POINTER:
                if ($this->checkBlock($blk, $tga)) {
                    return self::RESULT_BLOCKED_THREE;
                }
                if ($this->checkFoul($foul, $fta)) {
                    return $this->checkShot($tgp - self::FOUL_DIFFICULTY, $offenseData['oo'], $defenseData['od'])
                        ? self::RESULT_MADE_THREE
                        : self::RESULT_FOUL;
                }
                return $this->checkShot($tgp, $offenseData['oo'], $defenseData['od'])
                    ? self::RESULT_MADE_THREE
                    : self::RESULT_MISSED_THREE;

            case self::SHOT_OUTSIDE_TWO:
                if ($this->checkBlock($blk, $fga)) {
                    return self::RESULT_BLOCKED_OUTSIDE_TWO;
                }
                if ($this->checkFoul($foul, $fta)) {
                    return $this->checkShot($fgp - self::FOUL_DIFFICULTY, $offenseData['oo'], $defenseData['od'])
                        ? self::RESULT_MADE_OUTSIDE_TWO
                        : self::RESULT_FOUL;
                }
                return $this->checkShot($fgp, $offenseData['oo'], $defenseData['od'])
                    ? self::RESULT_MADE_OUTSIDE_TWO
                    : self::RESULT_MISSED_OUTSIDE_TWO;

            case self::SHOT_DRIVE:
                if ($this->checkBlock($blk, $fga)) {
                    return self::RESULT_BLOCKED_DRIVE;
                }
                if ($this->checkFoul($foul, $fta)) {
                    return $this->checkShot($fgp - self::FOUL_DIFFICULTY, $offenseData['r_drive_off'], $defenseData['dd'])
                        ? self::RESULT_MADE_DRIVE
                        : self::RESULT_FOUL;
                }
                return $this->checkShot($fgp, $offenseData['r_drive_off'], $defenseData['dd'])
                    ? self::RESULT_MADE_DRIVE
                    : self::RESULT_MISSED_DRIVE;

            case self::SHOT_POST:
            default:
                if ($this->checkBlock($blk, $fga)) {
                    return self::RESULT_BLOCKED_POST;
                }
                if ($this->checkFoul($foul, $fta)) {
                    return $this->checkShot($fgp - self::FOUL_DIFFICULTY, $offenseData['po'], $defenseData['pd'])
                        ? self::RESULT_MADE_POST
                        : self::RESULT_FOUL;
                }
                return $this->checkShot($fgp, $offenseData['po'], $defenseData['pd'])
                    ? self::RESULT_MADE_POST
                    : self::RESULT_MISSED_POST;
        }
    }

    /**
     * @see OneOnOneGameShotResultResolverInterface::checkBlock()
     */
    public function checkBlock(int $blockRating, int $attemptRating): bool
    {
        $blockCount = 0;

        for ($i = 0; $i < 3; $i++) {
            $makeBlock = $blockRating + rand(1, 100) + rand(1, 100);
            $avoidBlock = $attemptRating + rand(1, 100) + rand(1, 100);
            if ($makeBlock > $avoidBlock) {
                $blockCount++;
            }
        }

        return $blockCount === 3;
    }

    /**
     * @see OneOnOneGameShotResultResolverInterface::checkFoul()
     */
    public function checkFoul(int $foulRating, int $drawFoulRating): bool
    {
        $foulCount = 0;

        for ($i = 0; $i < 5; $i++) {
            $drawFoul = $drawFoulRating + rand(1, 100) + rand(1, 100);
            $avoidFoul = $foulRating + rand(1, 100) + rand(1, 100);
            if ($drawFoul > $avoidFoul) {
                $foulCount++;
            }
        }

        return $foulCount > 3;
    }

    /**
     * @see OneOnOneGameShotResultResolverInterface::checkShot()
     */
    public function checkShot(int $basePercent, int $offenseRating, int $defenseRating): bool
    {
        $shotChance = $basePercent + $offenseRating - ($defenseRating * 2);
        $shotResult = rand(1, 100);

        return $shotResult <= $shotChance;
    }
}
