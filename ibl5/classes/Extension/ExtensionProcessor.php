<?php

declare(strict_types=1);

namespace Extension;

use Player\Player;
use Shared\SalaryConverter;
use Extension\Contracts\ExtensionProcessorInterface;

/**
 * ExtensionProcessor - Processes contract extension offers
 * 
 * Orchestrates the complete extension offer lifecycle: validation, evaluation,
 * database updates, and notifications.
 * 
 * @see ExtensionProcessorInterface
 */
class ExtensionProcessor implements ExtensionProcessorInterface
{
    private $db;
    private ExtensionValidator $validator;
    private ExtensionOfferEvaluator $evaluator;
    private ExtensionDatabaseOperations $dbOps;

    public function __construct($db)
    {
        $this->db = $db;
        $this->validator = new ExtensionValidator($db);
        $this->evaluator = new ExtensionOfferEvaluator($db);
        $this->dbOps = new ExtensionDatabaseOperations($db);
    }

    /**
     * @see ExtensionProcessorInterface::processExtension()
     */
    public function processExtension($extensionData)
    {
        $offer = $extensionData['offer'];
        $demands = $extensionData['demands'] ?? null;
        $player = $this->getPlayerObject($extensionData);
        if (!$player) {
            return [
                'success' => false,
                'error' => 'Player not found in database.'
            ];
        }

        $team = $this->getTeamObject($extensionData, $player);
        if (!$team) {
            return [
                'success' => false,
                'error' => 'Team not found in database.'
            ];
        }

        $amountValidation = $this->validator->validateOfferAmounts($offer);
        if (!$amountValidation['valid']) {
            return [
                'success' => false,
                'error' => $amountValidation['error']
            ];
        }

        $eligibilityValidation = $this->validator->validateExtensionEligibility($team);
        if (!$eligibilityValidation['valid']) {
            return [
                'success' => false,
                'error' => $eligibilityValidation['error']
            ];
        }

        $maxOfferValidation = $this->validator->validateMaximumYearOneOffer($offer, $player->yearsOfExperience);
        if (!$maxOfferValidation['valid']) {
            return [
                'success' => false,
                'error' => $maxOfferValidation['error']
            ];
        }

        $raisesValidation = $this->validator->validateRaises($offer, $player->birdYears);
        if (!$raisesValidation['valid']) {
            return [
                'success' => false,
                'error' => $raisesValidation['error']
            ];
        }

        $decreasesValidation = $this->validator->validateSalaryDecreases($offer);
        if (!$decreasesValidation['valid']) {
            return [
                'success' => false,
                'error' => $decreasesValidation['error']
            ];
        }

        $this->dbOps->markExtensionUsedThisSim($team->name);
        $moneyCommittedAtPosition = $this->calculateMoneyCommittedAtPosition($team, $player->position);
        $traditionData = $this->getTeamTraditionData($team->name);

        $teamFactors = [
            'wins' => $traditionData['currentSeasonWins'],
            'losses' => $traditionData['currentSeasonLosses'],
            'tradition_wins' => $traditionData['tradition_wins'],
            'tradition_losses' => $traditionData['tradition_losses'],
            'money_committed_at_position' => $moneyCommittedAtPosition
        ];

        $playerPreferences = [
            'winner' => $player->freeAgencyPlayForWinner ?? 3,
            'tradition' => $player->freeAgencyTradition ?? 3,
            'loyalty' => $player->freeAgencyLoyalty ?? 3,
            'playing_time' => $player->freeAgencyPlayingTime ?? 3
        ];

        if (!$demands) {
            $offerData = $this->evaluator->calculateOfferValue($offer);
            $offerAvg = $offerData['averagePerYear'];
            $demandAvg = $offerAvg * 0.85;
            $demands = [
                'year1' => $demandAvg,
                'year2' => $demandAvg,
                'year3' => $demandAvg,
                'year4' => $offerData['years'] > 3 ? $demandAvg : 0,
                'year5' => $offerData['years'] > 4 ? $demandAvg : 0
            ];
        } elseif (isset($demands['total']) && isset($demands['years'])) {
            $demandAvg = $demands['total'] / $demands['years'];
            $demands = [
                'year1' => $demandAvg,
                'year2' => $demandAvg,
                'year3' => $demandAvg,
                'year4' => $demands['years'] > 3 ? $demandAvg : 0,
                'year5' => $demands['years'] > 4 ? $demandAvg : 0
            ];
        }

        $evaluation = $this->evaluator->evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

        $offerData = $this->evaluator->calculateOfferValue($offer);
        $offerTotal = $offerData['total'];
        $offerYears = $offerData['years'];
        $offerInMillions = SalaryConverter::convertToMillions($offerTotal);
        $offerDetails = $offer['year1'] . " " . $offer['year2'] . " " . $offer['year3'] . " " . $offer['year4'] . " " . $offer['year5'];

        if ($evaluation['accepted']) {
            $currentSalary = $player->currentSeasonSalary ?? 0;
            $this->dbOps->updatePlayerContract($player->name, $offer, $currentSalary);
            $this->dbOps->markExtensionUsedThisSeason($team->name);
            $this->dbOps->createAcceptedExtensionStory($player->name, $team->name, $offerInMillions, $offerYears, $offerDetails);
            
            // Send Discord notification
            if (class_exists('Discord')) {
                $hometext = "{$player->name} today accepted a contract extension offer from the {$team->name} worth $offerInMillions million dollars over $offerYears years:<br>" . $offerDetails;
                \Discord::postToChannel('#extensions', $hometext);
            }
            
            // Send email notification (only on non-localhost)
            if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] != "localhost") {
                $recipient = 'ibldepthcharts@gmail.com';
                $emailsubject = "Successful Extension - " . $player->name;
                $filetext = "{$player->name} accepts an extension offer from the {$team->name} of $offerTotal for $offerYears years.\n";
                $filetext .= "For reference purposes: the offer was " . $offerDetails;
                $filetext .= " and the offer value was thus considered to be " . $evaluation['offerValue'];
                $filetext .= "; the player wanted an offer with a value of " . $evaluation['demandValue'];
                mail($recipient, $emailsubject, $filetext, "From: accepted-extensions@iblhoops.net");
            }

            return [
                'success' => true,
                'accepted' => true,
                'message' => "{$player->name} accepts your extension offer of $offerInMillions million dollars over $offerYears years. Thank you! (Can't believe you gave me that much... sucker!)",
                'offerValue' => $evaluation['offerValue'],
                'demandValue' => $evaluation['demandValue'],
                'modifier' => $evaluation['modifier'],
                'extensionYears' => $offerYears,
                'offerInMillions' => $offerInMillions,
                'offerDetails' => $offerDetails,
                'discordNotificationSent' => class_exists('Discord'),
                'discordChannel' => '#extensions'
            ];
        } else {
            // Create news story for rejection
            $this->dbOps->createRejectedExtensionStory($player->name, $team->name, $offerInMillions, $offerYears);
            
            // Send Discord notification
            if (class_exists('Discord')) {
                $hometext = "{$player->name} today rejected a contract extension offer from the {$team->name} worth $offerInMillions million dollars over $offerYears years.";
                \Discord::postToChannel('#extensions', $hometext);
            }
            
            // Send email notification
            $recipient = 'ibldepthcharts@gmail.com';
            $emailsubject = "Unsuccessful Extension - " . $player->name;
            $filetext = "{$player->name} refuses an extension offer from the {$team->name} of $offerTotal for $offerYears years.\n";
            $filetext .= "For reference purposes: the offer was " . $offerDetails;
            $filetext .= " and the offer value was thus considered to be " . $evaluation['offerValue'] . ".";
            mail($recipient, $emailsubject, $filetext, "From: rejected-extensions@iblhoops.net");

            return [
                'success' => true,
                'accepted' => false,
                'message' => "While I appreciate your offer of $offerInMillions million dollars over $offerYears years, I refuse it as it kinda sucks, and isn't what I'm looking for. You're gonna have to try harder if you want me to stick around this dump!",
                'refusalMessage' => "refuses",
                'offerValue' => $evaluation['offerValue'],
                'demandValue' => $evaluation['demandValue'],
                'modifier' => $evaluation['modifier'],
                'extensionYears' => $offerYears,
                'offerInMillions' => $offerInMillions,
                'offerDetails' => $offerDetails,
                'discordNotificationSent' => class_exists('Discord'),
                'discordChannel' => '#extensions'
            ];
        }
    }

    private function getPlayerObject($extensionData)
    {
        // If Player object already provided, return it
        if (isset($extensionData['player']) && $extensionData['player'] instanceof \Player) {
            return $extensionData['player'];
        }

        // Load player by playerID if provided
        $playerID = $extensionData['playerID'] ?? null;
        if ($playerID) {
            try {
                return Player::withPlayerID($this->db, (int)$playerID);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function getTeamObject($extensionData, $player)
    {
        // If Team object already provided, return it
        if (isset($extensionData['team']) && $extensionData['team'] instanceof \Team) {
            return $extensionData['team'];
        }

        // Try to get team name from extension data or Player object
        $teamName = $extensionData['teamName'] ?? $player->teamName ?? null;
        if (!$teamName) {
            return null;
        }

        try {
            return \Team::initialize($this->db, $teamName);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function calculateMoneyCommittedAtPosition($team, $position)
    {
        try {
            // First, try to get from mock database (for tests)
            // Check if team_info has money_committed_at_position field
            $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $team->name);
            $query = "SELECT money_committed_at_position FROM ibl_team_info WHERE team_name = '$teamNameEscaped' LIMIT 1";
            $result = $this->db->sql_query($query);
            
            if ($this->db->sql_numrows($result) > 0) {
                $row = $this->db->sql_fetch_assoc($result);
                if (isset($row['money_committed_at_position']) && $row['money_committed_at_position'] > 0) {
                    // Mock data is available, use it
                    return (int) $row['money_committed_at_position'];
                }
            }
            
            // Production: Use Team methods to calculate
            if (method_exists($team, 'getPlayersUnderContractByPositionResult') && $position) {
                // Get players under contract at this position
                $result = $team->getPlayersUnderContractByPositionResult($position);
                
                // Calculate total next season salaries
                $totalSalaries = $team->getTotalNextSeasonSalariesFromPlrResult($result);
                
                return (int) $totalSalaries;
            }
            
            return 0;
        } catch (\Exception $e) {
            // If there's an error, return 0 as a safe default
            return 0;
        }
    }

    private function getTeamTraditionData($teamName)
    {
        try {
            $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
            $query = "SELECT Contract_Wins, Contract_Losses, Contract_AvgW, Contract_AvgL FROM ibl_team_info WHERE team_name = '$teamNameEscaped' LIMIT 1";
            $result = $this->db->sql_query($query);
            
            if ($this->db->sql_numrows($result) > 0) {
                $row = $this->db->sql_fetch_assoc($result);
                return [
                    'currentSeasonWins' => isset($row['Contract_Wins']) ? (int) $row['Contract_Wins'] : 41,
                    'currentSeasonLosses' => isset($row['Contract_Losses']) ? (int) $row['Contract_Losses'] : 41,
                    'tradition_wins' => isset($row['Contract_AvgW']) ? (int) $row['Contract_AvgW'] : 41,
                    'tradition_losses' => isset($row['Contract_AvgL']) ? (int) $row['Contract_AvgL'] : 41
                ];
            }
        } catch (\Exception $e) {
            // Log error if needed
        }
        
        return [
            'currentSeasonWins' => 41,
            'currentSeasonLosses' => 41,
            'tradition_wins' => 41,
            'tradition_losses' => 41
        ];
    }

}
