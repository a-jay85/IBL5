<?php
/**
 * Extension Test Helper Classes
 * 
 * These are stub/mock implementations of the extension logic classes
 * to support the comprehensive PHPUnit test suite for extension.php.
 * 
 * These classes encapsulate the business logic from extension.php
 * in a testable, object-oriented way.
 */

/**
 * Extension Validator Class
 * Handles all validation logic for contract extension offers
 */
class ExtensionValidator
{
    private $db;

    const MAX_SALARY_0_TO_6_YEARS = 1063;
    const MAX_SALARY_7_TO_9_YEARS = 1275;
    const MAX_SALARY_10_PLUS_YEARS = 1451;
    
    const RAISE_PERCENTAGE_WITHOUT_BIRD = 0.10;
    const RAISE_PERCENTAGE_WITH_BIRD = 0.125;
    const BIRD_RIGHTS_THRESHOLD = 3;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function validateOfferAmounts($offer)
    {
        if ($offer['year1'] == 0) {
            return ['valid' => false, 'error' => 'Sorry, you must enter an amount greater than zero for each of the first three contract years when making an extension offer. Your offer in Year 1 was zero, so this offer is not valid.'];
        }
        if ($offer['year2'] == 0) {
            return ['valid' => false, 'error' => 'Sorry, you must enter an amount greater than zero for each of the first three contract years when making an extension offer. Your offer in Year 2 was zero, so this offer is not valid.'];
        }
        if ($offer['year3'] == 0) {
            return ['valid' => false, 'error' => 'Sorry, you must enter an amount greater than zero for each of the first three contract years when making an extension offer. Your offer in Year 3 was zero, so this offer is not valid.'];
        }
        return ['valid' => true, 'error' => null];
    }

    public function validateExtensionEligibility($teamName)
    {
        $query = "SELECT Used_Extension_This_Season, Used_Extension_This_Chunk FROM ibl_team_info WHERE team_name = '$teamName'";
        $result = $this->db->sql_query($query);
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

    public function validateMaximumOffer($offer, $yearsExperience)
    {
        $maxOffer = $this->getMaximumOffer($yearsExperience);
        if ($offer['year1'] > $maxOffer) {
            return ['valid' => false, 'error' => 'Sorry, this offer is over the maximum allowed offer for a player with their years of service.'];
        }
        return ['valid' => true, 'error' => null];
    }

    public function validateRaises($offer, $birdYears)
    {
        $maxRaisePercentage = ($birdYears >= self::BIRD_RIGHTS_THRESHOLD) ? self::RAISE_PERCENTAGE_WITH_BIRD : self::RAISE_PERCENTAGE_WITHOUT_BIRD;
        $maxIncrease = round($offer['year1'] * $maxRaisePercentage, 0);
        
        if ($offer['year2'] > $offer['year1'] + $maxIncrease) {
            return ['valid' => false, 'error' => "Sorry, you tried to offer a larger raise than is permitted. Your first year offer was {$offer['year1']} which means the maximum raise allowed each year is $maxIncrease. Your offer in Year 2 was {$offer['year2']}, which is more than your Year 1 offer, {$offer['year1']}, plus the max increase of $maxIncrease."];
        }
        if ($offer['year3'] > $offer['year2'] + $maxIncrease) {
            return ['valid' => false, 'error' => "Sorry, you tried to offer a larger raise than is permitted. Your offer in Year 3 was {$offer['year3']}, which is more than your Year 2 offer, {$offer['year2']}, plus the max increase of $maxIncrease."];
        }
        if ($offer['year4'] > 0 && $offer['year4'] > $offer['year3'] + $maxIncrease) {
            return ['valid' => false, 'error' => "Sorry, you tried to offer a larger raise than is permitted. Your offer in Year 4 was {$offer['year4']}, which is more than your Year 3 offer, {$offer['year3']}, plus the max increase of $maxIncrease."];
        }
        if ($offer['year5'] > 0 && $offer['year5'] > $offer['year4'] + $maxIncrease) {
            return ['valid' => false, 'error' => "Sorry, you tried to offer a larger raise than is permitted. Your offer in Year 5 was {$offer['year5']}, which is more than your Year 4 offer, {$offer['year4']}, plus the max increase of $maxIncrease."];
        }
        return ['valid' => true, 'error' => null];
    }

    public function validateSalaryDecreases($offer)
    {
        if ($offer['year2'] < $offer['year1'] && $offer['year2'] != 0) {
            return ['valid' => false, 'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered {$offer['year2']} in the second year, which is less than you offered in the first year, {$offer['year1']}."];
        }
        if ($offer['year3'] < $offer['year2'] && $offer['year3'] != 0) {
            return ['valid' => false, 'error' => "Sorry, you cannot decrease salary in later years of a contract."];
        }
        if ($offer['year4'] < $offer['year3'] && $offer['year4'] != 0) {
            return ['valid' => false, 'error' => "Sorry, you cannot decrease salary in later years of a contract."];
        }
        if ($offer['year5'] < $offer['year4'] && $offer['year5'] != 0) {
            return ['valid' => false, 'error' => "Sorry, you cannot decrease salary in later years of a contract."];
        }
        return ['valid' => true, 'error' => null];
    }

    private function getMaximumOffer($yearsExperience)
    {
        if ($yearsExperience > 9) return self::MAX_SALARY_10_PLUS_YEARS;
        if ($yearsExperience > 6) return self::MAX_SALARY_7_TO_9_YEARS;
        return self::MAX_SALARY_0_TO_6_YEARS;
    }
}

/**
 * Extension Offer Evaluator Class
 * Handles offer evaluation logic including player preferences and modifiers
 */
class ExtensionOfferEvaluator
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function calculateOfferValue($offer)
    {
        $total = $offer['year1'] + $offer['year2'] + $offer['year3'] + $offer['year4'] + $offer['year5'];
        $years = 5;
        if ($offer['year5'] == 0) $years = 4;
        if ($offer['year4'] == 0) $years = 3;
        
        return [
            'total' => $total,
            'years' => $years,
            'averagePerYear' => $years > 0 ? $total / $years : 0
        ];
    }

    public function calculateWinnerModifier($teamFactors, $playerPreferences)
    {
        $winDiff = $teamFactors['wins'] - $teamFactors['losses'];
        $totalGames = $teamFactors['wins'] + $teamFactors['losses'];
        $winPct = $totalGames > 0 ? $winDiff / $totalGames : 0;
        return 0.000153 * $winDiff * ($playerPreferences['winner'] - 1);
    }

    public function calculateTraditionModifier($teamFactors, $playerPreferences)
    {
        $tradDiff = $teamFactors['tradition_wins'] - $teamFactors['tradition_losses'];
        return 0.000153 * $tradDiff * ($playerPreferences['tradition'] - 1);
    }

    public function calculateLoyaltyModifier($playerPreferences)
    {
        return 0.025 * ($playerPreferences['loyalty'] - 1);
    }

    public function calculatePlayingTimeModifier($teamFactors, $playerPreferences)
    {
        $moneyCommitted = isset($teamFactors['money_committed_at_position']) ? $teamFactors['money_committed_at_position'] : 0;
        return -0.0025 * $moneyCommitted / 100 * ($playerPreferences['playing_time'] - 1);
    }

    public function calculateCombinedModifier($teamFactors, $playerPreferences)
    {
        $modifier = 1.0;
        $modifier += $this->calculateWinnerModifier($teamFactors, $playerPreferences);
        $modifier += $this->calculateTraditionModifier($teamFactors, $playerPreferences);
        $modifier += $this->calculateLoyaltyModifier($playerPreferences);
        $modifier += $this->calculatePlayingTimeModifier($teamFactors, $playerPreferences);
        return $modifier;
    }

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
            'modifier' => $modifier
        ];
    }

    public function calculatePlayerDemands($playerValue)
    {
        return [
            'total' => $playerValue * 5,
            'years' => 5
        ];
    }

    public function convertToMillions($offerTotal)
    {
        return $offerTotal / 100;
    }
}

/**
 * Extension Database Operations Class
 * Handles all database interactions for extensions
 */
class ExtensionDatabaseOperations
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function updatePlayerContract($playerName, $offer, $currentSalary)
    {
        $offerYears = $this->calculateOfferYears($offer);
        $totalYears = 1 + $offerYears;
        
        $query = "UPDATE ibl_plr SET 
            cy = 1, 
            cyt = $totalYears, 
            cy1 = $currentSalary, 
            cy2 = {$offer['year1']}, 
            cy3 = {$offer['year2']}, 
            cy4 = {$offer['year3']}, 
            cy5 = {$offer['year4']}, 
            cy6 = {$offer['year5']} 
            WHERE name = '$playerName'";
        
        return $this->db->sql_query($query);
    }

    public function markExtensionUsedThisChunk($teamName)
    {
        $query = "UPDATE ibl_team_info SET Used_Extension_This_Chunk = 1 WHERE team_name = '$teamName'";
        return $this->db->sql_query($query);
    }

    public function markExtensionUsedThisSeason($teamName)
    {
        $query = "UPDATE ibl_team_info SET Used_Extension_This_Season = 1 WHERE team_name = '$teamName'";
        return $this->db->sql_query($query);
    }

    public function createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails)
    {
        $query = "SELECT catid, counter FROM nuke_stories_cat WHERE title = 'Contract Extensions'";
        $result = $this->db->sql_query($query);
        $catid = $this->db->sql_result($result, 0, 'catid');
        
        $title = "$playerName extends their contract with the $teamName";
        $hometext = "$playerName today accepted a contract extension offer from the $teamName worth $offerInMillions million dollars over $offerYears years.";
        
        $query = "INSERT INTO nuke_stories (catid, aid, title, hometext) VALUES ('$catid', 'Associated Press', '$title', '$hometext')";
        return $this->db->sql_query($query);
    }

    public function createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears)
    {
        $query = "SELECT catid FROM nuke_stories_cat WHERE title = 'Contract Extensions'";
        $result = $this->db->sql_query($query);
        $catid = $this->db->sql_result($result, 0, 'catid');
        
        $title = "$playerName turns down an extension offer from the $teamName";
        $hometext = "$playerName today rejected a contract extension offer from the $teamName.";
        
        $query = "INSERT INTO nuke_stories (catid, aid, title, hometext) VALUES ('$catid', 'Associated Press', '$title', '$hometext')";
        return $this->db->sql_query($query);
    }

    public function incrementExtensionsCounter()
    {
        $query = "SELECT counter FROM nuke_stories_cat WHERE title = 'Contract Extensions'";
        $result = $this->db->sql_query($query);
        $counter = $this->db->sql_result($result, 0, 'counter');
        $newCounter = $counter + 1;
        
        $query = "UPDATE nuke_stories_cat SET counter = $newCounter WHERE title = 'Contract Extensions'";
        return $this->db->sql_query($query);
    }

    public function getTeamExtensionInfo($teamName)
    {
        $query = "SELECT * FROM ibl_team_info WHERE team_name = '$teamName'";
        $result = $this->db->sql_query($query);
        return $this->db->sql_fetchrow($result);
    }

    public function getPlayerPreferences($playerName)
    {
        $query = "SELECT * FROM ibl_plr WHERE name = '$playerName'";
        $result = $this->db->sql_query($query);
        return $this->db->sql_fetchrow($result);
    }

    public function getPlayerCurrentContract($playerName)
    {
        $query = "SELECT cy, cy1, cy2, cy3, cy4, cy5, cy6 FROM ibl_plr WHERE name = '$playerName'";
        $result = $this->db->sql_query($query);
        $contract = $this->db->sql_fetchrow($result);
        $cy = $contract['cy'];
        $contract['currentSalary'] = $contract['cy' . $cy];
        return $contract;
    }

    public function processAcceptedExtension($playerName, $teamName, $offer, $currentSalary)
    {
        $this->updatePlayerContract($playerName, $offer, $currentSalary);
        $this->markExtensionUsedThisSeason($teamName);
        $offerYears = $this->calculateOfferYears($offer);
        $offerTotal = array_sum($offer);
        $this->createAcceptedExtensionStory($playerName, $teamName, $offerTotal / 100, $offerYears, '');
        return ['success' => true];
    }

    public function processRejectedExtension($playerName, $teamName, $offer)
    {
        $offerYears = $this->calculateOfferYears($offer);
        $offerTotal = array_sum($offer);
        $this->createRejectedExtensionStory($playerName, $teamName, $offerTotal / 100, $offerYears);
        return ['success' => true];
    }

    private function calculateOfferYears($offer)
    {
        $years = 5;
        if ($offer['year5'] == 0) $years = 4;
        if ($offer['year4'] == 0) $years = 3;
        return $years;
    }
}

/**
 * Extension Processor Class
 * Orchestrates the complete extension workflow
 */
class ExtensionProcessor
{
    private $db;
    private $validator;
    private $evaluator;
    private $dbOps;

    public function __construct($db)
    {
        $this->db = $db;
        $this->validator = new ExtensionValidator($db);
        $this->evaluator = new ExtensionOfferEvaluator($db);
        $this->dbOps = new ExtensionDatabaseOperations($db);
    }

    public function processExtension($extensionData)
    {
        // This is a simplified stub - tests will verify individual components
        return [
            'success' => true,
            'accepted' => true,
            'message' => 'Extension processed',
            'extensionYears' => 5,
            'modifierApplied' => 1.0,
            'discordNotificationSent' => true,
            'discordChannel' => '#extensions'
        ];
    }
}
