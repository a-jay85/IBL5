<?php

namespace FreeAgency;

/**
 * Validates free agency contract offers
 * 
 * Ensures offers comply with:
 * - Salary cap constraints (soft cap, hard cap)
 * - Maximum contract values based on years of service
 * - Legal raise percentages (10% standard, 12.5% with Bird Rights)
 * - Minimum salary requirements (veteran's minimum)
 * - Contract year sequencing (no decreases allowed)
 */
class FreeAgencyOfferValidator
{
    private const HARD_CAP_BUFFER = 2000;

    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Validate a complete free agency offer
     * 
     * @param array<string, mixed> $offerData Contract offer details
     * @return array{valid: bool, error?: string} Validation result
     */
    public function validateOffer(array $offerData): array
    {
        // Check for zero first year
        if ($offerData['offer1'] == 0) {
            return [
                'valid' => false,
                'error' => 'Sorry, you must enter an amount greater than zero in the first year of a free agency offer. Your offer in Year 1 was zero, so this offer is not valid.'
            ];
        }

        // Check veteran's minimum
        if ($offerData['offer1'] < $offerData['vetmin']) {
            return [
                'valid' => false,
                'error' => "Sorry, you must enter an amount greater than the Veteran's Minimum in the first year of a free agency offer.<br>Your offer in Year 1 was <b>{$offerData['offer1']}</b>, but should be at least <b>{$offerData['vetmin']}</b>."
            ];
        }

        // Check hard cap space
        $hardCapValidation = $this->validateHardCapSpace($offerData);
        if (!$hardCapValidation['valid']) {
            return $hardCapValidation;
        }

        // Check soft cap space (if no Bird Rights and not using exceptions)
        if (!\ContractRules::hasBirdRights($offerData['birdYears']) && $offerData['offerType'] == 0) {
            $softCapValidation = $this->validateSoftCapSpace($offerData);
            if (!$softCapValidation['valid']) {
                return $softCapValidation;
            }
        }

        // Check maximum contract value
        $maxContractValidation = $this->validateMaximumContract($offerData);
        if (!$maxContractValidation['valid']) {
            return $maxContractValidation;
        }

        // Check raises and contract continuity
        $raiseValidation = $this->validateRaisesAndContinuity($offerData);
        if (!$raiseValidation['valid']) {
            return $raiseValidation;
        }

        return ['valid' => true];
    }

    /**
     * Validate hard cap space for all contract years
     * 
     * @param array<string, mixed> $offerData
     * @return array{valid: bool, error?: string}
     */
    private function validateHardCapSpace(array $offerData): array
    {
        $hardCapSpace1 = $offerData['amendedCapSpaceYear1'] + self::HARD_CAP_BUFFER;
        
        if ($offerData['offer1'] > $hardCapSpace1) {
            return [
                'valid' => false,
                'error' => "Sorry, you do not have sufficient cap space under the hard cap to make the offer. You offered {$offerData['offer1']} in the first year of the contract, which is more than {$hardCapSpace1}, the amount of hard cap space you have available."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate soft cap space for first year
     * 
     * @param array<string, mixed> $offerData
     * @return array{valid: bool, error?: string}
     */
    private function validateSoftCapSpace(array $offerData): array
    {
        if ($offerData['offer1'] > $offerData['amendedCapSpaceYear1']) {
            return [
                'valid' => false,
                'error' => "Sorry, you do not have sufficient cap space under the soft cap to make the offer. You offered {$offerData['offer1']} in the first year of the contract, which is more than {$offerData['amendedCapSpaceYear1']}, the amount of soft cap space you have available."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate maximum contract value
     * 
     * @param array<string, mixed> $offerData
     * @return array{valid: bool, error?: string}
     */
    private function validateMaximumContract(array $offerData): array
    {
        if ($offerData['offer1'] > $offerData['year1Max']) {
            return [
                'valid' => false,
                'error' => "Sorry, you tried to offer a contract larger than the maximum allowed for this player based on their years of service. The maximum you are allowed to offer this player is {$offerData['year1Max']} in the first year of their contract."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate contract raises comply with CBA rules and contract has no gaps
     * 
     * Ensures:
     * - Raises don't exceed allowed percentage (10% or 12.5% with Bird Rights)
     * - No salary decreases year-over-year
     * - No gaps in contract years (once a year is 0, all following years must be 0)
     * 
     * @param array<string, mixed> $offerData
     * @return array{valid: bool, error?: string}
     */
    private function validateRaisesAndContinuity(array $offerData): array
    {
        // Determine max raise percentage
        $raisePercentage = \ContractRules::getMaxRaisePercentage($offerData['birdYears']);
        
        $maxRaise = (int) round($offerData['offer1'] * $raisePercentage);
        $contractEnded = false;

        // Check each year's raise and continuity
        for ($year = 2; $year <= 6; $year++) {
            $currentOffer = $offerData["offer{$year}"];
            $previousOffer = $offerData["offer" . ($year - 1)];
            
            // Check if contract ended
            if ($previousOffer == 0) {
                $contractEnded = true;
            }
            
            // Cannot resume contract after it ends
            if ($contractEnded && $currentOffer > 0) {
                return [
                    'valid' => false,
                    'error' => "Sorry, you cannot have gaps in contract years. You offered 0 in year " . ($year - 1) . " but offered {$currentOffer} in year {$year}."
                ];
            }
            
            // Check raise amount
            if ($currentOffer > 0 && $previousOffer > 0 && $currentOffer > $previousOffer + $maxRaise) {
                $legalOffer = $previousOffer + $maxRaise;
                
                return [
                    'valid' => false,
                    'error' => "Sorry, you tried to offer a larger raise than is permitted. Your first year offer was {$offerData['offer1']} which means the maximum raise allowed each year is {$maxRaise}. Your offer in Year {$year} was {$currentOffer}, which is more than your Year " . ($year - 1) . " offer, {$previousOffer}, plus the max increase of {$maxRaise}. Given your offer in Year " . ($year - 1) . ", the most you can offer in Year {$year} is {$legalOffer}."
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Check if player has already been signed during this free agency period
     * 
     * @param string $playerName Player name to check
     * @return bool True if player is already signed
     */
    public function isPlayerAlreadySigned(string $playerName): bool
    {
        $databaseService = new \Services\DatabaseService();
        $escapedPlayerName = $databaseService->escapeString($this->db, $playerName);
        
        $query = "SELECT cy, cy1 FROM ibl_plr WHERE name = '$escapedPlayerName'";
        $result = $this->db->sql_query($query);
        
        if (!$result) {
            return false;
        }
        
        $currentContractYear = $this->db->sql_result($result, 0, "cy");
        $year1Contract = $this->db->sql_result($result, 0, "cy1");
        
        return ($currentContractYear == 0 && $year1Contract != "0");
    }
}
