<?php

declare(strict_types=1);

namespace Negotiation;

use Negotiation\Contracts\NegotiationDemandCalculatorInterface;
use Negotiation\Contracts\NegotiationRepositoryInterface;
use Player\Player;

/**
 * @see NegotiationDemandCalculatorInterface
 *
 * @phpstan-import-type TeamFactors from NegotiationDemandCalculatorInterface
 * @phpstan-import-type DemandResult from NegotiationDemandCalculatorInterface
 * @phpstan-type RatingMap array{fga: int, fgp: int, fta: int, ftp: int, tga: int, tgp: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, foul: int, oo: int, od: int, do: int, dd: int, po: int, pd: int, to: int, td: int}
 * @phpstan-type MarketMaximums array{fga: int, fgp: int, fta: int, ftp: int, tga: int, tgp: int, orb: int, drb: int, ast: int, stl: int, to: int, blk: int, foul: int, oo: int, od: int, do: int, dd: int, po: int, pd: int, td: int}
 * @phpstan-type BaseDemands array{dem1: float, dem2: float, dem3: float, dem4: float, dem5: float, dem6: int}
 */
class NegotiationDemandCalculator implements NegotiationDemandCalculatorInterface
{
    private NegotiationRepositoryInterface $repository;

    // Constants for demand calculation
    private const RAW_SCORE_BASELINE = 700; // Sam Mack's baseline score
    private const DEMANDS_FACTOR = 3; // Trial-and-error multiplier

    public function __construct(\mysqli $db)
    {
        $this->repository = new NegotiationRepository($db);
    }
    
    /**
     * @see NegotiationDemandCalculatorInterface::calculateDemands()
     *
     * @param Player $player The player object with ratings and stats
     * @param TeamFactors $teamFactors Team factors affecting demands
     * @return DemandResult Demand information
     */
    public function calculateDemands(Player $player, array $teamFactors): array
    {
        // Calculate base demands from player ratings
        $baseDemands = $this->calculateBaseDemands($player);
        
        // Calculate modifier based on team factors and player preferences
        $modifier = $this->calculateModifier($player, $teamFactors);
        
        // Apply modifier to base demands
        $adjustedDemands = $this->applyModifier($baseDemands, $modifier);
        
        // Determine number of years demanded
        $years = $this->calculateYearsDemanded($adjustedDemands);
        
        return [
            'year1' => $adjustedDemands['dem1'],
            'year2' => $adjustedDemands['dem2'],
            'year3' => $adjustedDemands['dem3'],
            'year4' => $adjustedDemands['dem4'],
            'year5' => $adjustedDemands['dem5'],
            'year6' => 0, // Extensions are max 5 years
            'years' => $years,
            'total' => $adjustedDemands['dem1'] + $adjustedDemands['dem2'] + 
                      $adjustedDemands['dem3'] + $adjustedDemands['dem4'] + $adjustedDemands['dem5'],
            'modifier' => $modifier
        ];
    }
    
    /**
     * Calculate base contract demands from player ratings
     *
     * @param Player $player The player object
     * @return BaseDemands Base demand amounts for each year
     */
    private function calculateBaseDemands(Player $player): array
    {
        // Get player ratings
        $playerRatings = $this->getPlayerRatings($player);
        
        // Get market maximums for normalization
        $marketMaximums = $this->repository->getMarketMaximums();
        
        // Calculate raw scores (each rating as percentage of market max)
        $rawScore = $this->calculateRawScore($playerRatings, $marketMaximums);
        
        // Adjust score (baseline removal)
        $adjustedScore = $rawScore - self::RAW_SCORE_BASELINE;
        
        // Calculate average demands per year
        $avgDemands = $adjustedScore * self::DEMANDS_FACTOR;
        
        // Calculate total over 5 years
        $totalDemands = $avgDemands * 5;
        
        // Calculate base (first year) demands
        $baseDemands = $totalDemands / 6;
        
        // Calculate max raise per year (standard rate without bird rights)
        $maxRaise = floor($baseDemands * \ContractRules::STANDARD_RAISE_PERCENTAGE);
        
        // Build yearly demands with raises
        return [
            'dem1' => $baseDemands,
            'dem2' => $baseDemands + $maxRaise,
            'dem3' => $baseDemands + $maxRaise * 2,
            'dem4' => $baseDemands + $maxRaise * 3,
            'dem5' => $baseDemands + $maxRaise * 4,
            'dem6' => 0
        ];
    }
    
    /**
     * Get player ratings for demand calculation
     *
     * @param Player $player The player object
     * @return RatingMap Player ratings
     */
    private function getPlayerRatings(Player $player): array
    {
        return [
            'fga' => $player->ratingFieldGoalAttempts ?? 0,
            'fgp' => $player->ratingFieldGoalPercentage ?? 0,
            'fta' => $player->ratingFreeThrowAttempts ?? 0,
            'ftp' => $player->ratingFreeThrowPercentage ?? 0,
            'tga' => $player->ratingThreePointAttempts ?? 0,
            'tgp' => $player->ratingThreePointPercentage ?? 0,
            'orb' => $player->ratingOffensiveRebounds ?? 0,
            'drb' => $player->ratingDefensiveRebounds ?? 0,
            'ast' => $player->ratingAssists ?? 0,
            'stl' => $player->ratingSteals ?? 0,
            'tov' => $player->ratingTurnovers ?? 0,
            'blk' => $player->ratingBlocks ?? 0,
            'foul' => $player->ratingFouls ?? 0,
            'oo' => $player->ratingOutsideOffense ?? 0,
            'od' => $player->ratingOutsideDefense ?? 0,
            'do' => $player->ratingDriveOffense ?? 0,
            'dd' => $player->ratingDriveDefense ?? 0,
            'po' => $player->ratingPostOffense ?? 0,
            'pd' => $player->ratingPostDefense ?? 0,
            'to' => $player->ratingTransitionOffense ?? 0,
            'td' => $player->ratingTransitionDefense ?? 0
        ];
    }
    
    /**
     * Calculate raw score from player ratings and market maximums
     *
     * @param RatingMap $playerRatings Player's rating values
     * @param MarketMaximums $marketMaximums Market maximum values
     * @return int Total raw score
     */
    private function calculateRawScore(array $playerRatings, array $marketMaximums): int
    {
        $totalRawScore = 0;
        
        foreach ($playerRatings as $key => $value) {
            if (isset($marketMaximums[$key]) && $marketMaximums[$key] > 0) {
                $rawScore = intval(round($value / $marketMaximums[$key] * 100));
                $totalRawScore += $rawScore;
            }
        }
        
        return $totalRawScore;
    }
    
    /**
     * Calculate modifier based on team factors and player preferences
     *
     * @param Player $player The player object
     * @param TeamFactors $teamFactors Team factors
     * @return float Modifier value
     */
    private function calculateModifier(Player $player, array $teamFactors): float
    {
        $playerPrefs = [
            'winner' => $player->freeAgencyPlayForWinner ?? 1,
            'tradition' => $player->freeAgencyTradition ?? 1,
            'loyalty' => $player->freeAgencyLoyalty ?? 1,
            'playingTime' => $player->freeAgencyPlayingTime ?? 1
        ];
        
        // Play for winner factor
        $wins = $teamFactors['wins'] ?? 41;
        $losses = $teamFactors['losses'] ?? 41;
        $totalGames = $wins + $losses;
        $PFWFactor = 0;
        if ($totalGames > 0) {
            $PFWFactor = (0.025 * ($wins - $losses) / $totalGames * ($playerPrefs['winner'] - 1));
        }
        
        // Tradition factor
        $tradWins = $teamFactors['tradition_wins'] ?? 41;
        $tradLosses = $teamFactors['tradition_losses'] ?? 41;
        $totalTradGames = $tradWins + $tradLosses;
        $traditionFactor = 0;
        if ($totalTradGames > 0) {
            $traditionFactor = (0.025 * ($tradWins - $tradLosses) / $totalTradGames * ($playerPrefs['tradition'] - 1));
        }
        
        // Loyalty factor
        $loyaltyFactor = (0.025 * ($playerPrefs['loyalty'] - 1));
        
        // Playing time factor (based on money committed at position)
        $moneyCommitted = $teamFactors['money_committed_at_position'] ?? 0;
        $PTFactor = (($moneyCommitted * -0.00005) + 0.025) * ($playerPrefs['playingTime'] - 1);
        
        return 1 + $PFWFactor + $traditionFactor + $loyaltyFactor + $PTFactor;
    }
    
    /**
     * Apply modifier to base demands
     *
     * @param BaseDemands $baseDemands Base demand amounts
     * @param float $modifier Modifier to apply
     * @return BaseDemands Adjusted demands
     */
    private function applyModifier(array $baseDemands, float $modifier): array
    {
        if ($modifier === 0.0) {
            $modifier = 0.000001; // Prevent division by zero
        }
        
        return [
            'dem1' => round($baseDemands['dem1'] / $modifier),
            'dem2' => round($baseDemands['dem2'] / $modifier),
            'dem3' => round($baseDemands['dem3'] / $modifier),
            'dem4' => round($baseDemands['dem4'] / $modifier),
            'dem5' => round($baseDemands['dem5'] / $modifier),
            'dem6' => 0 // Extensions are max 5 years
        ];
    }
    
    /**
     * Calculate number of years demanded based on demand amounts
     *
     * @param BaseDemands $demands Demand amounts
     * @return int Number of years
     */
    private function calculateYearsDemanded(array $demands): int
    {
        if ($demands['dem6'] !== 0) return 6;
        if ($demands['dem5'] !== 0) return 5;
        if ($demands['dem4'] !== 0) return 4;
        if ($demands['dem3'] !== 0) return 3;
        if ($demands['dem2'] !== 0) return 2;
        return 1;
    }
}
