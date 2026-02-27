<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradeProcessorInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\Contracts\TradeExecutionRepositoryInterface;

/**
 * TradeProcessor - Executes trades
 *
 * Handles the complete trade execution process including player transfers,
 * draft pick transfers, cash transactions, news creation, and notifications.
 *
 * @see TradeProcessorInterface
 *
 * @phpstan-import-type TradeInfoRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradeCashRow from \Trading\Contracts\TradeCashRepositoryInterface
 * @phpstan-import-type DraftPickRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class TradeProcessor implements TradeProcessorInterface
{
    protected \mysqli $db;
    protected TradingRepository $repository;
    protected TradeCashRepositoryInterface $cashRepository;
    protected TradeExecutionRepositoryInterface $executionRepository;
    protected \Services\CommonMysqliRepository $commonRepository;
    protected \Season $season;
    protected CashTransactionHandler $cashHandler;
    protected \Services\NewsService $newsService;
    protected ?\Discord $discord;

    public function __construct(\mysqli $db, ?TradingRepository $repository = null)
    {
        $this->db = $db;
        $this->repository = $repository ?? new TradingRepository($db);
        $this->cashRepository = new TradeCashRepository($db);
        $this->executionRepository = new TradeExecutionRepository($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
        $this->season = new \Season($db);
        $this->cashHandler = new CashTransactionHandler($db, $this->repository, $this->cashRepository);
        $this->newsService = new \Services\NewsService($db);

        // Initialize Discord with error handling
        try {
            $this->discord = new \Discord($db);
        } catch (\Exception $e) {
            // Discord unavailable - will skip notifications
            error_log("Discord initialization failed in TradeProcessor: " . $e->getMessage());
            $this->discord = null;
        }
    }

    /**
     * @see TradeProcessorInterface::processTrade()
     */
    public function processTrade(int $offerId): array
    {
        $this->db->begin_transaction();

        try {
            // Acquire exclusive row-level lock to prevent double-processing
            $tradeRows = $this->repository->getTradesByOfferIdForUpdate($offerId);

            if ($tradeRows === []) {
                $this->db->rollback();
                return [
                    'success' => false,
                    'error' => 'No trade data found for this offer ID'
                ];
            }

            $storytext = "";
            $offeringTeamName = '';
            $listeningTeamName = '';

            foreach ($tradeRows as $tradeRow) {
                $itemId = $tradeRow['itemid'];
                $itemType = $tradeRow['itemtype'];
                $offeringTeamName = $tradeRow['from'];
                $listeningTeamName = $tradeRow['to'];

                $result = $this->processTradeItem($itemId, $itemType, $offeringTeamName, $listeningTeamName, $offerId);
                $storytext .= $result['tradeLine'];
            }

            // Create news story and notifications
            $storytitle = "$offeringTeamName and $listeningTeamName make a trade.";

            $this->createNewsStory($storytitle, $storytext);
            $this->sendNotifications($offeringTeamName, $listeningTeamName, $storytext);
            $this->repository->deleteTradeOffer($offerId);

            $this->db->commit();

            return [
                'success' => true,
                'storytext' => $storytext,
                'storytitle' => $storytitle
            ];
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Process a single trade item (player, pick, or cash)
     *
     * Routes the item to the appropriate handler based on type.
     *
     * @param int $itemId Item ID (int for player/pick, composite int for cash)
     * @param string $itemType Item type ('1'=player, '0'=pick, 'cash'=cash)
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @param int $offerId Trade offer ID
     * @return array{success: bool, tradeLine: string}
     */
    protected function processTradeItem(int $itemId, string $itemType, string $offeringTeamName, string $listeningTeamName, int $offerId): array
    {
        if ($itemType === 'cash') {
            return $this->processCashTransaction($itemId, $offeringTeamName, $listeningTeamName, $offerId);
        } elseif ($itemType === '0') {
            return $this->processDraftPick($itemId, $offeringTeamName, $listeningTeamName);
        } elseif ($itemType === '1') {
            return $this->processPlayer($itemId, $offeringTeamName, $listeningTeamName);
        }

        return ['success' => false, 'tradeLine' => ''];
    }

    /**
     * Process cash transaction
     *
     * Retrieves cash details from database and creates cash transaction records
     * via CashTransactionHandler.
     *
     * @param int $itemId Item ID for the transaction
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @param int $offerId Trade offer ID
     * @return array{success: bool, tradeLine: string}
     */
    protected function processCashTransaction(int $itemId, string $offeringTeamName, string $listeningTeamName, int $offerId): array
    {
        $uniquePid = $this->cashHandler->generateUniquePid($itemId);

        // Get cash details from cash repository
        $cashDetails = $this->cashRepository->getCashTransactionByOffer($offerId, $offeringTeamName);

        if ($cashDetails === null) {
            return ['success' => false, 'tradeLine' => ''];
        }

        $cashYear = [
            1 => $cashDetails['cy1'] ?? 0,
            2 => $cashDetails['cy2'] ?? 0,
            3 => $cashDetails['cy3'] ?? 0,
            4 => $cashDetails['cy4'] ?? 0,
            5 => $cashDetails['cy5'] ?? 0,
            6 => $cashDetails['cy6'] ?? 0,
        ];

        return $this->cashHandler->createCashTransaction($uniquePid, $offeringTeamName, $listeningTeamName, $cashYear, $this->season->endingYear);
    }

    /**
     * Process draft pick transfer
     *
     * Updates pick ownership and queues the operation if during certain season phases.
     *
     * @param int $itemId Pick ID
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @return array{success: bool, tradeLine: string}
     */
    protected function processDraftPick(int $itemId, string $offeringTeamName, string $listeningTeamName): array
    {
        // Get pick details from repository
        $pickData = $this->repository->getDraftPickById($itemId);

        if ($pickData === null) {
            return ['success' => false, 'tradeLine' => ''];
        }

        $tradeLine = "The $offeringTeamName send the " .
                    $pickData['year'] . " " .
                    $pickData['teampick'] . " Round " .
                    $pickData['round'] . " draft pick to the $listeningTeamName.<br>";

        // Resolve team ID for the new owner
        $listeningTeamId = $this->commonRepository->getTidFromTeamname($listeningTeamName) ?? 0;

        // Update pick ownership using repository
        $affectedRows = $this->repository->updateDraftPickOwnerById($itemId, $listeningTeamName, $listeningTeamId);

        // Queue structured data for deferred execution during certain season phases
        $this->queuePickTransfer($itemId, $listeningTeamName, $listeningTeamId, $tradeLine);

        return [
            'success' => ($affectedRows > 0),
            'tradeLine' => $tradeLine
        ];
    }

    /**
     * Process player transfer
     *
     * Updates player's team assignment and queues the operation if during certain season phases.
     *
     * @param int $itemId Player ID
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @return array{success: bool, tradeLine: string}
     */
    protected function processPlayer(int $itemId, string $offeringTeamName, string $listeningTeamName): array
    {
        $listeningTeamId = $this->commonRepository->getTidFromTeamname($listeningTeamName) ?? 0;

        // Get full player data from repository
        $playerData = $this->repository->getPlayerById($itemId);

        if ($playerData === null) {
            return ['success' => false, 'tradeLine' => ''];
        }

        $tradeLine = "The $offeringTeamName send " .
                    $playerData['pos'] . " " .
                    $playerData['name'] . " to the $listeningTeamName.<br>";

        // Update player team using repository
        $affectedRows = $this->repository->updatePlayerTeam($itemId, $listeningTeamName, $listeningTeamId);

        // Queue structured data for deferred execution during certain season phases
        $this->queuePlayerTransfer($itemId, $listeningTeamName, $listeningTeamId, $tradeLine);

        return [
            'success' => ($affectedRows > 0),
            'tradeLine' => $tradeLine
        ];
    }

    /**
     * Check if we should queue trades for later execution
     *
     * During Playoffs, Draft, or Free Agency phases, trades are queued
     * instead of executed immediately to prevent roster conflicts.
     *
     * @return bool True if in a phase that requires queueing
     */
    protected function shouldQueueTrades(): bool
    {
        return $this->season->phase === "Playoffs"
            || $this->season->phase === "Draft"
            || $this->season->phase === "Free Agency";
    }

    /**
     * Queue player transfer for later execution if in certain season phases
     *
     * @param int $playerId Player ID
     * @param string $teamName New team name
     * @param int $teamId New team ID
     * @param string $tradeLine Trade description for tracking
     * @return void
     */
    protected function queuePlayerTransfer(int $playerId, string $teamName, int $teamId, string $tradeLine): void
    {
        if ($this->shouldQueueTrades()) {
            $params = [
                'player_id' => $playerId,
                'team_name' => $teamName,
                'team_id' => $teamId,
            ];
            $this->executionRepository->insertTradeQueue('player_transfer', $params, $tradeLine);
        }
    }

    /**
     * Queue pick transfer for later execution if in certain season phases
     *
     * @param int $pickId Pick ID
     * @param string $newOwner New owner team name
     * @param int $newOwnerId New owner team ID
     * @param string $tradeLine Trade description for tracking
     * @return void
     */
    protected function queuePickTransfer(int $pickId, string $newOwner, int $newOwnerId, string $tradeLine): void
    {
        if ($this->shouldQueueTrades()) {
            $params = [
                'pick_id' => $pickId,
                'new_owner' => $newOwner,
                'new_owner_id' => $newOwnerId,
            ];
            $this->executionRepository->insertTradeQueue('pick_transfer', $params, $tradeLine);
        }
    }

    /**
     * Create news story for the trade
     *
     * Creates a news story with category ID 2 (Trade News) and topic ID 31 (IBL News).
     * Also sends email notification in production.
     *
     * @param string $storytitle Story title
     * @param string $storytext Story text with full trade details
     * @return void
     */
    protected function createNewsStory(string $storytitle, string $storytext): void
    {
        // Category ID 2 is typically 'Trade News'
        // Topic ID 31 is typically 'IBL News' or general league news
        $categoryID = 2;
        $topicID = 31;

        // NewsService handles escaping internally, so pass raw strings
        $this->newsService->createNewsStory($categoryID, $topicID, $storytitle, $storytext);

        // Send email notification
        \Mail\MailService::fromConfig()->send('ibldepthcharts@gmail.com', $storytitle, $storytext, 'trades@iblhoops.net');
    }

    /**
     * Send notifications (Discord, email) for the trade
     *
     * Posts trade announcement to Discord #trades and #general-chat channels.
     *
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @param string $storytext Full story text with trade details
     * @return void
     */
    protected function sendNotifications(string $offeringTeamName, string $listeningTeamName, string $storytext): void
    {
        // Skip notifications if Discord is not available
        if ($this->discord === null) {
            return;
        }

        try {
            $fromDiscordId = $this->discord->getDiscordIDFromTeamname($offeringTeamName);
            $toDiscordId = $this->discord->getDiscordIDFromTeamname($listeningTeamName);

            // Build Discord mention text only if both IDs exist
            if ($fromDiscordId !== '' && $toDiscordId !== '') {
                $discordText = "<@!$fromDiscordId> and <@!$toDiscordId> agreed to a trade:\n" . $storytext;
            } else {
                $discordText = "$offeringTeamName and $listeningTeamName agreed to a trade:\n" . $storytext;
            }

            \Discord::postToChannel('#trades', $discordText);

            $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
            if ($serverName !== 'localhost' && $serverName !== '127.0.0.1') {
                \Discord::postToChannel('#general-chat', $storytext);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the trade
            error_log('Discord notification failed: ' . $e->getMessage());
        }
    }
}
