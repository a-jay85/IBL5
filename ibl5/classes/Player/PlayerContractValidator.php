<?php

/**
 * PlayerContractValidator - Validates contract eligibility rules
 * 
 * This class encapsulates all contract validation logic, making it easy to test
 * and maintain. It follows the Single Responsibility Principle.
 */
class PlayerContractValidator
{
    /**
     * Check if a player can renegotiate their contract
     */
    public function canRenegotiateContract(PlayerData $playerData): bool
    {
        if (
            (($playerData->contractCurrentYear == 0 OR $playerData->contractCurrentYear == 1) AND $playerData->contractYear2Salary == 0)
            OR $playerData->contractCurrentYear == 1 AND $playerData->contractYear2Salary == 0
            OR $playerData->contractCurrentYear == 2 AND $playerData->contractYear3Salary == 0
            OR $playerData->contractCurrentYear == 3 AND $playerData->contractYear4Salary == 0
            OR $playerData->contractCurrentYear == 4 AND $playerData->contractYear5Salary == 0
            OR $playerData->contractCurrentYear == 5 AND $playerData->contractYear6Salary == 0
            OR $playerData->contractCurrentYear == 6
        ) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Check if a player is eligible for rookie option
     */
    public function canRookieOption(PlayerData $playerData, string $seasonPhase): bool
    {
        if ($seasonPhase == "Free Agency") {
            if (
                (
                    $playerData->draftRound == 1
                    && $playerData->yearsOfExperience == 2
                    && $playerData->contractYear4Salary == 0
                )
                OR (
                    $playerData->draftRound == 2
                    && $playerData->yearsOfExperience == 1
                    && $playerData->contractYear3Salary == 0
                )
            ) {
                return TRUE;
            }
        } elseif ($seasonPhase == "Preseason" or $seasonPhase == "HEAT") {
            if (
                (
                    $playerData->draftRound == 1
                    && $playerData->yearsOfExperience == 3
                    && $playerData->contractYear4Salary == 0
                )
                || (
                    $playerData->draftRound == 2
                    && $playerData->yearsOfExperience == 2
                    && $playerData->contractYear3Salary == 0
                )
            ) {
                return TRUE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Check if a player's rookie option was previously exercised
     */
    public function wasRookieOptioned(PlayerData $playerData): bool
    {
        if ((
            $playerData->yearsOfExperience == 4 
            AND $playerData->draftRound == 1
            AND $playerData->contractYear4Salary != 0
            AND 2 * $playerData->contractYear3Salary == $playerData->contractYear4Salary
        ) OR (
            $playerData->yearsOfExperience == 3
            AND $playerData->draftRound == 2
            AND $playerData->contractYear3Salary != 0
            AND 2 * $playerData->contractYear2Salary == $playerData->contractYear3Salary
        )) {
            return TRUE;
        }
        return FALSE;
    }
}
