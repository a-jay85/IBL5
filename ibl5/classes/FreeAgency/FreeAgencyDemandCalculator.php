<?php

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyDemandCalculatorInterface;
use Player\Player;

/**
 * @see FreeAgencyDemandCalculatorInterface
 */
class FreeAgencyDemandCalculator implements FreeAgencyDemandCalculatorInterface
{
    private const PLAY_FOR_WINNER_FACTOR = 0.000153;
    private const TRADITION_FACTOR = 0.000153;
    private const LOYALTY_BONUS_PERCENTAGE = 0.025;
    private const SECURITY_BASE_FACTOR = -0.025;
    private const SECURITY_YEAR_FACTOR = 0.01;
    private const PLAYING_TIME_BASE_FACTOR = 0.025;
    private const PLAYING_TIME_MONEY_FACTOR = 0.0025;
    private const PLAYING_TIME_DIVISOR = 100;
    private const MAX_POSITION_SALARY_CAP = 2000;
    private const RANDOM_VARIANCE_MIN = -5;
    private const RANDOM_VARIANCE_MAX = 5;
    private const RANDOM_VARIANCE_BASE = 100;

    private FreeAgencyDemandRepositoryInterface $repository;
    private ?int $randomFactor = null;

    public function __construct(FreeAgencyDemandRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see FreeAgencyDemandCalculatorInterface::setRandomFactor()
     */
    public function setRandomFactor(?int $factor): void
    {
        $this->randomFactor = $factor;
    }

    /**
     * Calculate perceived value of an offer with team modifiers applied
     * 
     * @param int $offerAverage Average salary offered per year
     * @param string $teamName Offering team name
     * @param Player $player Player object with preferences
     * @param int $yearsInOffer Number of years in the offer
     * @return float Perceived value after modifiers
     */
    public function calculatePerceivedValue(
        int $offerAverage,
        string $teamName,
        Player $player,
        int $yearsInOffer
    ): float {
        // Get team performance data
        $teamPerformance = $this->repository->getTeamPerformance($teamName);
        
        // Calculate position salary
        $positionSalary = $this->calculatePositionSalary($teamName, $player);
        
        // Calculate modifiers
        $modifier = $this->calculateModifier(
            $teamPerformance['wins'],
            $teamPerformance['losses'],
            $teamPerformance['tradWins'],
            $teamPerformance['tradLosses'],
            $player->freeAgencyPlayForWinner,
            $player->freeAgencyTradition,
            $player->freeAgencyLoyalty,
            $player->freeAgencySecurity,
            $player->freeAgencyPlayingTime,
            $teamName,
            $player->teamName,
            $yearsInOffer,
            $positionSalary
        );
        
        // Apply random variance
        $random = $this->getRandomFactor();
        $modRandom = (self::RANDOM_VARIANCE_BASE + $random) / self::RANDOM_VARIANCE_BASE;
        
        return $offerAverage * $modifier * $modRandom;
    }

    /**
     * Get random factor for variance calculation
     * 
     * @return int Random factor between -5 and 5
     */
    private function getRandomFactor(): int
    {
        if ($this->randomFactor !== null) {
            return $this->randomFactor;
        }
        
        return rand(self::RANDOM_VARIANCE_MIN, self::RANDOM_VARIANCE_MAX);
    }

    /**
     * Calculate total salary committed to a position
     * 
     * @param string $teamName Team name
     * @param Player $player Player to exclude and get position from
     * @return int Total salary committed (capped at MAX_POSITION_SALARY_CAP)
     */
    private function calculatePositionSalary(
        string $teamName,
        Player $player
    ): int {
        $totalSalary = $this->repository->getPositionSalaryCommitment(
            $teamName,
            $player->position,
            $player->playerID
        );
        
        // Cap at maximum
        return min($totalSalary, self::MAX_POSITION_SALARY_CAP);
    }

    /**
     * Calculate overall modifier based on team and player factors
     * 
     * @param int $teamWins Team wins this season
     * @param int $teamLosses Team losses this season
     * @param int $tradWins Team tradition wins
     * @param int $tradLosses Team tradition losses
     * @param int $playerWinner Player's desire to play for winner (1-5)
     * @param int $playerTradition Player's value of team tradition (1-5)
     * @param int $playerLoyalty Player's loyalty factor (1-5)
     * @param int $playerSecurity Player's desire for security (1-5)
     * @param int $playerPlayingTime Player's desire for playing time (1-5)
     * @param string $teamName Offering team
     * @param string $playerTeamName Player's current team
     * @param int $yearsInOffer Years in contract offer
     * @param int $positionSalary Salary committed to position
     * @return float Combined modifier (typically 0.8-1.2)
     */
    private function calculateModifier(
        int $teamWins,
        int $teamLosses,
        int $tradWins,
        int $tradLosses,
        int $playerWinner,
        int $playerTradition,
        int $playerLoyalty,
        int $playerSecurity,
        int $playerPlayingTime,
        string $teamName,
        string $playerTeamName,
        int $yearsInOffer,
        int $positionSalary
    ): float {
        // Play for winner factor
        $seasonDifferential = $teamWins - $teamLosses;
        $factorPlayForWinner = self::PLAY_FOR_WINNER_FACTOR * $seasonDifferential * ($playerWinner - 1);
        
        // Tradition factor
        $traditionDifferential = $tradWins - $tradLosses;
        $factorTradition = self::TRADITION_FACTOR * $traditionDifferential * ($playerTradition - 1);
        
        // Loyalty factor (bonus for staying, penalty for leaving)
        if ($teamName == $playerTeamName) {
            $factorLoyalty = self::LOYALTY_BONUS_PERCENTAGE * ($playerLoyalty - 1);
        } else {
            $factorLoyalty = -self::LOYALTY_BONUS_PERCENTAGE * ($playerLoyalty - 1);
        }
        
        // Security factor (longer contracts more attractive)
        $factorSecurity = (self::SECURITY_YEAR_FACTOR * ($yearsInOffer - 1) + self::SECURITY_BASE_FACTOR) 
                          * ($playerSecurity - 1);
        
        // Playing time factor (less money at position means more opportunity)
        $factorPlayingTime = -(self::PLAYING_TIME_MONEY_FACTOR * $positionSalary / self::PLAYING_TIME_DIVISOR 
                              - self::PLAYING_TIME_BASE_FACTOR) 
                             * ($playerPlayingTime - 1);
        
        return 1 + $factorPlayForWinner + $factorTradition + $factorLoyalty + $factorSecurity + $factorPlayingTime;
    }

    /**
     * @see FreeAgencyDemandCalculatorInterface::getPlayerDemands()
     */
    public function getPlayerDemands(string $playerName): array
    {
        return $this->repository->getPlayerDemands($playerName);
    }
}
