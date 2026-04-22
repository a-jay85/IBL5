<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradeOfferInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\Contracts\CashConsiderationRepositoryInterface;
use Season\Season;
use Discord\Discord;

/**
 * TradeOffer - Creates and manages trade offers
 *
 * Handles the creation of trade offers, validation of trade terms,
 * and notification of trade proposals to receiving teams.
 *
 * @see TradeOfferInterface
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type DraftPickRow from \Trading\Contracts\TradeAssetRepositoryInterface
 *
 * @phpstan-type TradeFormData array{offeringTeam: string, listeningTeam: string, switchCounter: int, fieldsCounter: int, check: array<int, string|null>, index: array<int, string>, type: array<int, string>, contract: array<int, string>, userSendsCash: array<int, int>, partnerSendsCash: array<int, int>}
 */
class TradeOffer implements TradeOfferInterface
{
    protected \mysqli $db;
    protected TradeOfferRepositoryInterface $offerRepository;
    protected TradeAssetRepositoryInterface $assetRepository;
    protected TradeCashRepositoryInterface $cashRepository;
    protected CashConsiderationRepositoryInterface $cashConsiderationRepository;
    protected \Services\CommonMysqliRepository $commonRepository;
    protected Season $season;
    protected CashTransactionHandler $cashHandler;
    protected TradeValidator $validator;
    protected ?Discord $discord;

    public function __construct(
        \mysqli $db,
        ?TradeOfferRepositoryInterface $offerRepository = null,
        ?TradeAssetRepositoryInterface $assetRepository = null
    ) {
        $this->db = $db;
        $this->offerRepository = $offerRepository ?? new TradeOfferRepository($db);
        $this->assetRepository = $assetRepository ?? new TradeAssetRepository($db);
        $this->cashRepository = new TradeCashRepository($db);
        $this->cashConsiderationRepository = new CashConsiderationRepository($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
        $this->season = new Season($db);
        $this->cashHandler = new CashTransactionHandler($db, $this->cashConsiderationRepository, $this->cashRepository);
        $this->validator = new TradeValidator($db);

        // Initialize Discord with error handling (may fail if column doesn't exist)
        try {
            $this->discord = new Discord($db);
        } catch (\Exception $e) {
            // Discord unavailable - will skip notifications
            \Logging\LoggerFactory::getChannel('trade')->warning('Discord initialization failed in TradeOffer', ['error' => $e->getMessage()]);
            $this->discord = null;
        }
    }

    /**
     * @see TradeOfferInterface::createTradeOffer()
     *
     * @param TradeFormData $tradeData
     * @return array{success: bool, error?: string, errors?: array<string>, capData?: array{valid: bool, errors: array<string>, userPostTradeCapTotal: int, partnerPostTradeCapTotal: int}, tradeText?: string, tradeOfferId?: int}
     */
    public function createTradeOffer(array $tradeData): array
    {
        // Generate new trade offer ID
        $tradeOfferId = $this->generateTradeOfferId();
        
        // Validate cash amounts
        $cashValidation = $this->validator->validateMinimumCashAmounts(
            $tradeData['userSendsCash'], 
            $tradeData['partnerSendsCash']
        );
        
        if ($cashValidation['valid'] !== true) {
            return ['success' => false, 'error' => $cashValidation['error'] ?? 'Cash validation failed'];
        }

        // Calculate and validate salary caps
        $capData = $this->calculateSalaryCapData($tradeData);
        $capValidation = $this->validator->validateSalaryCaps($capData);
        
        if ($capValidation['valid'] !== true) {
            return [
                'success' => false,
                'errors' => $capValidation['errors'],
                'capData' => $capValidation
            ];
        }

        // Count players being sent by each side
        $userPlayersSent = 0;
        $partnerPlayersSent = 0;
        $switchCounter = $tradeData['switchCounter'];
        $fieldsCounter = $tradeData['fieldsCounter'];

        for ($i = 0; $i < $switchCounter; $i++) {
            if (($tradeData['check'][$i] ?? null) === 'on' && ($tradeData['type'][$i] ?? '') === '1') {
                $userPlayersSent++;
            }
        }

        for ($i = $switchCounter; $i < $fieldsCounter; $i++) {
            if (($tradeData['check'][$i] ?? null) === 'on' && ($tradeData['type'][$i] ?? '') === '1') {
                $partnerPlayersSent++;
            }
        }

        // Validate roster limits (resolve team IDs for the teamid-based query)
        $userTeamId = $this->commonRepository->getTidFromTeamname($tradeData['offeringTeam']) ?? 0;
        $partnerTeamId = $this->commonRepository->getTidFromTeamname($tradeData['listeningTeam']) ?? 0;

        $rosterValidation = $this->validator->validateRosterLimits(
            $userTeamId,
            $partnerTeamId,
            $userPlayersSent,
            $partnerPlayersSent
        );

        if ($rosterValidation['valid'] !== true) {
            return [
                'success' => false,
                'errors' => $rosterValidation['errors'],
            ];
        }

        // Process the trade offer creation
        $result = $this->insertTradeOfferData($tradeOfferId, $tradeData);
        
        if ($result['success']) {
            $this->sendTradeNotification($tradeData, $result['tradeText'], $tradeOfferId);
        }

        return $result;
    }

    /**
     * Generate a new unique trade offer ID
     *
     * Delegates to repository which uses AUTO_INCREMENT for atomic ID generation.
     *
     * @return int New trade offer ID
     */
    protected function generateTradeOfferId(): int
    {
        return $this->offerRepository->generateNextTradeOfferId();
    }

    /**
     * Calculate salary cap data for both teams
     *
     * Computes current cap totals and amounts being sent/received for both teams,
     * including existing cash transaction records and new cash considerations.
     *
     * For self-trades (same team on both sides), sent/received amounts are zeroed
     * because all players remain on the same roster.
     *
     * @param TradeFormData $tradeData Trade data from form submission
     * @return array{userCurrentSeasonCapTotal: int, partnerCurrentSeasonCapTotal: int, userCapSentToPartner: int, partnerCapSentToUser: int}
     */
    protected function calculateSalaryCapData(array $tradeData): array
    {
        $userCurrentSeasonCapTotal = 0;
        $partnerCurrentSeasonCapTotal = 0;
        $userCapSentToPartner = 0;
        $partnerCapSentToUser = 0;

        // Calculate user team salary data from form
        $switchCounter = $tradeData['switchCounter'];
        for ($j = 0; $j < $switchCounter; $j++) {
            $isChecked = $tradeData['check'][$j] ?? null;
            $salaryAmount = (int) ($tradeData['contract'][$j] ?? '0');
            $userCurrentSeasonCapTotal += $salaryAmount;

            if ($isChecked === "on") {
                $userCapSentToPartner += $salaryAmount;
            }
        }

        // Calculate partner team salary data from form
        $fieldsCounter = $tradeData['fieldsCounter'];
        for ($j = $switchCounter; $j < $fieldsCounter; $j++) {
            $isChecked = $tradeData['check'][$j] ?? null;
            $salaryAmount = (int) ($tradeData['contract'][$j] ?? '0');
            $partnerCurrentSeasonCapTotal += $salaryAmount;

            if ($isChecked === "on") {
                $partnerCapSentToUser += $salaryAmount;
            }
        }

        // Include existing cash transaction records (e.g. "Cash from Heat")
        // which are stored as player records with names starting with '|'
        // but excluded from the form's hidden contract fields
        $userTeamId = $this->commonRepository->getTidFromTeamname($tradeData['offeringTeam']) ?? 0;
        $partnerTeamId = $this->commonRepository->getTidFromTeamname($tradeData['listeningTeam']) ?? 0;

        $userCurrentSeasonCapTotal += $this->sumCashRecordSalaries($userTeamId);
        $partnerCurrentSeasonCapTotal += $this->sumCashRecordSalaries($partnerTeamId);

        // Add new cash considerations from this trade offer
        $cashConsiderations = $this->validator->getCurrentSeasonCashConsiderations(
            $tradeData['userSendsCash'],
            $tradeData['partnerSendsCash']
        );

        $userCurrentSeasonCapTotal += $cashConsiderations['cashSentToThem'];
        $userCurrentSeasonCapTotal -= $cashConsiderations['cashSentToMe'];
        $partnerCurrentSeasonCapTotal += $cashConsiderations['cashSentToMe'];
        $partnerCurrentSeasonCapTotal -= $cashConsiderations['cashSentToThem'];

        // Self-trade: both sides are the same team, so no players actually move.
        // Zero out sent/received to avoid double-counting.
        if ($tradeData['offeringTeam'] === $tradeData['listeningTeam']) {
            $userCapSentToPartner = 0;
            $partnerCapSentToUser = 0;
        }

        return [
            'userCurrentSeasonCapTotal' => $userCurrentSeasonCapTotal,
            'partnerCurrentSeasonCapTotal' => $partnerCurrentSeasonCapTotal,
            'userCapSentToPartner' => $userCapSentToPartner,
            'partnerCapSentToUser' => $partnerCapSentToUser
        ];
    }

    /**
     * Sum current-season salary from cash consideration records for a team
     *
     * Cash entries (trades, buyouts) are excluded from form hidden fields
     * but still affect the team's salary cap. This computes their current-year
     * impact using the same contract-year logic as the form.
     *
     * @param int $teamId Team ID
     * @return int Sum of cash record salaries for the current season (may be negative)
     */
    private function sumCashRecordSalaries(int $teamId): int
    {
        $cashRecords = $this->cashConsiderationRepository->getTeamCashForSalary($teamId);
        $isOffseason = $this->season->phase === 'Playoffs'
            || $this->season->phase === 'Draft'
            || $this->season->phase === 'Free Agency';

        $total = 0;
        foreach ($cashRecords as $record) {
            $cy = $record['cy'] ?? 1;
            if ($isOffseason) {
                $cy++;
            }
            if ($cy === 0) {
                $cy = 1;
            }
            $total += match ($cy) {
                1 => $record['cy1'],
                2 => $record['cy2'],
                3 => $record['cy3'],
                4 => $record['cy4'],
                5 => $record['cy5'],
                6 => $record['cy6'],
                default => 0,
            };
        }

        return $total;
    }

    /**
     * Insert trade offer data into database
     *
     * Processes all checked items from both teams and inserts them into trade_info.
     * Handles players, picks, and cash considerations.
     *
     * @param int $tradeOfferId Trade offer ID
     * @param TradeFormData $tradeData Trade data from form submission
     * @return array{success: bool, tradeText: string, tradeOfferId: int}
     */
    protected function insertTradeOfferData(int $tradeOfferId, array $tradeData): array
    {
        $tradeText = '';
        $offeringTeamName = $tradeData['offeringTeam'];
        $listeningTeamName = $tradeData['listeningTeam'];

        // Process offering team items
        $switchCounter = $tradeData['switchCounter'];
        for ($k = 0; $k < $switchCounter; $k++) {
            if (($tradeData['check'][$k] ?? null) === "on") {
                $itemResult = $this->insertTradeItem(
                    $tradeOfferId,
                    (int) ($tradeData['index'][$k] ?? '0'),
                    (int) ($tradeData['type'][$k] ?? '0'),
                    $offeringTeamName,
                    $listeningTeamName,
                    $listeningTeamName
                );
                $tradeText .= $itemResult['tradeText'];
            }
        }

        // Process offering team cash
        if ($this->cashHandler->hasCashInTrade($tradeData['userSendsCash'])) {
            $cashResult = $this->insertCashTradeOffer(
                $tradeOfferId,
                $offeringTeamName,
                $listeningTeamName,
                $tradeData['userSendsCash'],
                $listeningTeamName  // Approval should always be the listening team
            );
            $tradeText .= $cashResult['tradeText'];
        }

        // Process receiving team items
        $fieldsCounter = $tradeData['fieldsCounter'];
        for ($k = $switchCounter; $k < $fieldsCounter; $k++) {
            if (($tradeData['check'][$k] ?? null) === "on") {
                $itemResult = $this->insertTradeItem(
                    $tradeOfferId,
                    (int) ($tradeData['index'][$k] ?? '0'),
                    (int) ($tradeData['type'][$k] ?? '0'),
                    $listeningTeamName,
                    $offeringTeamName,
                    $listeningTeamName
                );
                $tradeText .= $itemResult['tradeText'];
            }
        }

        // Process receiving team cash
        if ($this->cashHandler->hasCashInTrade($tradeData['partnerSendsCash'])) {
            $cashResult = $this->insertCashTradeOffer(
                $tradeOfferId,
                $listeningTeamName,
                $offeringTeamName,
                $tradeData['partnerSendsCash'],
                $listeningTeamName  // Approval should always be the listening team
            );
            $tradeText .= $cashResult['tradeText'];
        }

        return [
            'success' => true,
            'tradeText' => $tradeText,
            'tradeOfferId' => $tradeOfferId
        ];
    }

    /**
     * Insert a single trade item (player or pick)
     *
     * Inserts the trade item record and generates descriptive trade text.
     *
     * @param int $tradeOfferId Trade offer ID
     * @param int $itemId Item ID (player PID or pick ID)
     * @param int $assetType Asset type (0=pick, 1=player) from form data
     * @param string $offeringTeamName Offering team name
     * @param string $listeningTeamName Listening team name
     * @param string $approvalTeamName Name of team that needs to approve
     * @return array{tradeText: string}
     */
    protected function insertTradeItem(int $tradeOfferId, int $itemId, int $assetType, string $offeringTeamName, string $listeningTeamName, string $approvalTeamName): array
    {
        $itemType = TradeItemType::fromFormInt($assetType);

        // Use repository with prepared statements
        $this->offerRepository->insertTradeItem(
            $tradeOfferId,
            $itemId,
            $itemType,
            $offeringTeamName,
            $listeningTeamName,
            $approvalTeamName
        );

        $tradeText = "";
        if ($itemType === TradeItemType::DraftPick) {
            $tradeText = $this->getPickTradeText($itemId, $offeringTeamName, $listeningTeamName);
        } else {
            $tradeText = $this->getPlayerTradeText($itemId, $offeringTeamName, $listeningTeamName);
        }

        return ['tradeText' => $tradeText];
    }

    /**
     * Get trade text for a draft pick
     * 
     * Fetches pick details and formats a human-readable trade description.
     * 
     * @param int $pickId Pick ID
     * @param string $offeringTeamName Offering team name
     * @param string $listeningTeamName Listening team name
     * @return string Formatted trade text (e.g., "The Lakers send the Bulls 2024 Round 1 draft pick...")
     */
    protected function getPickTradeText(int $pickId, string $offeringTeamName, string $listeningTeamName): string
    {
        $pickData = $this->assetRepository->getDraftPickById($pickId);

        if ($pickData === null) {
            return '';
        }

        $pickTeam = $pickData['teampick'];
        $pickYear = $pickData['year'];
        $pickRound = $pickData['round'];
        $pickNotes = $pickData['notes'];

        $tradeText = "The $offeringTeamName send the $pickTeam $pickYear Round $pickRound draft pick to the $listeningTeamName.<br>";
        if ($pickNotes !== null) {
            $tradeText .= "<i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $pickNotes . "</i><br>";
        }

        return $tradeText;
    }

    /**
     * Get trade text for a player
     * 
     * Fetches player details and formats a human-readable trade description.
     * 
     * @param int $playerId Player ID
     * @param string $offeringTeamName Offering team name
     * @param string $listeningTeamName Listening team name
     * @return string Formatted trade text (e.g., "The Lakers send PG Michael Jordan...")
     */
    protected function getPlayerTradeText(int $playerId, string $offeringTeamName, string $listeningTeamName): string
    {
        $playerData = $this->assetRepository->getPlayerById($playerId);

        if ($playerData === null) {
            return '';
        }

        $playerName = $playerData['name'];
        $playerPosition = $playerData['pos'];

        return "The $offeringTeamName send $playerPosition $playerName to the $listeningTeamName.<br>";
    }

    /**
     * Insert cash trade offer
     *
     * Records cash consideration in trade_cash table and creates a trade_info record
     * with a composite item ID representing the cash transaction.
     *
     * @param int $tradeOfferId Trade offer ID
     * @param string $offeringTeamName Offering team name
     * @param string $listeningTeamName Listening team name
     * @param array<int, int> $cashAmounts Cash amounts indexed by year (1-6)
     * @param string $approvalTeamName Team that needs to approve (should be listening team)
     * @return array{tradeText: string}
     */
    protected function insertCashTradeOffer(int $tradeOfferId, string $offeringTeamName, string $listeningTeamName, array $cashAmounts, string $approvalTeamName): array
    {
        // Insert cash data
        $this->cashHandler->insertCashTradeData($tradeOfferId, $offeringTeamName, $listeningTeamName, $cashAmounts);

        // Insert trade info record for cash
        $offeringTeamId = $this->commonRepository->getTidFromTeamname($offeringTeamName) ?? 0;
        $listeningTeamId = $this->commonRepository->getTidFromTeamname($listeningTeamName) ?? 0;

        $itemId = (int) ($offeringTeamId . '0' . $listeningTeamId . '0');

        $this->offerRepository->insertTradeItem(
            $tradeOfferId,
            $itemId,
            TradeItemType::Cash,
            $offeringTeamName,
            $listeningTeamName,
            $approvalTeamName
        );

        $tradeText = CashTransactionHandler::formatCashTradeText(
            $cashAmounts,
            $offeringTeamName,
            $listeningTeamName,
            $this->season->endingYear
        );

        return ['tradeText' => $tradeText];
    }

    /**
     * Send trade notification to receiving team
     *
     * Sends Discord DM to the listening team with trade proposal details,
     * including interactive Accept/Decline buttons when a trade offer ID is provided.
     *
     * @param TradeFormData $tradeData Trade data with offeringTeam and listeningTeam keys
     * @param string $tradeText Trade description text
     * @param int $tradeOfferId Trade offer ID for button interaction tracking
     */
    protected function sendTradeNotification(array $tradeData, string $tradeText, int $tradeOfferId): void
    {
        // Skip notification if Discord is not available
        if ($this->discord === null) {
            \Logging\LoggerFactory::getChannel('trade')->warning('Discord notification skipped: Discord class not initialized', ['trade_id' => $tradeOfferId]);
            return;
        }

        try {
            $offeringTeamName = $tradeData['offeringTeam'];
            $listeningTeamName = $tradeData['listeningTeam'];

            $receivingUserDiscordID = $this->discord->getDiscordIDFromTeamname($listeningTeamName);

            if ($receivingUserDiscordID === '' || $receivingUserDiscordID === '0') {
                \Logging\LoggerFactory::getChannel('trade')->warning('Discord notification skipped: no Discord ID for team', ['trade_id' => $tradeOfferId, 'team' => $listeningTeamName]);
                return;
            }

            $cleanTradeText = str_replace(['<br>', '&nbsp;', '<i>', '</i>'], ["\n", " ", "_", "_"], $tradeText);

            Discord::sendTradeDM($receivingUserDiscordID, $tradeOfferId, $offeringTeamName, $cleanTradeText);
        } catch (\Exception $e) {
            \Logging\LoggerFactory::getChannel('trade')->error('Discord notification failed', ['trade_id' => $tradeOfferId, 'error' => $e->getMessage()]);
        }
    }
}