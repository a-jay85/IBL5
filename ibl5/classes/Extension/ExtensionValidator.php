<?php

namespace Extension;

/**
 * Extension Validator Class
 * 
 * Handles all validation logic for contract extension offers.
 * Encapsulates business rules for:
 * - Zero amount validation
 * - Extension eligibility
 * - Maximum offer validation
 * - Raise percentage validation
 * - Salary decrease validation
 */
class ExtensionValidator
{
    private $db;

    const MAX_YEAR_ONE_0_TO_6_YEARS = 1063;
    const MAX_YEAR_ONE_7_TO_9_YEARS = 1275;
    const MAX_YEAR_ONE_10_PLUS_YEARS = 1451;
    
    const RAISE_PERCENTAGE_WITHOUT_BIRD = 0.10;
    const RAISE_PERCENTAGE_WITH_BIRD = 0.125;
    const BIRD_RIGHTS_THRESHOLD = 3;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Escapes a string for SQL queries
     * Works with both real MySQL class and mock database
     * 
     * @param string $string String to escape
     * @return string Escaped string
     */
    private function escapeString($string)
    {
        // Check if this is the real MySQL class with db_connect_id
        if (isset($this->db->db_connect_id) && $this->db->db_connect_id) {
            return mysqli_real_escape_string($this->db->db_connect_id, $string);
        }
        // Otherwise use the mock's sql_escape_string or fallback to addslashes
        if (method_exists($this->db, 'sql_escape_string')) {
            return $this->db->sql_escape_string($string);
        }
        return addslashes($string);
    }

    /**
     * Public wrapper for escapeString to be used by ExtensionProcessor
     * 
     * @param string $string String to escape
     * @return string Escaped string
     */
    public function escapeStringPublic($string)
    {
        return $this->escapeString($string);
    }

    /**
     * Validates that the first three years of the offer have non-zero amounts
     * 
     * @param array $offer Array with keys: year1, year2, year3, year4, year5
     * @return array ['valid' => bool, 'error' => string|null]
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
     * Validates that the team hasn't already used their extension
     * 
     * @param string $teamName Team name to check
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateExtensionEligibility($teamName)
    {
        $teamNameEscaped = $this->escapeString($teamName);
        $query = "SELECT Used_Extension_This_Season, Used_Extension_This_Chunk FROM ibl_team_info WHERE team_name = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) == 0) {
            return ['valid' => false, 'error' => 'Team not found in database.'];
        }
        
        $usedThisSeason = $this->db->sql_result($result, 0, 'Used_Extension_This_Season');
        $usedThisChunk = $this->db->sql_result($result, 0, 'Used_Extension_This_Chunk');
        
        if ($usedThisSeason == 1) {
            return ['valid' => false, 'error' => 'Sorry, you have already used your extension for this season.'];
        }
        if ($usedThisChunk == 1) {
            return ['valid' => false, 'error' => 'Sorry, you have already used your extension for this Chunk.'];
        }
        return ['valid' => true, 'error' => null];
    }

    /**
     * Validates that the offer doesn't exceed the maximum allowed for player's experience
     * 
     * @param array $offer Offer array
     * @param int $yearsExperience Player's years of experience
     * @return array ['valid' => bool, 'error' => string|null]
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
     * Validates that raises between years don't exceed allowed percentages
     * 
     * @param array $offer Offer array
     * @param int $birdYears Years with Bird rights
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateRaises($offer, $birdYears)
    {
        $maxRaisePercentage = ($birdYears >= self::BIRD_RIGHTS_THRESHOLD) 
            ? self::RAISE_PERCENTAGE_WITH_BIRD 
            : self::RAISE_PERCENTAGE_WITHOUT_BIRD;
        $maxIncrease = round($offer['year1'] * $maxRaisePercentage, 0);
        
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
     * Validates that salaries don't decrease in later years (except to zero)
     * 
     * @param array $offer Offer array
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateSalaryDecreases($offer)
    {
        if ($offer['year2'] < $offer['year1'] && $offer['year2'] != 0) {
            return [
                'valid' => false, 
                'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered {$offer['year2']} in the second year, which is less than you offered in the first year, {$offer['year1']}."
            ];
        }
        if ($offer['year3'] < $offer['year2'] && $offer['year3'] != 0) {
            return [
                'valid' => false, 
                'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered {$offer['year3']} in the third year, which is less than you offered in the second year, {$offer['year2']}."
            ];
        }
        if ($offer['year4'] < $offer['year3'] && $offer['year4'] != 0) {
            return [
                'valid' => false, 
                'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered {$offer['year4']} in the fourth year, which is less than you offered in the third year, {$offer['year3']}."
            ];
        }
        if ($offer['year5'] < $offer['year4'] && $offer['year5'] != 0) {
            return [
                'valid' => false, 
                'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered {$offer['year5']} in the fifth year, which is less than you offered in the fourth year, {$offer['year4']}."
            ];
        }
        return ['valid' => true, 'error' => null];
    }

    /**
     * Gets the maximum offer allowed based on years of experience
     * 
     * @param int $yearsExperience Player's years of experience
     * @return int Maximum offer amount
     */
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
     * Validates extension eligibility using a Team object
     * 
     * @param \Team $team Team object
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateExtensionEligibilityWithTeam($team)
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
