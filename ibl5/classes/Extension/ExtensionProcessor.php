<?php

namespace Extension;

/**
 * Extension Processor Class
 * 
 * Orchestrates the complete contract extension workflow by coordinating:
 * - Validation (via ExtensionValidator)
 * - Evaluation (via ExtensionOfferEvaluator)
 * - Database operations (via ExtensionDatabaseOperations)
 * - Notifications (Discord, email)
 * 
 * This class provides the main entry point for processing extension offers
 * and returns structured results instead of echoing HTML directly.
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

    /**
     * Processes a contract extension offer through the complete workflow
     * 
     * @param array $extensionData Array containing:
     *   - teamName: string
     *   - playerName: string
     *   - offer: array [year1, year2, year3, year4, year5]
     *   - demands: array [total, years]
     *   - bird: int (Bird rights years)
     * @return array Result array with:
     *   - success: bool
     *   - accepted: bool (if successful)
     *   - error: string (if not successful)
     *   - message: string
     *   - offerValue: float
     *   - demandValue: float
     *   - modifier: float
     *   - extensionYears: int
     */
    public function processExtension($extensionData)
    {
        $teamName = $extensionData['teamName'];
        $playerName = $extensionData['playerName'];
        $offer = $extensionData['offer'];
        $demands = isset($extensionData['demands']) ? $extensionData['demands'] : null;
        $bird = isset($extensionData['bird']) ? $extensionData['bird'] : 2;

        // Step 1: Validate offer amounts
        $amountValidation = $this->validator->validateOfferAmounts($offer);
        if (!$amountValidation['valid']) {
            return [
                'success' => false,
                'error' => $amountValidation['error']
            ];
        }

        // Step 2: Check extension eligibility
        $eligibilityValidation = $this->validator->validateExtensionEligibility($teamName);
        if (!$eligibilityValidation['valid']) {
            return [
                'success' => false,
                'error' => $eligibilityValidation['error']
            ];
        }

        // Step 3: Get player info for validation
        $playerInfo = $this->dbOps->getPlayerPreferences($playerName);
        if (!$playerInfo) {
            return [
                'success' => false,
                'error' => 'Player not found in database.'
            ];
        }

        $yearsExperience = isset($playerInfo['exp']) ? $playerInfo['exp'] : 5;
        $birdYears = isset($playerInfo['bird']) ? $playerInfo['bird'] : $bird;

        // Step 4: Validate maximum offer
        $maxOfferValidation = $this->validator->validateMaximumOffer($offer, $yearsExperience);
        if (!$maxOfferValidation['valid']) {
            return [
                'success' => false,
                'error' => $maxOfferValidation['error']
            ];
        }

        // Step 5: Validate raises
        $raisesValidation = $this->validator->validateRaises($offer, $birdYears);
        if (!$raisesValidation['valid']) {
            return [
                'success' => false,
                'error' => $raisesValidation['error']
            ];
        }

        // Step 6: Validate salary decreases
        $decreasesValidation = $this->validator->validateSalaryDecreases($offer);
        if (!$decreasesValidation['valid']) {
            return [
                'success' => false,
                'error' => $decreasesValidation['error']
            ];
        }

        // Step 7: Mark extension used for this chunk (legal offer made)
        $this->dbOps->markExtensionUsedThisChunk($teamName);

        // Step 8: Get team factors for evaluation
        $teamInfo = $this->dbOps->getTeamExtensionInfo($teamName);
        if (!$teamInfo) {
            return [
                'success' => false,
                'error' => 'Team not found in database.'
            ];
        }

        $teamFactors = [
            'wins' => isset($teamInfo['Contract_Wins']) ? $teamInfo['Contract_Wins'] : 41,
            'losses' => isset($teamInfo['Contract_Losses']) ? $teamInfo['Contract_Losses'] : 41,
            'tradition_wins' => isset($teamInfo['Contract_AvgW']) ? $teamInfo['Contract_AvgW'] : 41,
            'tradition_losses' => isset($teamInfo['Contract_AvgL']) ? $teamInfo['Contract_AvgL'] : 41,
            'money_committed_at_position' => isset($teamInfo['money_committed_at_position']) ? $teamInfo['money_committed_at_position'] : 0
        ];

        $playerPreferences = [
            'winner' => isset($playerInfo['winner']) ? $playerInfo['winner'] : 3,
            'tradition' => isset($playerInfo['tradition']) ? $playerInfo['tradition'] : 3,
            'loyalty' => isset($playerInfo['loyalty']) ? $playerInfo['loyalty'] : 3,
            'playing_time' => isset($playerInfo['playingTime']) ? $playerInfo['playingTime'] : 3
        ];

        // Step 9: Convert demands to array format if needed
        if (!$demands) {
            // If no demands provided, use a default based on the offer (85% of offer)
            // This allows modifiers to determine acceptance/rejection
            $offerData = $this->evaluator->calculateOfferValue($offer);
            $offerAvg = $offerData['averagePerYear'];
            $demandAvg = $offerAvg * 0.85; // Player demands 85% of what's offered
            $demands = [
                'year1' => $demandAvg,
                'year2' => $demandAvg,
                'year3' => $demandAvg,
                'year4' => $offerData['years'] > 3 ? $demandAvg : 0,
                'year5' => $offerData['years'] > 4 ? $demandAvg : 0
            ];
        } elseif (isset($demands['total']) && isset($demands['years'])) {
            // Convert simple demands to array format
            $demandAvg = $demands['total'] / $demands['years'];
            $demands = [
                'year1' => $demandAvg,
                'year2' => $demandAvg,
                'year3' => $demandAvg,
                'year4' => $demands['years'] > 3 ? $demandAvg : 0,
                'year5' => $demands['years'] > 4 ? $demandAvg : 0
            ];
        }

        // Step 10: Evaluate offer
        $evaluation = $this->evaluator->evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

        // Step 11: Calculate values for reporting
        $offerData = $this->evaluator->calculateOfferValue($offer);
        $offerTotal = $offerData['total'];
        $offerYears = $offerData['years'];
        $offerInMillions = $this->evaluator->convertToMillions($offerTotal);
        $offerDetails = $offer['year1'] . " " . $offer['year2'] . " " . $offer['year3'] . " " . $offer['year4'] . " " . $offer['year5'];

        // Step 12: Process based on acceptance
        if ($evaluation['accepted']) {
            // Get current contract info
            $currentContract = $this->dbOps->getPlayerCurrentContract($playerName);
            $currentSalary = isset($currentContract['currentSalary']) ? $currentContract['currentSalary'] : 0;

            // Update player contract
            $this->dbOps->updatePlayerContract($playerName, $offer, $currentSalary);
            
            // Mark extension used for season
            $this->dbOps->markExtensionUsedThisSeason($teamName);
            
            // Create news story
            $this->dbOps->createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails);
            
            // Send Discord notification
            if (class_exists('Discord')) {
                $hometext = "$playerName today accepted a contract extension offer from the $teamName worth $offerInMillions million dollars over $offerYears years:<br>" . $offerDetails;
                \Discord::postToChannel('#extensions', $hometext);
            }
            
            // Send email notification (only on non-localhost)
            if ($_SERVER['SERVER_NAME'] != "localhost") {
                $recipient = 'ibldepthcharts@gmail.com';
                $emailsubject = "Successful Extension - " . $playerName;
                $filetext = "$playerName accepts an extension offer from the $teamName of $offerTotal for $offerYears years.\n";
                $filetext .= "For reference purposes: the offer was " . $offerDetails;
                $filetext .= " and the offer value was thus considered to be " . $evaluation['offerValue'];
                $filetext .= "; the player wanted an offer with a value of " . $evaluation['demandValue'];
                mail($recipient, $emailsubject, $filetext, "From: accepted-extensions@iblhoops.net");
            }

            return [
                'success' => true,
                'accepted' => true,
                'message' => "$playerName accepts your extension offer of $offerInMillions million dollars over $offerYears years. Thank you! (Can't believe you gave me that much...sucker!)",
                'offerValue' => $evaluation['offerValue'],
                'demandValue' => $evaluation['demandValue'],
                'modifier' => $evaluation['modifier'],
                'modifierApplied' => $evaluation['modifier'],
                'extensionYears' => $offerYears,
                'offerInMillions' => $offerInMillions,
                'offerDetails' => $offerDetails,
                'discordNotificationSent' => class_exists('Discord'),
                'discordChannel' => '#extensions'
            ];
        } else {
            // Create news story for rejection
            $this->dbOps->createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears);
            
            // Send Discord notification
            if (class_exists('Discord')) {
                $hometext = "$playerName today rejected a contract extension offer from the $teamName worth $offerInMillions million dollars over $offerYears years.";
                \Discord::postToChannel('#extensions', $hometext);
            }
            
            // Send email notification
            $recipient = 'ibldepthcharts@gmail.com';
            $emailsubject = "Unsuccessful Extension - " . $playerName;
            $filetext = "$playerName refuses an extension offer from the $teamName of $offerTotal for $offerYears years.\n";
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
                'modifierApplied' => $evaluation['modifier'],
                'extensionYears' => $offerYears,
                'offerInMillions' => $offerInMillions,
                'offerDetails' => $offerDetails,
                'discordNotificationSent' => class_exists('Discord'),
                'discordChannel' => '#extensions'
            ];
        }
    }
}
