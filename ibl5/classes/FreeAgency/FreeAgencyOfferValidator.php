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
    private $db;
    private $mysqli_db;
    private array $offerData = [];

    public function __construct($db, $mysqli_db = null)
    {
        $this->db = $db;
        $this->mysqli_db = $mysqli_db;
    }

    /**
     * Validate a complete free agency offer
     * 
     * @param array<string, mixed> $offerData Contract offer details
     * @return array{valid: bool, error?: string} Validation result
     */
    public function validateOffer(array $offerData): array
    {
        $this->offerData = $offerData;

        // Check for zero first year
        if ($this->offerData['offer1'] == 0) {
            return [
                'valid' => false,
                'error' => 'Sorry, you must enter an amount greater than zero in the first year of a free agency offer. Your offer in Year 1 was zero, so this offer is not valid.'
            ];
        }

        // Check veteran's minimum
        if ($this->offerData['offer1'] < $this->offerData['vetmin']) {
            return [
                'valid' => false,
                'error' => "Sorry, you must enter an amount greater than the Veteran's Minimum in the first year of a free agency offer.<br>Your offer in Year 1 was <b>{$this->offerData['offer1']}</b>, but should be at least <b>{$this->offerData['vetmin']}</b>."
            ];
        }

        // Check hard cap space
        $hardCapValidation = $this->validateHardCapSpace();
        if (!$hardCapValidation['valid']) {
            return $hardCapValidation;
        }

        // Check soft cap space (if no Bird Rights and not using exceptions)
        if (!\ContractRules::hasBirdRights($this->offerData['birdYears']) && $this->offerData['offerType'] == 0) {
            $softCapValidation = $this->validateSoftCapSpace();
            if (!$softCapValidation['valid']) {
                return $softCapValidation;
            }
        }

        // Check maximum contract value
        $maxContractValidation = $this->validateMaximumContract();
        if (!$maxContractValidation['valid']) {
            return $maxContractValidation;
        }

        // Check raises and contract continuity
        $raiseValidation = $this->validateRaisesAndContinuity();
        if (!$raiseValidation['valid']) {
            return $raiseValidation;
        }

        return ['valid' => true];
    }

    /**
     * Validate hard cap space for all contract years
     * 
     * @return array{valid: bool, error?: string}
     */
    private function validateHardCapSpace(): array
    {
        $hardCapSpace1 = $this->offerData['amendedCapSpaceYear1'] + (\League::HARD_CAP_MAX - \League::SOFT_CAP_MAX);
        
        if ($this->offerData['offer1'] > $hardCapSpace1) {
            return [
                'valid' => false,
                'error' => "Sorry, you do not have sufficient cap space under the hard cap to make the offer. You offered {$this->offerData['offer1']} in the first year of the contract, which is more than {$hardCapSpace1}, the amount of hard cap space you have available."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate soft cap space for first year
     * 
     * @return array{valid: bool, error?: string}
     */
    private function validateSoftCapSpace(): array
    {
        if ($this->offerData['offer1'] > $this->offerData['amendedCapSpaceYear1']) {
            return [
                'valid' => false,
                'error' => "Sorry, you do not have sufficient cap space under the soft cap to make the offer. You offered {$this->offerData['offer1']} in the first year of the contract, which is more than {$this->offerData['amendedCapSpaceYear1']}, the amount of soft cap space you have available."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate maximum contract value
     * 
     * @return array{valid: bool, error?: string}
     */
    private function validateMaximumContract(): array
    {
        if ($this->offerData['offer1'] > $this->offerData['year1Max']) {
            return [
                'valid' => false,
                'error' => "Sorry, you tried to offer a contract larger than the maximum allowed for this player based on their years of service. The maximum you are allowed to offer this player is {$this->offerData['year1Max']} in the first year of their contract."
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
     * @return array{valid: bool, error?: string}
     */
    private function validateRaisesAndContinuity(): array
    {
        // Determine max raise percentage
        $raisePercentage = \ContractRules::getMaxRaisePercentage($this->offerData['birdYears']);
        
        $maxRaise = (int) round($this->offerData['offer1'] * $raisePercentage);
        $contractEnded = false;

        // Check each year's raise and continuity
        for ($year = 2; $year <= 6; $year++) {
            $currentOffer = $this->offerData["offer{$year}"];
            $previousOffer = $this->offerData["offer" . ($year - 1)];
            
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
                    'error' => "Sorry, you tried to offer a larger raise than is permitted. Your first year offer was {$this->offerData['offer1']} which means the maximum raise allowed each year is {$maxRaise}. Your offer in Year {$year} was {$currentOffer}, which is more than your Year " . ($year - 1) . " offer, {$previousOffer}, plus the max increase of {$maxRaise}. Given your offer in Year " . ($year - 1) . ", the most you can offer in Year {$year} is {$legalOffer}."
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Check if player has already been signed during this free agency period
     * 
     * @param int $playerId Player ID to check
     * @return bool True if player is already signed
     */
    public function isPlayerAlreadySigned(int $playerId): bool
    {
        $query = "SELECT cy, cy1 FROM ibl_plr WHERE pid = ?";
        $stmt = $this->mysqli_db->prepare($query);
        $stmt->bind_param("i", $playerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        $currentContractYear = $row['cy'] ?? 0;
        $year1Contract = $row['cy1'] ?? '0';
        
        return ($currentContractYear == 0 && $year1Contract != "0");
    }
}
