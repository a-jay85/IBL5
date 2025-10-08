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
            $fromTeam = $this->db->sql_result($resultTradeRows, $i, "from");
            $toTeam = $this->db->sql_result($resultTradeRows, $i, "to");

            $result = $this->processTradeItem($itemId, $itemType, $fromTeam, $toTeam, $offerId);
            
            if ($result['success']) {
                $storytext .= $result['tradeLine'];
            }

            $i++;
        }

        // Create news story and notifications
        $timestamp = date('Y-m-d H:i:s', time());
        $storytitle = "$fromTeam and $toTeam make a trade.";
        
        $this->createNewsStory($storytitle, $storytext, $timestamp);
        $this->sendNotifications($fromTeam, $toTeam, $storytext);
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
     * @param string $fromTeam From team
     * @param string $toTeam To team
     * @param int $offerId Trade offer ID
     * @return array Result with success status and trade line
     */
    protected function processTradeItem($itemId, $itemType, $fromTeam, $toTeam, $offerId)
    {
        if ($itemType == 'cash') {
            return $this->processCashTransaction($itemId, $fromTeam, $toTeam, $offerId);
        } elseif ($itemType == 0) {
            return $this->processDraftPick($itemId, $fromTeam, $toTeam);
        } elseif ($itemType == 1) {
            return $this->processPlayer($itemId, $fromTeam, $toTeam);
        }

        return ['success' => false, 'tradeLine' => ''];
    }

    /**
     * Process cash transaction
     * @param int $itemId Item ID
     * @param string $fromTeam From team
     * @param string $toTeam To team
     * @param int $offerId Trade offer ID
     * @return array Result
     */
    protected function processCashTransaction($itemId, $fromTeam, $toTeam, $offerId)
    {
        $itemId = $this->cashHandler->generateUniquePid($itemId);
        
        $queryCashDetails = "SELECT * FROM ibl_trade_cash WHERE tradeOfferID = $offerId AND sendingTeam = '$fromTeam'";
        $cashDetails = $this->db->sql_fetchrow($this->db->sql_query($queryCashDetails));

        $cashYear = [
            1 => $cashDetails['cy1'],
            2 => $cashDetails['cy2'],
            3 => $cashDetails['cy3'],
            4 => $cashDetails['cy4'],
            5 => $cashDetails['cy5'],
            6 => $cashDetails['cy6']
        ];

        return $this->cashHandler->createCashTransaction($itemId, $fromTeam, $toTeam, $cashYear);
    }

    /**
     * Process draft pick transfer
     * @param int $itemId Pick ID
     * @param string $fromTeam From team
     * @param string $toTeam To team
     * @return array Result
     */
    protected function processDraftPick($itemId, $fromTeam, $toTeam)
    {
        $queryj = "SELECT * FROM ibl_draft_picks WHERE `pickid` = '$itemId'";
        $resultj = $this->db->sql_query($queryj);
        
        $tradeLine = "The $fromTeam send the " . 
                    $this->db->sql_result($resultj, 0, "year") . " " . 
                    $this->db->sql_result($resultj, 0, "teampick") . " Round " . 
                    $this->db->sql_result($resultj, 0, "round") . " draft pick to the $toTeam.<br>";

        $queryi = 'UPDATE ibl_draft_picks SET `ownerofpick` = "' . $toTeam . '" WHERE `pickid` = ' . $itemId . ' LIMIT 1';
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
     * @param string $fromTeam From team
     * @param string $toTeam To team
     * @return array Result
     */
    protected function processPlayer($itemId, $fromTeam, $toTeam)
    {
        $receivingTeamId = $this->sharedFunctions->getTidFromTeamname($toTeam);

        $queryk = "SELECT * FROM ibl_plr WHERE pid = '$itemId'";
        $resultk = $this->db->sql_query($queryk);

        $tradeLine = "The $fromTeam send " . 
                    $this->db->sql_result($resultk, 0, "pos") . " " . 
                    $this->db->sql_result($resultk, 0, "name") . " to the $toTeam.<br>";

        $queryi = 'UPDATE ibl_plr SET `teamname` = "' . $toTeam . '", `tid` = ' . $receivingTeamId . ' WHERE `pid` = ' . $itemId . ' LIMIT 1';
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
     * @param string $fromTeam From team
     * @param string $toTeam To team
     * @param string $storytext Story text
     */
    protected function sendNotifications($fromTeam, $toTeam, $storytext)
    {
        $fromDiscordId = Discord::getDiscordIDFromTeamname($this->db, $fromTeam);
        $toDiscordId = Discord::getDiscordIDFromTeamname($this->db, $toTeam);
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