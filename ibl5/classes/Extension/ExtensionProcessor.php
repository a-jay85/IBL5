<?php

declare(strict_types=1);

namespace Extension;

use Player\Player;
use Shared\SalaryConverter;
use Extension\Contracts\ExtensionProcessorInterface;
use Services\CommonContractValidator;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Team;
use Discord\Discord;

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
 * @phpstan-type EvaluationContext array{teamFactors: array{wins: int, losses: int, tradition_wins: int, tradition_losses: int, money_committed_at_position: int}, playerPreferences: array{winner: int, tradition: int, loyalty: int, playing_time: int}, demands: ExtensionOffer}
 *
 * @see ExtensionProcessorInterface
 */
class ExtensionProcessor implements ExtensionProcessorInterface
{
    private \mysqli $db;
    private ExtensionValidator $validator;
    private ExtensionOfferEvaluator $evaluator;
    private CommonContractValidator $contractValidator;
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
        $this->contractValidator = new CommonContractValidator();
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
        // Phase 1: Resolve entities
        $offer = $extensionData['offer'];
        $demands = $extensionData['demands'] ?? null;
        $player = $this->getPlayerObject($extensionData);
        if ($player === null) {
            return ['success' => false, 'error' => 'Player not found in database.'];
        }

        $team = $this->getTeamObject($extensionData, $player);
        if ($team === null) {
            return ['success' => false, 'error' => 'Team not found in database.'];
        }

        // Phase 2: Validate offer
        $validationError = $this->validateExtensionOffer($offer, $player, $team);
        if ($validationError !== null) {
            return $validationError;
        }

        // Phase 3: Build evaluation context
        $context = $this->buildEvaluationContext($team, $player, $demands, $offer);

        // Phase 4: Evaluate offer and prepare result data
        $evaluation = $this->evaluator->evaluateOffer($offer, $context['demands'], $context['teamFactors'], $context['playerPreferences']);

        $offerData = $this->contractValidator->calculateOfferValue($offer);
        $offerTotal = $offerData['total'];
        $offerYears = $offerData['years'];
        $offerInMillions = SalaryConverter::convertToMillions($offerTotal);
        $offerDetails = $offer['year1'] . " " . $offer['year2'] . " " . $offer['year3'] . " " . $offer['year4'] . " " . $offer['year5'];

        $playerName = $player->name ?? '';
        $teamName = $team->name;

        // Phase 5: Handle acceptance or rejection
        if ($evaluation['accepted']) {
            return $this->handleAcceptedExtension(
                $player, $team, $offer, $evaluation,
                $offerTotal, $offerInMillions, $offerYears, $offerDetails
            );
        }

        return $this->handleRejectedExtension(
            $playerName, $teamName, $evaluation,
            $offerTotal, $offerInMillions, $offerYears, $offerDetails
        );
    }

    /**
     * Validate extension offer amounts, eligibility, contract rules
     *
     * @param ExtensionOffer $offer
     * @return array{success: false, error: string}|null Error result or null on success
     */
    private function validateExtensionOffer(array $offer, Player $player, Team $team): ?array
    {
        $amountValidation = $this->validator->validateOfferAmounts($offer);
        if ($amountValidation['valid'] !== true) {
            return ['success' => false, 'error' => (string) $amountValidation['error']];
        }

        $eligibilityValidation = $this->validator->validateExtensionEligibility($team);
        if ($eligibilityValidation['valid'] !== true) {
            return ['success' => false, 'error' => (string) $eligibilityValidation['error']];
        }

        $maxOfferValidation = $this->contractValidator->validateMaximumYearOne($offer, $player->yearsOfExperience ?? 0);
        if ($maxOfferValidation['valid'] !== true) {
            return ['success' => false, 'error' => (string) $maxOfferValidation['error']];
        }

        $raisesValidation = $this->contractValidator->validateRaises($offer, $player->birdYears ?? 0);
        if ($raisesValidation['valid'] !== true) {
            return ['success' => false, 'error' => (string) $raisesValidation['error']];
        }

        $decreasesValidation = $this->contractValidator->validateSalaryDecreases($offer);
        if ($decreasesValidation['valid'] !== true) {
            return ['success' => false, 'error' => (string) $decreasesValidation['error']];
        }

        return null;
    }

    /**
     * Build evaluation context: mark sim usage, calculate factors, normalize demands
     *
     * @param ExtensionOffer $offer
     * @param array{total: int, years: int}|ExtensionOffer|null $demands
     * @return EvaluationContext
     */
    private function buildEvaluationContext(Team $team, Player $player, array|null $demands, array $offer): array
    {
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
            $offerData = $this->contractValidator->calculateOfferValue($offer);
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
        return [
            'teamFactors' => $teamFactors,
            'playerPreferences' => $playerPreferences,
            'demands' => $demands,
        ];
    }

    /**
     * Handle accepted extension: DB transaction, audit log, Discord, email
     *
     * @param ExtensionOffer $offer
     * @param array{accepted: bool, offerValue: float, demandValue: float, modifier: float} $evaluation
     * @return array{success: true, accepted: true, message: string, offerValue: float, demandValue: float, modifier: float, extensionYears: int, offerInMillions: float, offerDetails: string, discordNotificationSent: bool, discordChannel: string}
     */
    private function handleAcceptedExtension(
        Player $player,
        Team $team,
        array $offer,
        array $evaluation,
        int $offerTotal,
        float $offerInMillions,
        int $offerYears,
        string $offerDetails
    ): array {
        $playerName = $player->name ?? '';
        $teamName = $team->name;
        $currentSalary = $player->currentSeasonSalary ?? 0;

        $this->db->begin_transaction();
        try {
            $this->dbOps->updatePlayerContract($playerName, $offer, $currentSalary);
            $this->dbOps->markExtensionUsedThisSeason($teamName);
            $this->dbOps->createAcceptedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears, $offerDetails);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        \Logging\LoggerFactory::getChannel('audit')->info('extension_accepted', [
            'action' => 'extension_accepted',
            'player_name' => $playerName,
            'team_name' => $teamName,
            'years' => $offerYears,
            'total_millions' => $offerInMillions,
            'details' => $offerDetails,
        ]);

        if (class_exists(Discord::class)) {
            $hometext = "{$playerName} today accepted a contract extension offer from the {$teamName} worth $offerInMillions million dollars over $offerYears years:<br>" . $offerDetails;
            Discord::postToChannel('#extensions', $hometext);

            $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
            if ($serverName !== 'localhost' && $serverName !== '127.0.0.1') {
                Discord::postToChannel('#general-chat', $hometext);
            }
        }

        $emailsubject = "Successful Extension - " . $playerName;
        $filetext = "{$playerName} accepts an extension offer from the {$teamName} of $offerTotal for $offerYears years.\n";
        $filetext .= "For reference purposes: the offer was " . $offerDetails;
        $filetext .= " and the offer value was thus considered to be " . $evaluation['offerValue'];
        $filetext .= "; the player wanted an offer with a value of " . $evaluation['demandValue'];
        \Mail\MailService::fromConfig()->send('ibldepthcharts@gmail.com', $emailsubject, $filetext, 'accepted-extensions@iblhoops.net');

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
            'discordNotificationSent' => class_exists(Discord::class),
            'discordChannel' => '#extensions'
        ];
    }

    /**
     * Handle rejected extension: news story, audit log, Discord, email
     *
     * @param array{accepted: bool, offerValue: float, demandValue: float, modifier: float} $evaluation
     * @return array{success: true, accepted: false, message: string, refusalMessage: string, offerValue: float, demandValue: float, modifier: float, extensionYears: int, offerInMillions: float, offerDetails: string, discordNotificationSent: bool, discordChannel: string}
     */
    private function handleRejectedExtension(
        string $playerName,
        string $teamName,
        array $evaluation,
        int $offerTotal,
        float $offerInMillions,
        int $offerYears,
        string $offerDetails
    ): array {
        $this->dbOps->createRejectedExtensionStory($playerName, $teamName, $offerInMillions, $offerYears);

        \Logging\LoggerFactory::getChannel('audit')->info('extension_rejected', [
            'action' => 'extension_rejected',
            'player_name' => $playerName,
            'team_name' => $teamName,
            'years' => $offerYears,
            'total_millions' => $offerInMillions,
        ]);

        if (class_exists(Discord::class)) {
            $hometext = "{$playerName} today rejected a contract extension offer from the {$teamName} worth $offerInMillions million dollars over $offerYears years.";
            Discord::postToChannel('#extensions', $hometext);
        }

        $emailsubject = "Unsuccessful Extension - " . $playerName;
        $filetext = "{$playerName} refuses an extension offer from the {$teamName} of $offerTotal for $offerYears years.\n";
        $filetext .= "For reference purposes: the offer was " . $offerDetails;
        $filetext .= " and the offer value was thus considered to be " . $evaluation['offerValue'] . ".";
        \Mail\MailService::fromConfig()->send('ibldepthcharts@gmail.com', $emailsubject, $filetext, 'rejected-extensions@iblhoops.net');

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
            'discordNotificationSent' => class_exists(Discord::class),
            'discordChannel' => '#extensions'
        ];
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
     * @return Team|null
     */
    private function getTeamObject(array $extensionData, Player $player): ?Team
    {
        // If Team object already provided, return it
        if (isset($extensionData['team']) && $extensionData['team'] instanceof Team) {
            return $extensionData['team'];
        }

        // Try to get team name from extension data or Player object
        $teamName = $extensionData['teamName'] ?? $player->teamName ?? null;
        if ($teamName === null) {
            return null;
        }

        try {
            return Team::initialize($this->db, $teamName);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param Team $team
     * @param string|null $position
     * @return int
     */
    private function calculateMoneyCommittedAtPosition(Team $team, ?string $position): int
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
                $posResult = $this->teamQueryRepo->getPlayersUnderContractByPosition($team->teamID, $position);

                // Calculate total next season salaries
                return $this->teamQueryRepo->getTotalNextSeasonSalaries($posResult);
            }

            return 0;
        } catch (\Exception $e) {
            \Logging\LoggerFactory::getChannel('app')->warning('ExtensionProcessor::calculateMoneyCommittedAtPosition failed', ['error' => $e->getMessage()]);
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
            \Logging\LoggerFactory::getChannel('app')->warning('ExtensionProcessor::getTeamTraditionData failed', ['error' => $e->getMessage()]);
        }

        return [
            'currentSeasonWins' => 41,
            'currentSeasonLosses' => 41,
            'tradition_wins' => 41,
            'tradition_losses' => 41
        ];
    }
}
