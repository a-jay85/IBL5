<?php

declare(strict_types=1);

namespace OneOnOneGame;

use OneOnOneGame\Contracts\OneOnOneGameEngineInterface;
use OneOnOneGame\Contracts\OneOnOneGamePossessionResolverInterface;
use OneOnOneGame\Contracts\OneOnOneGameShotResultResolverInterface;
use Security\HtmlSanitizer;

/**
 * OneOnOneGameEngine - Simulates One-on-One basketball games
 *
 * WARNING: This is a fan-created mini-game. It is NOT a representation of how the
 * Jump Shot Basketball (JSB) simulation engine works. While it uses player ratings,
 * the game mechanics here are original and should not be used to understand JSB logic.
 *
 * Orchestrates the game loop and delegates possession outcome selection to
 * OneOnOneGamePossessionResolverInterface and shot resolution to
 * OneOnOneGameShotResultResolverInterface. The play-by-play transcript is produced
 * by an ordered sequence of rand() draws whose count and order are frozen by the
 * characterization pins in OneOnOneGameEngineCharacterizationTest.
 *
 * @see OneOnOneGameEngineInterface For method contracts
 * @phpstan-import-type PlayerGameData from OneOnOneGameEngineInterface
 */
class OneOnOneGameEngine implements OneOnOneGameEngineInterface
{
    private const WINNING_SCORE = 21;

    private OneOnOneGameTextGenerator $textGenerator;
    private OneOnOneGamePossessionResolverInterface $possessionResolver;

    private int $currentPossession = 1;

    public function __construct(
        ?OneOnOneGameTextGenerator $textGenerator = null,
        ?OneOnOneGamePossessionResolverInterface $possessionResolver = null,
        ?OneOnOneGameShotResultResolverInterface $shotResultResolver = null
    ) {
        $this->textGenerator = $textGenerator ?? new OneOnOneGameTextGenerator();
        $this->possessionResolver = $possessionResolver
            ?? new OneOnOneGamePossessionResolver($shotResultResolver ?? new OneOnOneGameShotResultResolver());
    }

    /**
     * @see OneOnOneGameEngineInterface::simulateGame()
     *
     * @param PlayerGameData $player1Data
     * @param PlayerGameData $player2Data
     */
    public function simulateGame(array $player1Data, array $player2Data, string $owner): OneOnOneGameResult
    {
        $result = new OneOnOneGameResult();
        $sanitizedOwner = HtmlSanitizer::safeHtmlOutput($owner);
        $result->owner = $sanitizedOwner;
        $sanitizedP1Name = HtmlSanitizer::safeHtmlOutput($player1Data['name']);
        $result->player1Name = $sanitizedP1Name;
        $sanitizedP2Name = HtmlSanitizer::safeHtmlOutput($player2Data['name']);
        $result->player2Name = $sanitizedP2Name;

        // Coin flip to determine starting possession
        $coinFlip = rand(1, 2);
        $possession = $coinFlip; // 1 = player1, 2 = player2

        // Reset per-game possession state so a prior simulateGame() call on this
        // same instance cannot leak a stale possession into this run.
        $this->currentPossession = $possession;
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

    /**
     * Run a single possession
     *
     * @param PlayerGameData $offenseData
     * @param PlayerGameData $defenseData
     */
    private function runPossession(
        OneOnOneGameResult $result,
        array $offenseData,
        array $defenseData,
        OneOnOneGamePlayerStats $offenseStats,
        OneOnOneGamePlayerStats $defenseStats,
        bool $isPlayer1OnOffense,
        int $possession
    ): void {
        $offenseName = HtmlSanitizer::safeHtmlOutput($offenseData['name']);
        $defenseName = HtmlSanitizer::safeHtmlOutput($defenseData['name']);

        $possessionResult = $this->possessionResolver->resolve($offenseData, $defenseData);

        $looseBall = false;

        switch ($possessionResult) {
            case OneOnOneGameShotResultResolverInterface::RESULT_FOUL:
                $result->playByPlay .= $this->textGenerator->getFoulText($defenseName, $offenseName);
                $defenseStats->fouls++;
                // Simulate free throws - offensive player shoots 2 free throws
                $freeThrowsMade = $this->shootFreeThrows($offenseData['r_fta'], 2);
                $offenseStats->freeThrowsAttempted += 2;
                $offenseStats->freeThrowsMade += $freeThrowsMade;
                if ($freeThrowsMade > 0) {
                    $result->playByPlay .= $offenseName . ' makes ' . $freeThrowsMade . ' of 2 free throws.<br>';
                    if ($isPlayer1OnOffense) {
                        $result->player1Score += $freeThrowsMade;
                    } else {
                        $result->player2Score += $freeThrowsMade;
                    }
                    // Change possession after free throws
                    $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                } else {
                    $result->playByPlay .= $offenseName . ' misses both free throws.<br>';
                    // Offensive player gets the ball back after missed free throws
                    $this->currentPossession = $isPlayer1OnOffense ? 1 : 2;
                }
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_STEAL:
                $result->playByPlay .= $this->textGenerator->getStealPlayText($defenseName, $offenseName);
                $offenseStats->turnovers++;
                $defenseStats->steals++;
                $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_BLOCKED_THREE:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getThreePointText()
                    . ' but ' . $defenseName . ' ' . $this->textGenerator->getBlockText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->threePointersAttempted++;
                $defenseStats->blocks++;
                $looseBall = true;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_MISSED_THREE:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getThreePointText()
                    . ' ' . $this->textGenerator->getMissedShotText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->threePointersAttempted++;
                $looseBall = true;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_MADE_THREE:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getThreePointText()
                    . ' ' . $this->textGenerator->getMadeShotText() . '<br>';
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

            case OneOnOneGameShotResultResolverInterface::RESULT_BLOCKED_OUTSIDE_TWO:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getOutsideTwoText()
                    . ' but ' . $defenseName . ' ' . $this->textGenerator->getBlockText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $defenseStats->blocks++;
                $looseBall = true;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_MISSED_OUTSIDE_TWO:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getOutsideTwoText()
                    . ' ' . $this->textGenerator->getMissedShotText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $looseBall = true;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_MADE_OUTSIDE_TWO:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getOutsideTwoText()
                    . ' ' . $this->textGenerator->getMadeShotText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->fieldGoalsMade++;
                if ($isPlayer1OnOffense) {
                    $result->player1Score += 2;
                } else {
                    $result->player2Score += 2;
                }
                $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_BLOCKED_DRIVE:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getDriveText()
                    . ' but ' . $defenseName . ' ' . $this->textGenerator->getBlockText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $defenseStats->blocks++;
                $looseBall = true;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_MISSED_DRIVE:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getDriveText()
                    . ' ' . $this->textGenerator->getMissedShotText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $looseBall = true;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_MADE_DRIVE:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getDriveText()
                    . ' ' . $this->textGenerator->getMadeShotText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $offenseStats->fieldGoalsMade++;
                if ($isPlayer1OnOffense) {
                    $result->player1Score += 2;
                } else {
                    $result->player2Score += 2;
                }
                $this->currentPossession = $isPlayer1OnOffense ? 2 : 1;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_BLOCKED_POST:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getPostText()
                    . ' but ' . $defenseName . ' ' . $this->textGenerator->getBlockText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $defenseStats->blocks++;
                $looseBall = true;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_MISSED_POST:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getPostText()
                    . ' ' . $this->textGenerator->getMissedShotText() . '<br>';
                $offenseStats->fieldGoalsAttempted++;
                $looseBall = true;
                break;

            case OneOnOneGameShotResultResolverInterface::RESULT_MADE_POST:
                $result->playByPlay .= $offenseName . ' ' . $this->textGenerator->getPostText()
                    . ' ' . $this->textGenerator->getMadeShotText() . '<br>';
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
     *
     * @param PlayerGameData $offenseData
     * @param PlayerGameData $defenseData
     */
    private function handleRebound(
        OneOnOneGameResult $result,
        array $offenseData,
        array $defenseData,
        OneOnOneGamePlayerStats $offenseStats,
        OneOnOneGamePlayerStats $defenseStats,
        string $offenseName,
        string $defenseName,
        bool $isPlayer1OnOffense
    ): void {
        $offReb = $offenseData['r_orb'];
        $defReb = $defenseData['r_drb'];

        if ($this->possessionResolver->checkRebound($offReb, $defReb)) {
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

        // Player names are already sanitized in simulateGame()
        $p1Name = $result->player1Name;
        $p2Name = $result->player2Name;

        return '<div class="table-scroll-wrapper"><div class="table-scroll-container">'
            . '<table class="ibl-data-table">'
            . '<thead><tr><th colspan="13"><span class="text-accent-500">FINAL SCORE: ' . $p1Name . ' ' . $result->player1Score . ', ' . $p2Name . ' ' . $result->player2Score . '</span></th></tr>'
            . '<tr><th>Name</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>STL</th><th>BLK</th><th>TVR</th><th>FOUL</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>' . $p1Name . '</td><td>' . $p1Stats->fieldGoalsMade . '</td><td>' . $p1Stats->fieldGoalsAttempted . '</td><td>' . $p1Stats->freeThrowsMade . '</td><td>' . $p1Stats->freeThrowsAttempted . '</td><td>' . $p1Stats->threePointersMade . '</td><td>' . $p1Stats->threePointersAttempted . '</td><td>' . $p1Stats->offensiveRebounds . '</td><td>' . $p1Stats->totalRebounds . '</td><td>' . $p1Stats->steals . '</td><td>' . $p1Stats->blocks . '</td><td>' . $p1Stats->turnovers . '</td><td>' . $p1Stats->fouls . '</td></tr>'
            . '<tr><td>' . $p2Name . '</td><td>' . $p2Stats->fieldGoalsMade . '</td><td>' . $p2Stats->fieldGoalsAttempted . '</td><td>' . $p2Stats->freeThrowsMade . '</td><td>' . $p2Stats->freeThrowsAttempted . '</td><td>' . $p2Stats->threePointersMade . '</td><td>' . $p2Stats->threePointersAttempted . '</td><td>' . $p2Stats->offensiveRebounds . '</td><td>' . $p2Stats->totalRebounds . '</td><td>' . $p2Stats->steals . '</td><td>' . $p2Stats->blocks . '</td><td>' . $p2Stats->turnovers . '</td><td>' . $p2Stats->fouls . '</td></tr>'
            . '</tbody></table>'
            . '</div></div>'
            . "\n";
    }
}
