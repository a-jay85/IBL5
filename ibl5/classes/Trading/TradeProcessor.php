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
    protected $db;
    protected TradingRepository $repository;
    protected \Services\CommonRepository $commonRepository;
    protected \Season $season;
    protected CashTransactionHandler $cashHandler;
    protected \Services\NewsService $newsService;

    public function __construct($db, ?TradingRepository $repository = null)
    {
        $this->db = $db;
        // Extract mysqli connection from legacy $db object for repositories
        $mysqli = $db->db_connect_id ?? $db;
        $this->repository = $repository ?? new TradingRepository($mysqli);
        $this->commonRepository = new \Services\CommonRepository($db);
        $this->season = new \Season($db);
        $this->cashHandler = new CashTransactionHandler($db, $mysqli, $this->repository);
        $this->newsService = new \Services\NewsService($db);
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
        $this->cleanupTradeData($offerId);

        return [
            'success' => true,
            'storytext' => $storytext,
            'storytitle' => $storytitle
        ];
    }

    /**
     * Process a single trade item (player, pick, or cash)
     * @param mixed $itemId Item ID
     * @param mixed $itemType Item type (1=player, 0=pick, 'cash'=cash)
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @param int $offerId Trade offer ID
     * @return array Result with success status and trade line
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
     * @param int $itemId Item ID
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @param int $offerId Trade offer ID
     * @return array Result
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
     * @param int $itemId Pick ID
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @return array Result
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
        $queryi = 'UPDATE ibl_draft_picks SET `ownerofpick` = "' . addslashes($listeningTeamName) . '" WHERE `pickid` = ' . $itemId . ' LIMIT 1';

        $this->queueTradeQuery($queryi, $tradeLine);

        return [
            'success' => ($affectedRows > 0),
            'tradeLine' => $tradeLine
        ];
    }

    /**
     * Process player transfer
     * @param int $itemId Player ID
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @return array Result
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
        $queryi = 'UPDATE ibl_plr SET `teamname` = "' . addslashes($listeningTeamName) . '", `tid` = ' . $listeningTeamId . ' WHERE `pid` = ' . $itemId . ' LIMIT 1';

        $this->queueTradeQuery($queryi, $tradeLine);

        return [
            'success' => ($affectedRows > 0),
            'tradeLine' => $tradeLine
        ];
    }

    /**
     * Queue trade query for later execution if in certain season phases
     * @param string $query SQL query
     * @param string $tradeLine Trade description
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
     * @param string $storytitle Story title
     * @param string $storytext Story text
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
     * @param string $offeringTeamName Offering team
     * @param string $listeningTeamName Listening team
     * @param string $storytext Story text
     */
    protected function sendNotifications($offeringTeamName, $listeningTeamName, $storytext)
    {
        $fromDiscordId = \Discord::getDiscordIDFromTeamname($this->db, $offeringTeamName);
        $toDiscordId = \Discord::getDiscordIDFromTeamname($this->db, $listeningTeamName);
        $discordText = "<@!$fromDiscordId> and <@!$toDiscordId> agreed to a trade:<br>" . $storytext;
        
        \Discord::postToChannel('#trades', $discordText);
        \Discord::postToChannel('#general-chat', $storytext);
    }

    /**
     * Clean up trade data after processing
     * @param int $offerId Trade offer ID
     */
    protected function cleanupTradeData($offerId)
    {
        $this->repository->deleteTradeInfoByOfferId($offerId);
        $this->repository->deleteTradeCashByOfferId($offerId);
    }
}