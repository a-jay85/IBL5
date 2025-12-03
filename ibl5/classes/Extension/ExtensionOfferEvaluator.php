<?php

namespace Extension;

use Extension\Contracts\ExtensionOfferEvaluatorInterface;

/**
 * @see ExtensionOfferEvaluatorInterface
 */
class ExtensionOfferEvaluator implements ExtensionOfferEvaluatorInterface
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateOfferValue()
     */
    public function calculateOfferValue($offer)
    {
        $total = $offer['year1'] + $offer['year2'] + $offer['year3'] + $offer['year4'] + $offer['year5'];
        $years = 5;
        if ($offer['year5'] == 0) {
            $years = 4;
        }
        if ($offer['year4'] == 0) {
            $years = 3;
        }
        
        return [
            'total' => $total,
            'years' => $years,
            'averagePerYear' => $years > 0 ? $total / $years : 0
        ];
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateWinnerModifier()
     */
    public function calculateWinnerModifier($teamFactors, $playerPreferences)
    {
        $winDiff = $teamFactors['wins'] - $teamFactors['losses'];
        return 0.000153 * $winDiff * ($playerPreferences['winner'] - 1);
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateTraditionModifier()
     */
    public function calculateTraditionModifier($teamFactors, $playerPreferences)
    {
        $tradDiff = $teamFactors['tradition_wins'] - $teamFactors['tradition_losses'];
        return 0.000153 * $tradDiff * ($playerPreferences['tradition'] - 1);
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateLoyaltyModifier()
     */
    public function calculateLoyaltyModifier($playerPreferences)
    {
        return 0.025 * ($playerPreferences['loyalty'] - 1);
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculatePlayingTimeModifier()
     */
    public function calculatePlayingTimeModifier($teamFactors, $playerPreferences)
    {
        $moneyCommitted = isset($teamFactors['money_committed_at_position']) 
            ? $teamFactors['money_committed_at_position'] 
            : 0;
        return -0.0025 * ($moneyCommitted / 100) * ($playerPreferences['playing_time'] - 1);
    }

    /**
     * @see ExtensionOfferEvaluatorInterface::calculateCombinedModifier()
     */
    public function calculateCombinedModifier($teamFactors, $playerPreferences)
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
    public function evaluateOffer($offer, $demands, $teamFactors, $playerPreferences)
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
    public function calculatePlayerDemands($playerValue)
    {
        return [
            'total' => $playerValue * 5,
            'years' => 5
        ];
    }
}
