<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyDemandCalculatorInterface;
use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;
use Player\Player;

/**
 * @see FreeAgencyDemandCalculatorInterface
 *
 * @phpstan-import-type CalculationResult from FreeAgencyDemandCalculatorInterface
 */
class FreeAgencyDemandCalculator implements FreeAgencyDemandCalculatorInterface
{
    private const SECURITY_BASE_FACTOR = -0.025;
    private const SECURITY_YEAR_FACTOR = 0.01;
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
     * @see FreeAgencyDemandCalculatorInterface::calculatePerceivedValue()
     *
     * @return CalculationResult
     */
    public function calculatePerceivedValue(
        int $offerAverage,
        string $teamName,
        Player $player,
        int $yearsInOffer
    ): array {
        $teamPerformance = $this->repository->getTeamPerformance($teamName);
        $positionSalary = $this->calculatePositionSalary($teamName, $player);

        $modifier = $this->calculateModifier(
            $teamPerformance['wins'],
            $teamPerformance['losses'],
            $teamPerformance['tradWins'],
            $teamPerformance['tradLosses'],
            $player->freeAgencyPlayForWinner ?? 0,
            $player->freeAgencyTradition ?? 0,
            $player->freeAgencyLoyalty ?? 0,
            $player->freeAgencySecurity ?? 0,
            $player->freeAgencyPlayingTime ?? 0,
            $teamName,
            $player->teamName ?? '',
            $yearsInOffer,
            $positionSalary
        );

        $random = $this->getRandomFactor();
        $modRandom = (self::RANDOM_VARIANCE_BASE + $random) / self::RANDOM_VARIANCE_BASE;

        return [
            'modifier' => $modifier,
            'random' => $random,
            'perceivedValue' => $offerAverage * $modifier * $modRandom,
        ];
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
     * @return int Total salary committed at position
     */
    private function calculatePositionSalary(
        string $teamName,
        Player $player
    ): int {
        return $this->repository->getPositionSalaryCommitment(
            $teamName,
            $player->position ?? '',
            $player->playerID ?? 0
        );
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
        $factorPlayForWinner = \ContractRules::calculateWinnerModifier($teamWins, $teamLosses, $playerWinner);
        $factorTradition = \ContractRules::calculateTraditionModifier($tradWins, $tradLosses, $playerTradition);
        $factorLoyalty = \ContractRules::calculateLoyaltyModifier($playerLoyalty, $teamName === $playerTeamName);
        $factorSecurity = (self::SECURITY_YEAR_FACTOR * ($yearsInOffer - 1) + self::SECURITY_BASE_FACTOR)
                          * ($playerSecurity - 1);
        $factorPlayingTime = \ContractRules::calculatePlayingTimeModifier($positionSalary, $playerPlayingTime);

        return 1 + $factorPlayForWinner + $factorTradition + $factorLoyalty + $factorSecurity + $factorPlayingTime;
    }

}
