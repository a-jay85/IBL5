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
    protected $mysqli_db;
    protected \Services\CommonRepository $commonRepository;
    protected \Season $season;
    protected CashTransactionHandler $cashHandler;
    protected \Services\NewsService $newsService;

    public function __construct($db, $mysqli_db = null)
    {
        $this->db = $db;
        $this->mysqli_db = $mysqli_db;
        $this->commonRepository = new \Services\CommonRepository($db);
        $this->season = new \Season($db);
        $this->cashHandler = new CashTransactionHandler($db);
        $this->newsService = new \Services\NewsService($db);
    }

    /**
     * @see TradeProcessorInterface::processTrade()
     */
    public function processTrade(int $offerId): array
    {
        $queryTradeRows = "SELECT * FROM ibl_trade_info WHERE tradeofferid = '$offerId'";
        $resultTradeRows = $this->db->sql_query($queryTradeRows);
        $numTradeRows = $this->db->sql_numrows($resultTradeRows);

        if ($numTradeRows == 0) {
            return [
                'success' => false,
                'error' => 'No trade data found for this offer ID'
            ];
        }

        $storytext = "";
        $i = 0;

        while ($i < $numTradeRows) {
            $itemId = $this->db->sql_result($resultTradeRows, $i, "itemid");
            $itemType = $this->db->sql_result($resultTradeRows, $i, "itemtype");
            $offeringTeamName = $this->db->sql_result($resultTradeRows, $i, "from");
            $listeningTeamName = $this->db->sql_result($resultTradeRows, $i, "to");

            $result = $this->processTradeItem($itemId, $itemType, $offeringTeamName, $listeningTeamName, $offerId);
            
            if ($result['success']) {
                $storytext .= $result['tradeLine'];
            }

            $i++;
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
        $itemId = $this->cashHandler->generateUniquePid($itemId);
        
        // Use prepared statement if mysqli_db is available (preferred)
        if ($this->mysqli_db) {
            $query = "SELECT * FROM ibl_trade_cash WHERE tradeOfferID = ? AND sendingTeam = ?";
            $stmt = $this->mysqli_db->prepare($query);
            $stmt->bind_param('is', $offerId, $offeringTeamName);
            $stmt->execute();
            $result = $stmt->get_result();
            $cashDetails = $result->fetch_assoc();
        } else {
            // Fallback: use mysqli_real_escape_string if available, otherwise addslashes
            if (isset($this->db->db_connect_id) && $this->db->db_connect_id) {
                $escapedTeam = mysqli_real_escape_string($this->db->db_connect_id, $offeringTeamName);
            } else {
                $escapedTeam = addslashes($offeringTeamName);
            }
            $queryCashDetails = "SELECT * FROM ibl_trade_cash WHERE tradeOfferID = $offerId AND sendingTeam = '$escapedTeam'";
            $cashDetails = $this->db->sql_fetchrow($this->db->sql_query($queryCashDetails));
        }

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
        // Use prepared statement if mysqli_db is available (preferred)
        if ($this->mysqli_db) {
            $query = "SELECT * FROM ibl_draft_picks WHERE `pickid` = ?";
            $stmt = $this->mysqli_db->prepare($query);
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $resultj = $stmt->get_result();
        } else {
            $queryj = "SELECT * FROM ibl_draft_picks WHERE `pickid` = '$itemId'";
            $resultj = $this->db->sql_query($queryj);
        }
        
        $tradeLine = "The $offeringTeamName send the " . 
                    $this->db->sql_result($resultj, 0, "year") . " " . 
                    $this->db->sql_result($resultj, 0, "teampick") . " Round " . 
                    $this->db->sql_result($resultj, 0, "round") . " draft pick to the $listeningTeamName.<br>";

        if (isset($this->db->db_connect_id) && $this->db->db_connect_id) {
            $escapedTeam = mysqli_real_escape_string($this->db->db_connect_id, $listeningTeamName);
        } else {
            $escapedTeam = addslashes($listeningTeamName);
        }
        $queryi = 'UPDATE ibl_draft_picks SET `ownerofpick` = "' . $escapedTeam . '" WHERE `pickid` = ' . $itemId . ' LIMIT 1';
        $resulti = $this->db->sql_query($queryi);

        $this->queueTradeQuery($queryi, $tradeLine);

        return [
            'success' => (bool)$resulti,
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
        $listeningTeamId = $this->commonRepository->getTidFromTeamname($listeningTeamName);

        // Use prepared statement if mysqli_db is available (preferred)
        if ($this->mysqli_db) {
            $query = "SELECT * FROM ibl_plr WHERE pid = ?";
            $stmt = $this->mysqli_db->prepare($query);
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $resultk = $stmt->get_result();
        } else {
            $queryk = "SELECT * FROM ibl_plr WHERE pid = '$itemId'";
            $resultk = $this->db->sql_query($queryk);
        }

        $tradeLine = "The $offeringTeamName send " . 
                    $this->db->sql_result($resultk, 0, "pos") . " " . 
                    $this->db->sql_result($resultk, 0, "name") . " to the $listeningTeamName.<br>";

        if (isset($this->db->db_connect_id) && $this->db->db_connect_id) {
            $escapedTeam = mysqli_real_escape_string($this->db->db_connect_id, $listeningTeamName);
        } else {
            $escapedTeam = addslashes($listeningTeamName);
        }
        $queryi = 'UPDATE ibl_plr SET `teamname` = "' . $escapedTeam . '", `tid` = ' . $listeningTeamId . ' WHERE `pid` = ' . $itemId . ' LIMIT 1';
        $resulti = $this->db->sql_query($queryi);

        $this->queueTradeQuery($queryi, $tradeLine);

        return [
            'success' => (bool)$resulti,
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
            // Use prepared statement if mysqli_db is available (preferred)
            if ($this->mysqli_db) {
                $queryInsert = "INSERT INTO ibl_trade_queue (query, tradeline) VALUES (?, ?)";
                $stmt = $this->mysqli_db->prepare($queryInsert);
                $stmt->bind_param('ss', $query, $tradeLine);
                $stmt->execute();
            } else {
                if (isset($this->db->db_connect_id) && $this->db->db_connect_id) {
                    $escapedQuery = mysqli_real_escape_string($this->db->db_connect_id, $query);
                    $escapedLine = mysqli_real_escape_string($this->db->db_connect_id, $tradeLine);
                } else {
                    $escapedQuery = addslashes($query);
                    $escapedLine = addslashes($tradeLine);
                }
                $queryInsert = "INSERT INTO ibl_trade_queue (query, tradeline) VALUES ('$escapedQuery', '$escapedLine')";
                $this->db->sql_query($queryInsert);
            }
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
        // $offerId is an int parameter, so it's safe - but use prepared statements for consistency
        if ($this->mysqli_db) {
            $queryClearInfo = "DELETE FROM ibl_trade_info WHERE `tradeofferid` = ?";
            $stmt = $this->mysqli_db->prepare($queryClearInfo);
            $stmt->bind_param('i', $offerId);
            $stmt->execute();
            
            $queryClearCash = "DELETE FROM ibl_trade_cash WHERE `tradeOfferID` = ?";
            $stmt = $this->mysqli_db->prepare($queryClearCash);
            $stmt->bind_param('i', $offerId);
            $stmt->execute();
        } else {
            $queryClearInfo = "DELETE FROM ibl_trade_info WHERE `tradeofferid` = '$offerId'";
            $this->db->sql_query($queryClearInfo);
            
            $queryClearCash = "DELETE FROM ibl_trade_cash WHERE `tradeOfferID` = '$offerId'";
            $this->db->sql_query($queryClearCash);
        }
    }
}