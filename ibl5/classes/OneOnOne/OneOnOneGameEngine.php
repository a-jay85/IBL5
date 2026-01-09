<?php

declare(strict_types=1);

namespace OneOnOne;

use OneOnOne\Contracts\OneOnOneGameEngineInterface;

/**
 * OneOnOneGameEngine - Simulates One-on-One basketball games
 * 
 * Implements the game simulation logic using player ratings to determine
 * outcomes for shots, blocks, steals, fouls, and rebounds.
 * 
 * @see OneOnOneGameEngineInterface For method contracts
 */
class OneOnOneGameEngine implements OneOnOneGameEngineInterface
{
    private const WINNING_SCORE = 21;
    private const FOUL_DIFFICULTY = 5;
    
    /**
     * Shot type constants for selectShotType return values
     */
    private const SHOT_THREE_POINTER = 0;
    private const SHOT_OUTSIDE_TWO = 1;
    private const SHOT_DRIVE = 2;
    private const SHOT_POST = 3;

    /**
     * Possession result constants
     */
    private const RESULT_FOUL = 1;
    private const RESULT_STEAL = 2;
    private const RESULT_BLOCKED_THREE = 3;
    private const RESULT_MISSED_THREE = 4;
    private const RESULT_MADE_THREE = 5;
    private const RESULT_BLOCKED_OUTSIDE_TWO = 6;
    private const RESULT_MISSED_OUTSIDE_TWO = 7;
    private const RESULT_MADE_OUTSIDE_TWO = 8;
    private const RESULT_BLOCKED_DRIVE = 9;
    private const RESULT_MISSED_DRIVE = 10;
    private const RESULT_MADE_DRIVE = 11;
    private const RESULT_BLOCKED_POST = 12;
    private const RESULT_MISSED_POST = 13;
    private const RESULT_MADE_POST = 14;

    private OneOnOneTextGenerator $textGenerator;

    public function __construct(?OneOnOneTextGenerator $textGenerator = null)
    {
        $this->textGenerator = $textGenerator ?? new OneOnOneTextGenerator();
    }

    /**
     * @see OneOnOneGameEngineInterface::simulateGame()
     */
    public function simulateGame(array $player1Data, array $player2Data, string $owner): OneOnOneGameResult
    {
        $result = new OneOnOneGameResult();
        $result->owner = $owner;
        $result->player1Name = (string) $player1Data['name'];
        $result->player2Name = (string) $player2Data['name'];

        // Coin flip to determine starting possession
        $coinFlip = rand(1, 2);
        $possession = $coinFlip; // 1 = player1, 2 = player2
        $result->coinFlipResult = $this->textGenerator->getCoinFlipText(
            $coinFlip === 1,
            $result->player1Name,
            $result->player2Name
        );
        $result->playByPlay = $result->coinFlipResult;

        // Safety limit to prevent infinite loops
        $maxPossessions = 500;
        $possessionCount = 0;

        // Main game loop
        while (max($result->player1Score, $result->player2Score) < self::WINNING_SCORE && $possessionCount < $maxPossessions) {
            $possessionCount++;
            
            if ($possession === 1) {
                $this->runPossession(
                    $result,
                    $player1Data,
                    $player2Data,
                    $result->player1Stats,
                    $result->player2Stats,
                    true,
                    $possession
                );
            } else {
                $this->runPossession(
                    $result,
                    $player2Data,
                    $player1Data,
                    $result->player2Stats,
                    $result->player1Stats,
                    false,
                    $possession
                );
            }
            
            // Update possession based on result
            $possession = $this->currentPossession;
            
            // Add score update
            $result->playByPlay .= $this->textGenerator->getScoreText(
                $result->player1Name,
                $result->player1Score,
                $result->player2Name,
                $result->player2Score
            );
        }

        // Add final score table
        $result->playByPlay .= $this->generateFinalScoreTable($result);

        return $result;
    }

    private int $currentPossession = 1;

    /**
     * Run a single possession
     */
    private function runPossession(
        OneOnOneGameResult $result,
        array $offenseData,
        array $defenseData,
        OneOnOnePlayerStats $offenseStats,
        OneOnOnePlayerStats $defenseStats,
        bool $isPlayer1OnOffense,
        int $possession
    ): void {
        $offenseName = (string) $offenseData['name'];
        $defenseName = (string) $defenseData['name'];
        
        $possessionResult = $this->calculatePossessionResult($offenseData, $defenseData);
        
        $looseBall = false;
        
        switch ($possessionResult) {
            case self::RESULT_FOUL:
                $result->playByPlay .= $this->textGenerator->getFoulText($defenseName, $offenseName);
                $defenseStats->fouls++;
                // Simulate free throws - offensive player shoots 2 free throws
                $freeThrowsMade = $this->shootFreeThrows((int) $offenseData['r_fta'], 2);
                if ($freeThrowsMade > 0) {
                    $result->playByPlay .= "$offenseName makes $freeThrowsMade of 2 free throws.<br>";
                    if ($isPlayer1OnOffense) {
                        $result->player1Score += $freeThrowsMade;
                    } else {
                        $result->player2Score += $freeThrowsMade;
                    }
                    // Change possession after free throws
                    $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                } else {
                    $result->playByPlay .= "$offenseName misses both free throws.<br>";
                    // Offensive player gets the ball back after missed free throws
                    $this->currentPossession = $isPlayer1OnOffense ? 1 : 2;
                }
                break;
                
            case self::RESULT_STEAL:
                $result->playByPlay .= $this->textGenerator->getStealPlayText($defenseName, $offenseName);
                $offenseStats->turnovers++;
                $defenseStats->steals++;
                $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                break;
                
            case self::RESULT_BLOCKED_THREE:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getThreePointText() 
                    . " but $defenseName " . $this->textGenerator->getBlockText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->threePointersAttempted++;
                $defenseStats->blocks++;
                $looseBall = true;
                break;
                
            case self::RESULT_MISSED_THREE:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getThreePointText() 
                    . " " . $this->textGenerator->getMissedShotText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->threePointersAttempted++;
                $looseBall = true;
                break;
                
            case self::RESULT_MADE_THREE:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getThreePointText() 
                    . " " . $this->textGenerator->getMadeShotText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->threePointersAttempted++;
                $offenseStats->fieldGoalsMade++;
                $offenseStats->threePointersMade++;
                if ($isPlayer1OnOffense) {
                    $result->player1Score += 3;
                } else {
                    $result->player2Score += 3;
                }
                $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                break;
                
            case self::RESULT_BLOCKED_OUTSIDE_TWO:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getOutsideTwoText() 
                    . " but $defenseName " . $this->textGenerator->getBlockText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $defenseStats->blocks++;
                $looseBall = true;
                break;
                
            case self::RESULT_MISSED_OUTSIDE_TWO:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getOutsideTwoText() 
                    . " " . $this->textGenerator->getMissedShotText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $looseBall = true;
                break;
                
            case self::RESULT_MADE_OUTSIDE_TWO:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getOutsideTwoText() 
                    . " " . $this->textGenerator->getMadeShotText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->fieldGoalsMade++;
                if ($isPlayer1OnOffense) {
                    $result->player1Score += 2;
                } else {
                    $result->player2Score += 2;
                }
                $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                break;
                
            case self::RESULT_BLOCKED_DRIVE:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getDriveText() 
                    . " but $defenseName " . $this->textGenerator->getBlockText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $defenseStats->blocks++;
                $looseBall = true;
                break;
                
            case self::RESULT_MISSED_DRIVE:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getDriveText() 
                    . " " . $this->textGenerator->getMissedShotText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $looseBall = true;
                break;
                
            case self::RESULT_MADE_DRIVE:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getDriveText() 
                    . " " . $this->textGenerator->getMadeShotText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->fieldGoalsMade++;
                if ($isPlayer1OnOffense) {
                    $result->player1Score += 2;
                } else {
                    $result->player2Score += 2;
                }
                $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                break;
                
            case self::RESULT_BLOCKED_POST:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getPostText() 
                    . " but $defenseName " . $this->textGenerator->getBlockText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $defenseStats->blocks++;
                $looseBall = true;
                break;
                
            case self::RESULT_MISSED_POST:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getPostText() 
                    . " " . $this->textGenerator->getMissedShotText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $looseBall = true;
                break;
                
            case self::RESULT_MADE_POST:
                $result->playByPlay .= "$offenseName " . $this->textGenerator->getPostText() 
                    . " " . $this->textGenerator->getMadeShotText() . "<br>";
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->fieldGoalsMade++;
                if ($isPlayer1OnOffense) {
                    $result->player1Score += 2;
                } else {
                    $result->player2Score += 2;
                }
                $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                break;
        }
        
        // Handle loose ball (rebound)
        if ($looseBall) {
            $this->handleRebound(
                $result,
                $offenseData,
                $defenseData,
                $offenseStats,
                $defenseStats,
                $offenseName,
                $defenseName,
                $isPlayer1OnOffense
            );
        }
    }

    /**
     * Handle rebound after a missed shot or block
     */
    private function handleRebound(
        OneOnOneGameResult $result,
        array $offenseData,
        array $defenseData,
        OneOnOnePlayerStats $offenseStats,
        OneOnOnePlayerStats $defenseStats,
        string $offenseName,
        string $defenseName,
        bool $isPlayer1OnOffense
    ): void {
        $offReb = (int) $offenseData['r_orb'];
        $defReb = (int) $defenseData['r_drb'];
        
        if ($this->checkRebound($offReb, $defReb)) {
            // Offensive rebound
            $result->playByPlay .= $this->textGenerator->getReboundText($offenseName, true);
            $offenseStats->offensiveRebounds++;
            $offenseStats->totalRebounds++;
            $this->currentPossession = $isPlayer1OnOffense ? 1 : 2;
        } else {
            // Defensive rebound
            $result->playByPlay .= $this->textGenerator->getReboundText($defenseName, false);
            $defenseStats->totalRebounds++;
            $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
        }
    }

    /**
     * Calculate the result of a possession
     * 
     * @param array<string, mixed> $offenseData Offensive player's data
     * @param array<string, mixed> $defenseData Defensive player's data
     * @return int Possession result constant
     */
    private function calculatePossessionResult(array $offenseData, array $defenseData): int
    {
        // Check for foul first
        if ($this->checkFoul((int) $defenseData['r_foul'], (int) $offenseData['r_fta'])) {
            return self::RESULT_FOUL;
        }

        // Check for steal
        if ($this->checkSteal((int) $defenseData['r_stl'], (int) $offenseData['r_to'])) {
            return self::RESULT_STEAL;
        }

        // Select shot type
        $shotType = $this->selectShotType(
            (int) $offenseData['oo'],
            (int) $offenseData['do'],
            (int) $offenseData['po'],
            (int) $offenseData['r_fga'],
            (int) $offenseData['r_tga']
        );

        return $this->processShotAttempt($shotType, $offenseData, $defenseData);
    }

    /**
     * Process a shot attempt and return the result
     */
    private function processShotAttempt(int $shotType, array $offenseData, array $defenseData): int
    {
        $fgp = (int) $offenseData['r_fgp'];
        $tgp = (int) $offenseData['r_tgp'];
        $fta = (int) $offenseData['r_fta'];
        $fga = (int) $offenseData['r_fga'];
        $tga = (int) $offenseData['r_tga'];
        $blk = (int) $defenseData['r_blk'];
        $foul = (int) $defenseData['r_foul'];

        switch ($shotType) {
            case self::SHOT_THREE_POINTER:
                if ($this->checkBlock($blk, $tga)) {
                    return self::RESULT_BLOCKED_THREE;
                }
                if ($this->checkFoul($foul, $fta)) {
                    return $this->checkShot($tgp - self::FOUL_DIFFICULTY, (int) $offenseData['oo'], (int) $defenseData['od'])
                        ? self::RESULT_MADE_THREE
                        : self::RESULT_FOUL;
                }
                return $this->checkShot($tgp, (int) $offenseData['oo'], (int) $defenseData['od'])
                    ? self::RESULT_MADE_THREE
                    : self::RESULT_MISSED_THREE;

            case self::SHOT_OUTSIDE_TWO:
                if ($this->checkBlock($blk, $fga)) {
                    return self::RESULT_BLOCKED_OUTSIDE_TWO;
                }
                if ($this->checkFoul($foul, $fta)) {
                    return $this->checkShot($fgp - self::FOUL_DIFFICULTY, (int) $offenseData['oo'], (int) $defenseData['od'])
                        ? self::RESULT_MADE_OUTSIDE_TWO
                        : self::RESULT_FOUL;
                }
                return $this->checkShot($fgp, (int) $offenseData['oo'], (int) $defenseData['od'])
                    ? self::RESULT_MADE_OUTSIDE_TWO
                    : self::RESULT_MISSED_OUTSIDE_TWO;

            case self::SHOT_DRIVE:
                if ($this->checkBlock($blk, $fga)) {
                    return self::RESULT_BLOCKED_DRIVE;
                }
                if ($this->checkFoul($foul, $fta)) {
                    return $this->checkShot($fgp - self::FOUL_DIFFICULTY, (int) $offenseData['do'], (int) $defenseData['dd'])
                        ? self::RESULT_MADE_DRIVE
                        : self::RESULT_FOUL;
                }
                return $this->checkShot($fgp, (int) $offenseData['do'], (int) $defenseData['dd'])
                    ? self::RESULT_MADE_DRIVE
                    : self::RESULT_MISSED_DRIVE;

            case self::SHOT_POST:
            default:
                if ($this->checkBlock($blk, $fga)) {
                    return self::RESULT_BLOCKED_POST;
                }
                if ($this->checkFoul($foul, $fta)) {
                    return $this->checkShot($fgp - self::FOUL_DIFFICULTY, (int) $offenseData['po'], (int) $defenseData['pd'])
                        ? self::RESULT_MADE_POST
                        : self::RESULT_FOUL;
                }
                return $this->checkShot($fgp, (int) $offenseData['po'], (int) $defenseData['pd'])
                    ? self::RESULT_MADE_POST
                    : self::RESULT_MISSED_POST;
        }
    }

    /**
     * Select the type of shot to attempt
     * 
     * @return int Shot type constant (0=three, 1=outside two, 2=drive, 3=post)
     */
    private function selectShotType(int $outside, int $drive, int $post, int $twoChance, int $threeChance): int
    {
        $shotSelection = $outside + $drive + $post;
        $shotType = rand(0, $shotSelection - 1);
        
        if ($shotType < $outside) {
            $twoOrThree = $twoChance + $threeChance;
            $pickTwoOrThree = rand(0, $twoOrThree - 1);
            return $pickTwoOrThree > $twoChance ? self::SHOT_THREE_POINTER : self::SHOT_OUTSIDE_TWO;
        } elseif ($shotType < ($outside + $drive)) {
            return self::SHOT_DRIVE;
        }
        
        return self::SHOT_POST;
    }

    /**
     * @see OneOnOneGameEngineInterface::checkBlock()
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
     * @see OneOnOneGameEngineInterface::checkSteal()
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
     * @see OneOnOneGameEngineInterface::checkFoul()
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
     * @see OneOnOneGameEngineInterface::checkShot()
     */
    public function checkShot(int $basePercent, int $offenseRating, int $defenseRating): bool
    {
        $shotChance = $basePercent + $offenseRating - ($defenseRating * 2);
        $shotResult = rand(1, 100);
        
        return $shotResult <= $shotChance;
    }

    /**
     * @see OneOnOneGameEngineInterface::checkRebound()
     */
    public function checkRebound(int $offensiveRebound, int $defensiveRebound): bool
    {
        $reboundMatrix = $offensiveRebound + $defensiveRebound + 50;
        $reboundResult = rand(1, $reboundMatrix);
        
        return $reboundResult <= $offensiveRebound;
    }

    /**
     * Simulate free throw attempts
     * 
     * @param int $freeThrowRating Player's free throw rating
     * @param int $attempts Number of free throws to attempt
     * @return int Number of free throws made
     */
    private function shootFreeThrows(int $freeThrowRating, int $attempts): int
    {
        $made = 0;
        // Base free throw percentage is around 75%, modified by rating
        $basePercent = 60 + ($freeThrowRating / 4);
        
        for ($i = 0; $i < $attempts; $i++) {
            if (rand(1, 100) <= $basePercent) {
                $made++;
            }
        }
        
        return $made;
    }

    /**
     * Generate the final score table HTML
     */
    private function generateFinalScoreTable(OneOnOneGameResult $result): string
    {
        $p1Stats = $result->player1Stats;
        $p2Stats = $result->player2Stats;
        
        // Sanitize player names to prevent XSS
        $p1Name = \Utilities\HtmlSanitizer::safeHtmlOutput($result->player1Name);
        $p2Name = \Utilities\HtmlSanitizer::safeHtmlOutput($result->player2Name);
        
        return "<table style=\"border: 1px solid #000; border-collapse: collapse;\"><tr><td colspan=11 style=\"border: 1px solid #000; padding: 4px;\"><span style=\"color: #ff0000;\"><strong style=\"font-weight: bold;\">FINAL SCORE: {$p1Name} {$result->player1Score}, {$p2Name} {$result->player2Score}</strong></span></td></tr>
<tr><td style=\"border: 1px solid #000; padding: 4px;\">Name</td><td style=\"border: 1px solid #000; padding: 4px;\">FGM</td><td style=\"border: 1px solid #000; padding: 4px;\">FGA</td><td style=\"border: 1px solid #000; padding: 4px;\">3GM</td><td style=\"border: 1px solid #000; padding: 4px;\">3GA</td><td style=\"border: 1px solid #000; padding: 4px;\">ORB</td><td style=\"border: 1px solid #000; padding: 4px;\">REB</td><td style=\"border: 1px solid #000; padding: 4px;\">STL</td><td style=\"border: 1px solid #000; padding: 4px;\">BLK</td><td style=\"border: 1px solid #000; padding: 4px;\">TVR</td><td style=\"border: 1px solid #000; padding: 4px;\">FOUL</td></tr>
<tr><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Name}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->fieldGoalsMade}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->fieldGoalsAttempted}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->threePointersMade}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->threePointersAttempted}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->offensiveRebounds}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->totalRebounds}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->steals}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->blocks}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->turnovers}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p1Stats->fouls}</td></tr>
<tr><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Name}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->fieldGoalsMade}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->fieldGoalsAttempted}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->threePointersMade}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->threePointersAttempted}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->offensiveRebounds}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->totalRebounds}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->steals}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->blocks}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->turnovers}</td><td style=\"border: 1px solid #000; padding: 4px;\">{$p2Stats->fouls}</td></tr>
</table>
";
    }
}
