<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradeOfferInterface;

/**
 * TradeOffer - Creates and manages trade offers
 *
 * Handles the creation of trade offers, validation of trade terms,
 * and notification of trade proposals to receiving teams.
 *
 * @see TradeOfferInterface
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type DraftPickRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradeAutocounterRow from \Trading\Contracts\TradingRepositoryInterface
 *
 * @phpstan-type TradeFormData array{offeringTeam: string, listeningTeam: string, switchCounter: int, fieldsCounter: int, check: array<int, string|null>, index: array<int, string>, type: array<int, string>, contract: array<int, string>, userSendsCash: array<int, int>, partnerSendsCash: array<int, int>}
 */
class TradeOffer implements TradeOfferInterface
{
    /** @phpstan-var \mysqli */
    protected object $db;
    protected TradingRepository $repository;
    protected \Services\CommonMysqliRepository $commonRepository;
    protected \Season $season;
    protected CashTransactionHandler $cashHandler;
    protected TradeValidator $validator;
    protected ?\Discord $discord;

    /**
     * @phpstan-param \mysqli $db
     */
    public function __construct(object $db, ?TradingRepository $repository = null)
    {
        $this->db = $db;
        $this->repository = $repository ?? new TradingRepository($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
        $this->season = new \Season($db);
        $this->cashHandler = new CashTransactionHandler($db, $this->repository);
        $this->validator = new TradeValidator($db);

        // Initialize Discord with error handling (may fail if column doesn't exist)
        try {
            $this->discord = new \Discord($db);
        } catch (\Exception $e) {
            // Discord unavailable - will skip notifications
            error_log("Discord initialization failed in TradeOffer: " . $e->getMessage());
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

        // Validate roster limits
        $rosterValidation = $this->validator->validateRosterLimits(
            $tradeData['offeringTeam'],
            $tradeData['listeningTeam'],
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
            $this->sendTradeNotification($tradeData, $result['tradeText']);
        }

        return $result;
    }

    /**
     * Generate a new unique trade offer ID
     * 
     * Queries the autocounter table and increments to get the next available ID.
     * 
     * @return int New trade offer ID
     */
    protected function generateTradeOfferId(): int
    {
        // Get current counter using repository
        $currentCounterRow = $this->repository->getTradeAutocounter();
        $currentCounter = $currentCounterRow !== null ? $currentCounterRow['counter'] : 0;
        $tradeOfferId = $currentCounter + 1;

        // Insert new counter using prepared statement
        $this->repository->insertTradeAutocounter($tradeOfferId);

        return $tradeOfferId;
    }

    /**
     * Calculate salary cap data for both teams
     *
     * Computes current cap totals and amounts being sent/received for both teams,
     * including cash considerations based on current season phase.
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

        // Calculate user team salary data
        $switchCounter = $tradeData['switchCounter'];
        for ($j = 0; $j < $switchCounter; $j++) {
            $isChecked = $tradeData['check'][$j] ?? null;
            $salaryAmount = (int) ($tradeData['contract'][$j] ?? '0');
            $userCurrentSeasonCapTotal += $salaryAmount;

            if ($isChecked === "on") {
                $userCapSentToPartner += $salaryAmount;
            }
        }

        // Calculate partner team salary data
        $fieldsCounter = $tradeData['fieldsCounter'];
        for ($j = $switchCounter; $j < $fieldsCounter; $j++) {
            $isChecked = $tradeData['check'][$j] ?? null;
            $salaryAmount = (int) ($tradeData['contract'][$j] ?? '0');
            $partnerCurrentSeasonCapTotal += $salaryAmount;

            if ($isChecked === "on") {
                $partnerCapSentToUser += $salaryAmount;
            }
        }

        // Add cash considerations
        $cashConsiderations = $this->validator->getCurrentSeasonCashConsiderations(
            $tradeData['userSendsCash'], 
            $tradeData['partnerSendsCash']
        );

        $userCurrentSeasonCapTotal += $cashConsiderations['cashSentToThem'];
        $userCurrentSeasonCapTotal -= $cashConsiderations['cashSentToMe'];
        $partnerCurrentSeasonCapTotal += $cashConsiderations['cashSentToMe'];
        $partnerCurrentSeasonCapTotal -= $cashConsiderations['cashSentToThem'];

        return [
            'userCurrentSeasonCapTotal' => $userCurrentSeasonCapTotal,
            'partnerCurrentSeasonCapTotal' => $partnerCurrentSeasonCapTotal,
            'userCapSentToPartner' => $userCapSentToPartner,
            'partnerCapSentToUser' => $partnerCapSentToUser
        ];
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
     * @param int $assetType Asset type (0=pick, 1=player)
     * @param string $offeringTeamName Offering team name
     * @param string $listeningTeamName Listening team name
     * @param string $approvalTeamName Name of team that needs to approve
     * @return array{tradeText: string}
     */
    protected function insertTradeItem(int $tradeOfferId, int $itemId, int $assetType, string $offeringTeamName, string $listeningTeamName, string $approvalTeamName): array
    {
        // Use repository with prepared statements
        $this->repository->insertTradeItem(
            $tradeOfferId,
            $itemId,
            $assetType,
            $offeringTeamName,
            $listeningTeamName,
            $approvalTeamName
        );

        $tradeText = "";
        if ($assetType === 0) {
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
        $pickData = $this->repository->getDraftPickById($pickId);

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
        $playerData = $this->repository->getPlayerById($playerId);

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

        $this->repository->insertTradeItem(
            $tradeOfferId,
            $itemId,
            'cash',
            $offeringTeamName,
            $listeningTeamName,
            $approvalTeamName
        );

        $cashText = implode(' ', array_filter($cashAmounts, static fn (int $amount): bool => $amount !== 0));
        $tradeText = "The $offeringTeamName send $cashText in cash to the $listeningTeamName.<br>";

        return ['tradeText' => $tradeText];
    }

    /**
     * Send trade notification to receiving team
     *
     * Sends Discord DM to the listening team with trade proposal details.
     *
     * @param TradeFormData $tradeData Trade data with offeringTeam and listeningTeam keys
     * @param string $tradeText Trade description text
     */
    protected function sendTradeNotification(array $tradeData, string $tradeText): void
    {
        // Skip notification if Discord is not available
        if ($this->discord === null) {
            return;
        }

        try {
            $offeringTeamName = $tradeData['offeringTeam'];
            $listeningTeamName = $tradeData['listeningTeam'];

            $offeringUserDiscordID = $this->discord->getDiscordIDFromTeamname($offeringTeamName);
            $receivingUserDiscordID = $this->discord->getDiscordIDFromTeamname($listeningTeamName);

            $cleanTradeText = str_replace(['<br>', '&nbsp;', '<i>', '</i>'], ["\n", " ", "_", "_"], $tradeText);

            $discordDMmessage = 'New trade proposal from <@!' . $offeringUserDiscordID . '>!
' . $cleanTradeText . '
Go here to accept or decline: http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade';

            \Discord::sendDM($receivingUserDiscordID, $discordDMmessage);
        } catch (\Exception $e) {
            // Log error but don't fail the trade offer
            error_log("Discord notification failed in sendTradeNotification: " . $e->getMessage());
        }
    }
}