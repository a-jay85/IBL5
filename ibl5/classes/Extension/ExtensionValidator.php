<?php

namespace Extension;

use Extension\Contracts\ExtensionValidatorInterface;

/**
 * @see ExtensionValidatorInterface
 */
class ExtensionValidator implements ExtensionValidatorInterface
{
    private $db;

    const MAX_YEAR_ONE_0_TO_6_YEARS = 1063;
    const MAX_YEAR_ONE_7_TO_9_YEARS = 1275;
    const MAX_YEAR_ONE_10_PLUS_YEARS = 1451;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @see ExtensionValidatorInterface::validateOfferAmounts()
     */
    public function validateOfferAmounts($offer)
    {
        foreach (['year1', 'year2', 'year3'] as $year) {
            if (empty($offer[$year])) {
            return [
                'valid' => false,
                'error' => "Sorry, you must enter an amount greater than zero for each of the first three contract years when making an extension offer. Your offer in " . ucfirst($year) . " was zero, so this offer is not valid."
            ];
            }
        }
        return ['valid' => true, 'error' => null];
    }

    /**
     * @see ExtensionValidatorInterface::validateMaximumYearOneOffer()
     */
    public function validateMaximumYearOneOffer($offer, $yearsExperience)
    {
        $maxYearOneOffer = $this->getMaximumYearOneOffer($yearsExperience);
        if ($offer['year1'] > $maxYearOneOffer) {
            return ['valid' => false, 'error' => 'Sorry, the first year of your offer is over the maximum allowed for a player with their years of service.'];
        }
        return ['valid' => true, 'error' => null];
    }

    /**
     * @see ExtensionValidatorInterface::validateRaises()
     */
    public function validateRaises($offer, $birdYears)
    {
        $maxRaisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);
        $maxIncrease = floor($offer['year1'] * $maxRaisePercentage);
        
        if ($offer['year2'] > $offer['year1'] + $maxIncrease) {
            $legalOffer = $offer['year1'] + $maxIncrease;
            return [
                'valid' => false, 
                'error' => "Sorry, you tried to offer a larger raise than is permitted. Your first year offer was {$offer['year1']} which means the maximum raise allowed each year is $maxIncrease. Your offer in Year 2 was {$offer['year2']}, which is more than your Year 1 offer, {$offer['year1']}, plus the max increase of $maxIncrease. Given your offer in Year 1, the most you can offer in Year 2 is $legalOffer."
            ];
        }
        if ($offer['year3'] > $offer['year2'] + $maxIncrease) {
            $legalOffer = $offer['year2'] + $maxIncrease;
            return [
                'valid' => false, 
                'error' => "Sorry, you tried to offer a larger raise than is permitted. Your first year offer was {$offer['year1']} which means the maximum raise allowed each year is $maxIncrease. Your offer in Year 3 was {$offer['year3']}, which is more than your Year 2 offer, {$offer['year2']}, plus the max increase of $maxIncrease. Given your offer in Year 2, the most you can offer in Year 3 is $legalOffer."
            ];
        }
        if ($offer['year4'] > 0 && $offer['year4'] > $offer['year3'] + $maxIncrease) {
            $legalOffer = $offer['year3'] + $maxIncrease;
            return [
                'valid' => false, 
                'error' => "Sorry, you tried to offer a larger raise than is permitted. Your first year offer was {$offer['year1']} which means the maximum raise allowed each year is $maxIncrease. Your offer in Year 4 was {$offer['year4']}, which is more than your Year 3 offer, {$offer['year3']}, plus the max increase of $maxIncrease. Given your offer in Year 3, the most you can offer in Year 4 is $legalOffer."
            ];
        }
        if ($offer['year5'] > 0 && $offer['year5'] > $offer['year4'] + $maxIncrease) {
            $legalOffer = $offer['year4'] + $maxIncrease;
            return [
                'valid' => false, 
                'error' => "Sorry, you tried to offer a larger raise than is permitted. Your first year offer was {$offer['year1']} which means the maximum raise allowed each year is $maxIncrease. Your offer in Year 5 was {$offer['year5']}, which is more than your Year 4 offer, {$offer['year4']}, plus the max increase of $maxIncrease. Given your offer in Year 4, the most you can offer in Year 5 is $legalOffer."
            ];
        }
        return ['valid' => true, 'error' => null];
    }

    /**
     * @see ExtensionValidatorInterface::validateSalaryDecreases()
     */
    public function validateSalaryDecreases($offer)
    {
        $years = ['year1', 'year2', 'year3', 'year4', 'year5'];
        for ($i = 1; $i < count($years); $i++) {
            if ($offer[$years[$i]] < $offer[$years[$i - 1]] && $offer[$years[$i]] != 0) {
            return [
                'valid' => false,
                'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered {$offer[$years[$i]]} in the " . ($i + 1) . " year, which is less than you offered in the previous year, {$offer[$years[$i - 1]]}."
            ];
            }
        }
        return ['valid' => true, 'error' => null];
    }

    private function getMaximumYearOneOffer($yearsExperience)
    {
        if ($yearsExperience > 9) {
            return self::MAX_YEAR_ONE_10_PLUS_YEARS;
        }
        if ($yearsExperience > 6) {
            return self::MAX_YEAR_ONE_7_TO_9_YEARS;
        }
        return self::MAX_YEAR_ONE_0_TO_6_YEARS;
    }

    /**
     * @see ExtensionValidatorInterface::validateExtensionEligibility()
     */
    public function validateExtensionEligibility($team)
    {
        if ($team->hasUsedExtensionThisSeason == 1) {
            return [
                'valid' => false,
                'error' => 'Sorry, you have already used your extension for this season.'
            ];
        }
        
        if ($team->hasUsedExtensionThisSim == 1) {
            return [
                'valid' => false,
                'error' => 'Sorry, you have already used your extension for this sim.'
            ];
        }
        
        return ['valid' => true, 'error' => null];
    }
}
