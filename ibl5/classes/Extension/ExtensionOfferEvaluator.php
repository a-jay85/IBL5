<?php

declare(strict_types=1);

namespace Extension;

use Extension\Contracts\ExtensionOfferEvaluatorInterface;
use Services\CommonContractValidator;

/**
 * ExtensionOfferEvaluator - Evaluates contract extension offers
 * 
 * Calculates whether a player will accept or reject an extension based on
 * offer value, team factors, and player preferences.
 * 
 * @see ExtensionOfferEvaluatorInterface
 */
class ExtensionOfferEvaluator implements ExtensionOfferEvaluatorInterface
{
    private CommonContractValidator $contractValidator;

    public function __construct()
    {
        $this->contractValidator = new CommonContractValidator();
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateOfferValue()
     */
    public function calculateOfferValue(array $offer): array
    {
        return $this->contractValidator->calculateOfferValue($offer);
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateWinnerModifier()
     */
    public function calculateWinnerModifier(array $teamFactors, array $playerPreferences): float
    {
        $winDiff = ($teamFactors['wins'] ?? 0) - ($teamFactors['losses'] ?? 0);
        return 0.000153 * $winDiff * (($playerPreferences['winner'] ?? 1) - 1);
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateTraditionModifier()
     */
    public function calculateTraditionModifier(array $teamFactors, array $playerPreferences): float
    {
        $tradDiff = ($teamFactors['tradition_wins'] ?? 0) - ($teamFactors['tradition_losses'] ?? 0);
        return 0.000153 * $tradDiff * (($playerPreferences['tradition'] ?? 1) - 1);
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateLoyaltyModifier()
     */
    public function calculateLoyaltyModifier(array $playerPreferences): float
    {
        return 0.025 * (($playerPreferences['loyalty'] ?? 1) - 1);
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculatePlayingTimeModifier()
     */
    public function calculatePlayingTimeModifier(array $teamFactors, array $playerPreferences): float
    {
        $moneyCommitted = $teamFactors['money_committed_at_position'] ?? 0;
        return -0.0025 * ($moneyCommitted / 100) * (($playerPreferences['playing_time'] ?? 1) - 1);
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateCombinedModifier()
     */
    public function calculateCombinedModifier(array $teamFactors, array $playerPreferences): float
    {
        $modifier = 1.0;
        $modifier += $this->calculateWinnerModifier($teamFactors, $playerPreferences);
        $modifier += $this->calculateTraditionModifier($teamFactors, $playerPreferences);
        $modifier += $this->calculateLoyaltyModifier($playerPreferences);
        $modifier += $this->calculatePlayingTimeModifier($teamFactors, $playerPreferences);
        return $modifier;
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::evaluateOffer()
     */
    public function evaluateOffer(array $offer, array $demands, array $teamFactors, array $playerPreferences): array
    {
        $offerData = $this->calculateOfferValue($offer);
        $demandsData = $this->calculateOfferValue($demands);
        
        $modifier = $this->calculateCombinedModifier($teamFactors, $playerPreferences);
        
        $adjustedOfferValue = $offerData['averagePerYear'] * $modifier;
        $demandValue = $demandsData['averagePerYear'];
        
        return [
            'accepted' => $adjustedOfferValue >= $demandValue,
            'offerValue' => $adjustedOfferValue,
            'demandValue' => $demandValue,
            'modifier' => $modifier,
            'offerYears' => $offerData['years']
        ];
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculatePlayerDemands()
     */
    public function calculatePlayerDemands(int $playerValue): array
    {
        return [
            'total' => $playerValue * 5,
            'years' => 5
        ];
    }
}
