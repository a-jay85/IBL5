<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradeProcessorInterface;

/**
 * TradeProcessor - Executes trades
 *
 * Handles the complete trade execution process including player transfers,
 * draft pick transfers, cash transactions, news creation, and notifications.
 * 
 * @see TradeProcessorInterface
 */
class TradeProcessor implements TradeProcessorInterface
{
    protected object $db;
    protected TradingRepository $repository;
    protected \Services\CommonMysqliRepository $commonRepository;
    protected \Season $season;
    protected CashTransactionHandler $cashHandler;
    protected \Services\NewsService $newsService;
    protected \Discord $discord;

    public function __construct(object $db, ?TradingRepository $repository = null)
    {
        $this->db = $db;
        $this->repository = $repository ?? new TradingRepository($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
        $this->season = new \Season($db);
        $this->cashHandler = new CashTransactionHandler($db, $this->repository);
        $this->newsService = new \Services\NewsService($db);
        $this->discord = new \Discord($db);
    }

    /**
     * @see TradeProcessorInterface::processTrade()
     */
    public function processTrade(int $offerId): array
    {
        $tradeRows = $this->repository->getTradesByOfferId($offerId);

        if (empty($tradeRows)) {
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

            if ($result['success']) {
                $storytext .= $result['tradeLine'];
            }
        }

        // Create news story and notifications
        $storytitle = "$offeringTeamName and $listeningTeamName make a trade.";
        
        $this->createNewsStory($storytitle, $storytext);
        $this->sendNotifications($offeringTeamName, $listeningTeamName, $storytext);
        $this->repository->deleteTradeInfoByOfferId($offerId);
        $this->repository->deleteTradeCashByOfferId($offerId);

        return [
            'success' => true,
            'storytext' => $storytext,
            'storytitle' => $storytitle
        ];
    }

    /**
     * Process a single trade item (player, pick, or cash)
     * 
     * Routes the item to the appropriate handler based on type.
     * 
     * @param mixed $itemId Item ID (int for player/pick, composite string for cash)
     * @param mixed $itemType Item type (1=player, 0=pick, 'cash'=cash)
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @param int $offerId Trade offer ID
     * @return array Result with keys: success (bool), tradeLine (string)
     */
    protected function processTradeItem($itemId, $itemType, $offeringTeamName, $listeningTeamName, $offerId)
    {
        if ($itemType == 'cash') {
            return $this->processCashTransaction($itemId, $offeringTeamName, $listeningTeamName, $offerId);
        } elseif ($itemType == 0) {
            return $this->processDraftPick($itemId, $offeringTeamName, $listeningTeamName);
        } elseif ($itemType == 1) {
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
     * @return array Result with keys: success (bool), tradeLine (string)
     */
    protected function processCashTransaction($itemId, $offeringTeamName, $listeningTeamName, $offerId)
    {
        $itemId = $this->cashHandler->generateUniquePid((int) $itemId);
        
        // Get cash details from repository
        $cashDetails = $this->repository->getCashTransactionByOffer($offerId, $offeringTeamName);

        $cashYear = [
            1 => $cashDetails['cy1'],
            2 => $cashDetails['cy2'],
            3 => $cashDetails['cy3'],
            4 => $cashDetails['cy4'],
            5 => $cashDetails['cy5'],
            6 => $cashDetails['cy6']
        ];

        return $this->cashHandler->createCashTransaction($itemId, $offeringTeamName, $listeningTeamName, $cashYear);
    }

    /**
     * Process draft pick transfer
     * 
     * Updates pick ownership and queues the query if during certain season phases.
     * 
     * @param int $itemId Pick ID
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @return array Result with keys: success (bool), tradeLine (string)
     */
    protected function processDraftPick($itemId, $offeringTeamName, $listeningTeamName)
    {
        $itemId = (int) $itemId;
        
        // Get pick details from repository
        $pickData = $this->repository->getDraftPickById($itemId);
        
        if (!$pickData) {
            return ['success' => false, 'tradeLine' => ''];
        }
        
        $tradeLine = "The $offeringTeamName send the " . 
                    $pickData['year'] . " " . 
                    $pickData['teampick'] . " Round " . 
                    $pickData['round'] . " draft pick to the $listeningTeamName.<br>";

        // Update pick ownership using repository
        $affectedRows = $this->repository->updateDraftPickOwnerById($itemId, $listeningTeamName);
        
        // Build query string for queue (if needed in certain phases)
        // Note: Query is built for queue storage only - actual execution uses prepared statements in repository
        if (method_exists($this->db, 'real_escape_string')) {
            $escapedTeamName = $this->db->real_escape_string($listeningTeamName);
        } else {
            $escapedTeamName = \Services\DatabaseService::escapeString($this->db, $listeningTeamName);
        }
        $queryi = 'UPDATE ibl_draft_picks SET `ownerofpick` = "' . $escapedTeamName . '" WHERE `pickid` = ' . $itemId . ' LIMIT 1';

        $this->queueTradeQuery($queryi, $tradeLine);

        return [
            'success' => ($affectedRows > 0),
            'tradeLine' => $tradeLine
        ];
    }

    /**
     * Process player transfer
     * 
     * Updates player's team assignment and queues the query if during certain season phases.
     * 
     * @param int $itemId Player ID
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @return array Result with keys: success (bool), tradeLine (string)
     */
    protected function processPlayer($itemId, $offeringTeamName, $listeningTeamName)
    {
        $itemId = (int) $itemId;
        $listeningTeamId = $this->commonRepository->getTidFromTeamname($listeningTeamName);

        // Get full player data from repository
        $playerData = $this->repository->getPlayerById($itemId);

        if (!$playerData) {
            return ['success' => false, 'tradeLine' => ''];
        }

        $tradeLine = "The $offeringTeamName send " . 
                    $playerData['pos'] . " " . 
                    $playerData['name'] . " to the $listeningTeamName.<br>";

        // Update player team using repository
        $affectedRows = $this->repository->updatePlayerTeam($itemId, $listeningTeamName, $listeningTeamId);
        
        // Build query string for queue (if needed in certain phases)
        // Note: Query is built for queue storage only - actual execution uses prepared statements in repository
        if (method_exists($this->db, 'real_escape_string')) {
            $escapedTeamName = $this->db->real_escape_string($listeningTeamName);
        } else {
            $escapedTeamName = \Services\DatabaseService::escapeString($this->db, $listeningTeamName);
        }
        $queryi = 'UPDATE ibl_plr SET `teamname` = "' . $escapedTeamName . '", `tid` = ' . $listeningTeamId . ' WHERE `pid` = ' . $itemId . ' LIMIT 1';

        $this->queueTradeQuery($queryi, $tradeLine);

        return [
            'success' => ($affectedRows > 0),
            'tradeLine' => $tradeLine
        ];
    }

    /**
     * Queue trade query for later execution if in certain season phases
     * 
     * During Playoffs, Draft, or Free Agency phases, queries are queued
     * instead of executed immediately to prevent roster conflicts.
     * 
     * @param string $query SQL query to execute later
     * @param string $tradeLine Trade description for tracking
     * @return void
     */
    protected function queueTradeQuery($query, $tradeLine)
    {
        if (
            $this->season->phase == "Playoffs"
            || $this->season->phase == "Draft"
            || $this->season->phase == "Free Agency"
        ) {
            $this->repository->insertTradeQueue($query, $tradeLine);
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
    protected function createNewsStory($storytitle, $storytext)
    {
        // Category ID 2 is typically 'Trade News'
        // Topic ID 31 is typically 'IBL News' or general league news
        $categoryID = 2;
        $topicID = 31;
        
        // NewsService handles escaping internally, so pass raw strings
        $this->newsService->createNewsStory($categoryID, $topicID, $storytitle, $storytext);
        
        // Send email notification
        if ($_SERVER['SERVER_NAME'] != "localhost") {
            $recipient = 'ibldepthcharts@gmail.com';
            mail($recipient, $storytitle, $storytext, "From: trades@iblhoops.net");
        }
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
    protected function sendNotifications($offeringTeamName, $listeningTeamName, $storytext)
    {
        $fromDiscordId = $this->discord->getDiscordIDFromTeamname($offeringTeamName);
        $toDiscordId = $this->discord->getDiscordIDFromTeamname($listeningTeamName);
        
        // Build Discord mention text only if both IDs exist
        if (!empty($fromDiscordId) && !empty($toDiscordId)) {
            $discordText = "<@!$fromDiscordId> and <@!$toDiscordId> agreed to a trade:\n" . $storytext;
        } else {
            $discordText = "$offeringTeamName and $listeningTeamName agreed to a trade:\n" . $storytext;
        }

        try {
            \Discord::postToChannel('#trades', $discordText);
            \Discord::postToChannel('#general-chat', $storytext);
        } catch (\Exception $e) {
            // Log the error but don't fail the trade
            error_log('Discord notification failed: ' . $e->getMessage());
        }
    }
}