<?php

declare(strict_types=1);

namespace Extension;

use Player\Player;
use Shared\SalaryConverter;
use Extension\Contracts\ExtensionProcessorInterface;
use Team\Contracts\TeamQueryRepositoryInterface;

/**
 * ExtensionProcessor - Processes contract extension offers
 *
 * Orchestrates the complete extension offer lifecycle: validation, evaluation,
 * database updates, and notifications.
 *
 * @phpstan-import-type ExtensionOffer from Contracts\ExtensionDatabaseOperationsInterface
 * @phpstan-import-type ExtensionData from Contracts\ExtensionProcessorInterface
 * @phpstan-import-type ExtensionResult from Contracts\ExtensionProcessorInterface
 *
 * @phpstan-type TraditionData array{currentSeasonWins: int, currentSeasonLosses: int, tradition_wins: int, tradition_losses: int}
 * @phpstan-type TeamTraditionDbRow array{Contract_Wins: int, Contract_Losses: int, Contract_AvgW: int, Contract_AvgL: int}
 * @phpstan-type MoneyCommittedDbRow array{money_committed_at_position: int}
 *
 * @see ExtensionProcessorInterface
 */
class ExtensionProcessor implements ExtensionProcessorInterface
{
    private \mysqli $db;
    private ExtensionValidator $validator;
    private ExtensionOfferEvaluator $evaluator;
    private ExtensionDatabaseOperations $dbOps;
    private TeamQueryRepositoryInterface $teamQueryRepo;

    /**
     * Constructor
     *
     * @param \mysqli $db mysqli connection
     */
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->validator = new ExtensionValidator();
        $this->evaluator = new ExtensionOfferEvaluator();
        $this->dbOps = new ExtensionDatabaseOperations($db);
        $this->teamQueryRepo = new \Team\TeamQueryRepository($db);
    }

    /**
     * @param ExtensionData $extensionData
     * @return ExtensionResult
     *
     * @see ExtensionProcessorInterface::processExtension()
     */
    public function processExtension($extensionData)
    {
        $offer = $extensionData['offer'];
        $demands = $extensionData['demands'] ?? null;
        $player = $this->getPlayerObject($extensionData);
        if ($player === null) {
            return [
                'success' => false,
                'error' => 'Player not found in database.'
            ];
        }

        $team = $this->getTeamObject($extensionData, $player);
        if ($team === null) {
            return [
                'success' => false,
                'error' => 'Team not found in database.'
            ];
        }

        $amountValidation = $this->validator->validateOfferAmounts($offer);
        if ($amountValidation['valid'] !== true) {
            return [
                'success' => false,
                'error' => (string) $amountValidation['error']
            ];
        }

        $eligibilityValidation = $this->validator->validateExtensionEligibility($team);
        if ($eligibilityValidation['valid'] !== true) {
            return [
                'success' => false,
                'error' => (string) $eligibilityValidation['error']
            ];
        }

        $maxOfferValidation = $this->validator->validateMaximumYearOneOffer($offer, $player->yearsOfExperience ?? 0);
        if ($maxOfferValidation['valid'] !== true) {
            return [
                'success' => false,
                'error' => (string) $maxOfferValidation['error']
            ];
        }

        $raisesValidation = $this->validator->validateRaises($offer, $player->birdYears ?? 0);
        if ($raisesValidation['valid'] !== true) {
            return [
                'success' => false,
                'error' => (string) $raisesValidation['error']
            ];
        }

        $decreasesValidation = $this->validator->validateSalaryDecreases($offer);
        if ($decreasesValidation['valid'] !== true) {
            return [
                'success' => false,
                'error' => (string) $decreasesValidation['error']
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

        if ($demands === null) {
            $offerData = $this->evaluator->calculateOfferValue($offer);
            $offerAvg = $offerData['averagePerYear'];
            $demandAvg = $offerAvg * 0.85;
            $demands = [
                'year1' => (int) $demandAvg,
                'year2' => (int) $demandAvg,
                'year3' => (int) $demandAvg,
                'year4' => $offerData['years'] > 3 ? (int) $demandAvg : 0,
                'year5' => $offerData['years'] > 4 ? (int) $demandAvg : 0
            ];
        } elseif (isset($demands['total']) && isset($demands['years'])) {
            /** @var array{total: int, years: int} $totalYearsDemands */
            $totalYearsDemands = $demands;
            $demandAvg = $totalYearsDemands['total'] / $totalYearsDemands['years'];
            $demands = [
                'year1' => (int) $demandAvg,
                'year2' => (int) $demandAvg,
                'year3' => (int) $demandAvg,
                'year4' => $totalYearsDemands['years'] > 3 ? (int) $demandAvg : 0,
                'year5' => $totalYearsDemands['years'] > 4 ? (int) $demandAvg : 0
            ];
        }

        /** @var ExtensionOffer $demands */
        $evaluation = $this->evaluator->evaluateOffer($offer, $demands, $teamFactors, $playerPreferences);

        $offerData = $this->evaluator->calculateOfferValue($offer);
        $offerTotal = $offerData['total'];
        $offerYears = $offerData['years'];
        $offerInMillions = SalaryConverter::convertToMillions($offerTotal);
        $offerDetails = $offer['year1'] . " " . $offer['year2'] . " " . $offer['year3'] . " " . $offer['year4'] . " " . $offer['year5'];

        $playerName = $player->name ?? '';
        $teamName = $team->name;

        if ($evaluation['accepted']) {
            $currentSalary = $player->currentSeasonSalary ?? 0;
            $this->dbOps->updatePlayerContract($playerName, $offer, $currentSalary);
            $this->dbOps->markExtensionUsedThisSeason($teamName);
            $this->dbOps->createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails);

            // Send Discord notification
            if (class_exists('Discord')) {
                $hometext = "{$playerName} today accepted a contract extension offer from the {$teamName} worth $offerInMillions million dollars over $offerYears years:<br>" . $offerDetails;
                \Discord::postToChannel('#extensions', $hometext);
            }

            // Send email notification (only on non-localhost)
            if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== "localhost") {
                $recipient = 'ibldepthcharts@gmail.com';
                // SECURITY: Sanitize email subject to prevent header injection
                $emailsubject = \Utilities\EmailSanitizer::sanitizeSubject("Successful Extension - " . $playerName);
                $filetext = "{$playerName} accepts an extension offer from the {$teamName} of $offerTotal for $offerYears years.\n";
                $filetext .= "For reference purposes: the offer was " . $offerDetails;
                $filetext .= " and the offer value was thus considered to be " . $evaluation['offerValue'];
                $filetext .= "; the player wanted an offer with a value of " . $evaluation['demandValue'];
                mail($recipient, $emailsubject, $filetext, "From: accepted-extensions@iblhoops.net");
            }

            return [
                'success' => true,
                'accepted' => true,
                'message' => "{$playerName} accepts your extension offer of $offerInMillions million dollars over $offerYears years. Thank you! (Can't believe you gave me that much... sucker!)",
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
            $this->dbOps->createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears);

            // Send Discord notification
            if (class_exists('Discord')) {
                $hometext = "{$playerName} today rejected a contract extension offer from the {$teamName} worth $offerInMillions million dollars over $offerYears years.";
                \Discord::postToChannel('#extensions', $hometext);
            }

            // Send email notification
            $recipient = 'ibldepthcharts@gmail.com';
            // SECURITY: Sanitize email subject to prevent header injection
            $emailsubject = \Utilities\EmailSanitizer::sanitizeSubject("Unsuccessful Extension - " . $playerName);
            $filetext = "{$playerName} refuses an extension offer from the {$teamName} of $offerTotal for $offerYears years.\n";
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

    /**
     * @param ExtensionData $extensionData
     * @return Player|null
     */
    private function getPlayerObject(array $extensionData): ?Player
    {
        // If Player object already provided, return it
        if (isset($extensionData['player']) && $extensionData['player'] instanceof Player) {
            return $extensionData['player'];
        }

        // Load player by playerID if provided
        $playerID = $extensionData['playerID'] ?? null;
        if ($playerID !== null) {
            try {
                return Player::withPlayerID($this->db, (int) $playerID);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param ExtensionData $extensionData
     * @param Player $player
     * @return \Team|null
     */
    private function getTeamObject(array $extensionData, Player $player): ?\Team
    {
        // If Team object already provided, return it
        if (isset($extensionData['team']) && $extensionData['team'] instanceof \Team) {
            return $extensionData['team'];
        }

        // Try to get team name from extension data or Player object
        $teamName = $extensionData['teamName'] ?? $player->teamName ?? null;
        if ($teamName === null) {
            return null;
        }

        try {
            return \Team::initialize($this->db, $teamName);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param \Team $team
     * @param string|null $position
     * @return int
     */
    private function calculateMoneyCommittedAtPosition(\Team $team, ?string $position): int
    {
        try {
            $stmt = $this->db->prepare("SELECT money_committed_at_position FROM ibl_team_info WHERE team_name = ? LIMIT 1");
            if ($stmt === false) {
                return 0;
            }
            $stmt->bind_param('s', $team->name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result !== false && $result->num_rows > 0) {
                /** @var MoneyCommittedDbRow|null $row */
                $row = $result->fetch_assoc();
                $stmt->close();
                if ($row !== null && $row['money_committed_at_position'] > 0) {
                    return $row['money_committed_at_position'];
                }
            } else {
                $stmt->close();
            }

            // Production: Use TeamQueryRepository to calculate
            if ($position !== null) {
                // Get players under contract at this position
                $posResult = $this->teamQueryRepo->getPlayersUnderContractByPosition($team->name, $position);

                // Calculate total next season salaries
                return $this->teamQueryRepo->getTotalNextSeasonSalaries($posResult);
            }

            return 0;
        } catch (\Exception $e) {
            // If there's an error, return 0 as a safe default
            return 0;
        }
    }

    /**
     * @param string $teamName
     * @return TraditionData
     */
    private function getTeamTraditionData(string $teamName): array
    {
        try {
            $stmt = $this->db->prepare("SELECT Contract_Wins, Contract_Losses, Contract_AvgW, Contract_AvgL FROM ibl_team_info WHERE team_name = ? LIMIT 1");
            if ($stmt === false) {
                return [
                    'currentSeasonWins' => 41,
                    'currentSeasonLosses' => 41,
                    'tradition_wins' => 41,
                    'tradition_losses' => 41
                ];
            }
            $stmt->bind_param('s', $teamName);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result !== false && $result->num_rows > 0) {
                /** @var TeamTraditionDbRow|null $row */
                $row = $result->fetch_assoc();
                $stmt->close();
                if ($row !== null) {
                    return [
                        'currentSeasonWins' => $row['Contract_Wins'],
                        'currentSeasonLosses' => $row['Contract_Losses'],
                        'tradition_wins' => $row['Contract_AvgW'],
                        'tradition_losses' => $row['Contract_AvgL']
                    ];
                }
            } else {
                $stmt->close();
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
