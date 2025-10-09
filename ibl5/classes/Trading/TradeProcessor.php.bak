<?php

class Trading_TradeProcessor
{
    protected $db;
    protected $sharedFunctions;
    protected $season;
    protected $cashHandler;

    public function __construct($db)
    {
        $this->db = $db;
        $this->sharedFunctions = new Shared($db);
        $this->season = new Season($db);
        $this->cashHandler = new Trading_CashTransactionHandler($db);
    }

    /**
     * Process a complete trade acceptance
     * @param int $offerId Trade offer ID
     * @return array Result with success status and story text
     */
    public function processTrade($offerId)
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
            $sendingTeamName = $this->db->sql_result($resultTradeRows, $i, "from");
            $receivingTeamName = $this->db->sql_result($resultTradeRows, $i, "to");

            $result = $this->processTradeItem($itemId, $itemType, $sendingTeamName, $receivingTeamName, $offerId);
            
            if ($result['success']) {
                $storytext .= $result['tradeLine'];
            }

            $i++;
        }

        // Create news story and notifications
        $timestamp = date('Y-m-d H:i:s', time());
        $storytitle = "$sendingTeamName and $receivingTeamName make a trade.";
        
        $this->createNewsStory($storytitle, $storytext, $timestamp);
        $this->sendNotifications($sendingTeamName, $receivingTeamName, $storytext);
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
     * @param string $sendingTeamName From team
     * @param string $receivingTeamName To team
     * @param int $offerId Trade offer ID
     * @return array Result with success status and trade line
     */
    protected function processTradeItem($itemId, $itemType, $sendingTeamName, $receivingTeamName, $offerId)
    {
        if ($itemType == 'cash') {
            return $this->processCashTransaction($itemId, $sendingTeamName, $receivingTeamName, $offerId);
        } elseif ($itemType == 0) {
            return $this->processDraftPick($itemId, $sendingTeamName, $receivingTeamName);
        } elseif ($itemType == 1) {
            return $this->processPlayer($itemId, $sendingTeamName, $receivingTeamName);
        }

        return ['success' => false, 'tradeLine' => ''];
    }

    /**
     * Process cash transaction
     * @param int $itemId Item ID
     * @param string $sendingTeamName From team
     * @param string $receivingTeamName To team
     * @param int $offerId Trade offer ID
     * @return array Result
     */
    protected function processCashTransaction($itemId, $sendingTeamName, $receivingTeamName, $offerId)
    {
        $itemId = $this->cashHandler->generateUniquePid($itemId);
        
        $queryCashDetails = "SELECT * FROM ibl_trade_cash WHERE tradeOfferID = $offerId AND sendingTeam = '$sendingTeamName'";
        $cashDetails = $this->db->sql_fetchrow($this->db->sql_query($queryCashDetails));

        $cashYear = [
            1 => $cashDetails['cy1'],
            2 => $cashDetails['cy2'],
            3 => $cashDetails['cy3'],
            4 => $cashDetails['cy4'],
            5 => $cashDetails['cy5'],
            6 => $cashDetails['cy6']
        ];

        return $this->cashHandler->createCashTransaction($itemId, $sendingTeamName, $receivingTeamName, $cashYear);
    }

    /**
     * Process draft pick transfer
     * @param int $itemId Pick ID
     * @param string $sendingTeamName From team
     * @param string $receivingTeamName To team
     * @return array Result
     */
    protected function processDraftPick($itemId, $sendingTeamName, $receivingTeamName)
    {
        $queryj = "SELECT * FROM ibl_draft_picks WHERE `pickid` = '$itemId'";
        $resultj = $this->db->sql_query($queryj);
        
        $tradeLine = "The $sendingTeamName send the " . 
                    $this->db->sql_result($resultj, 0, "year") . " " . 
                    $this->db->sql_result($resultj, 0, "teampick") . " Round " . 
                    $this->db->sql_result($resultj, 0, "round") . " draft pick to the $receivingTeamName.<br>";

        $queryi = 'UPDATE ibl_draft_picks SET `ownerofpick` = "' . $receivingTeamName . '" WHERE `pickid` = ' . $itemId . ' LIMIT 1';
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
     * @param string $sendingTeamName From team
     * @param string $receivingTeamName To team
     * @return array Result
     */
    protected function processPlayer($itemId, $sendingTeamName, $receivingTeamName)
    {
        $receivingTeamId = $this->sharedFunctions->getTidFromTeamname($receivingTeamName);

        $queryk = "SELECT * FROM ibl_plr WHERE pid = '$itemId'";
        $resultk = $this->db->sql_query($queryk);

        $tradeLine = "The $sendingTeamName send " . 
                    $this->db->sql_result($resultk, 0, "pos") . " " . 
                    $this->db->sql_result($resultk, 0, "name") . " to the $receivingTeamName.<br>";

        $queryi = 'UPDATE ibl_plr SET `teamname` = "' . $receivingTeamName . '", `tid` = ' . $receivingTeamId . ' WHERE `pid` = ' . $itemId . ' LIMIT 1';
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
            $queryInsert = "INSERT INTO ibl_trade_queue (query, tradeline) VALUES ('$query', '$tradeLine')";
            $this->db->sql_query($queryInsert);
        }
    }

    /**
     * Create news story for the trade
     * @param string $storytitle Story title
     * @param string $storytext Story text
     * @param string $timestamp Timestamp
     */
    protected function createNewsStory($storytitle, $storytext, $timestamp)
    {
        $querystor = "INSERT INTO nuke_stories
                    (catid,
                     aid,
                     title,
                     time,
                     hometext,
                     topic,
                     informant,
                     counter,
                     alanguage)
        VALUES      ('2',
                     'Associated Press',
                     '$storytitle',
                     '$timestamp',
                     '$storytext',
                     '31',
                     'Associated Press',
                     '0',
                     'english')";
        
        $resultstor = $this->db->sql_query($querystor);
        
        if (isset($resultstor) && $_SERVER['SERVER_NAME'] != "localhost") {
            $recipient = 'ibldepthcharts@gmail.com';
            mail($recipient, $storytitle, $storytext, "From: trades@iblhoops.net");
        }
    }

    /**
     * Send notifications (Discord, email) for the trade
     * @param string $sendingTeamName From team
     * @param string $receivingTeamName To team
     * @param string $storytext Story text
     */
    protected function sendNotifications($sendingTeamName, $receivingTeamName, $storytext)
    {
        $fromDiscordId = Discord::getDiscordIDFromTeamname($this->db, $sendingTeamName);
        $toDiscordId = Discord::getDiscordIDFromTeamname($this->db, $receivingTeamName);
        $discordText = "<@!$fromDiscordId> and <@!$toDiscordId> agreed to a trade:<br>" . $storytext;
        
        Discord::postToChannel('#trades', $discordText);
    }

    /**
     * Clean up trade data after processing
     * @param int $offerId Trade offer ID
     */
    protected function cleanupTradeData($offerId)
    {
        $queryClearInfo = "DELETE FROM ibl_trade_info WHERE `tradeofferid` = '$offerId'";
        $this->db->sql_query($queryClearInfo);
        
        $queryClearCash = "DELETE FROM ibl_trade_cash WHERE `tradeOfferID` = '$offerId'";
        $this->db->sql_query($queryClearCash);
    }
}