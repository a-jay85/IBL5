<?php

class Trading_TradeOffer
{
    protected $db;
    protected $sharedFunctions;
    protected $season;
    protected $cashHandler;
    protected $validator;

    public function __construct($db)
    {
        $this->db = $db;
        $this->sharedFunctions = new Shared($db);
        $this->season = new Season($db);
        $this->cashHandler = new Trading_CashTransactionHandler($db);
        $this->validator = new Trading_TradeValidator($db);
    }

    /**
     * Create a new trade offer
     * @param array $tradeData Trade data from form submission
     * @return array Result with success status and any errors
     */
    public function createTradeOffer($tradeData)
    {
        // Generate new trade offer ID
        $tradeOfferId = $this->generateTradeOfferId();
        
        // Validate cash amounts
        $cashValidation = $this->validator->validateMinimumCashAmounts(
            $tradeData['userSendsCash'], 
            $tradeData['partnerSendsCash']
        );
        
        if (!$cashValidation['valid']) {
            return ['success' => false, 'error' => $cashValidation['error']];
        }

        // Calculate and validate salary caps
        $capData = $this->calculateSalaryCapData($tradeData);
        $capValidation = $this->validator->validateSalaryCaps($capData);
        
        if (!$capValidation['valid']) {
            return [
                'success' => false, 
                'errors' => $capValidation['errors'],
                'capData' => $capValidation
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
     * @return int New trade offer ID
     */
    protected function generateTradeOfferId()
    {
        $query0 = "SELECT * FROM ibl_trade_autocounter ORDER BY `counter` DESC";
        $result0 = $this->db->sql_query($query0);
        $tradeOfferId = $this->db->sql_result($result0, 0, "counter") + 1;

        $query0a = "INSERT INTO ibl_trade_autocounter ( `counter` ) VALUES ( '$tradeOfferId')";
        $this->db->sql_query($query0a);

        return $tradeOfferId;
    }

    /**
     * Calculate salary cap data for both teams
     * @param array $tradeData Trade data
     * @return array Calculated cap data
     */
    protected function calculateSalaryCapData($tradeData)
    {
        $userCurrentSeasonCapTotal = 0;
        $partnerCurrentSeasonCapTotal = 0;
        $userCapSentToPartner = 0;
        $partnerCapSentToUser = 0;

        // Calculate user team salary data
        $switchCounter = $tradeData['switchCounter'];
        for ($j = 0; $j < $switchCounter; $j++) {
            $check = $tradeData['check'][$j] ?? null;
            $salary = (int)($tradeData['contract'][$j] ?? 0);
            $userCurrentSeasonCapTotal += $salary;
            
            if ($check == "on") {
                $userCapSentToPartner += $salary;
            }
        }

        // Calculate partner team salary data
        $fieldsCounter = $tradeData['fieldsCounter'];
        for ($j = $switchCounter; $j < $fieldsCounter; $j++) {
            $check = $tradeData['check'][$j] ?? null;
            $salary = (int)($tradeData['contract'][$j] ?? 0);
            $partnerCurrentSeasonCapTotal += $salary;
            
            if ($check == "on") {
                $partnerCapSentToUser += $salary;
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
     * @param int $tradeOfferId Trade offer ID
     * @param array $tradeData Trade data
     * @return array Result with success status and trade text
     */
    protected function insertTradeOfferData($tradeOfferId, $tradeData)
    {
        $tradeText = "";
        $offeringTeam = $tradeData['offeringTeam'];
        $receivingTeam = $tradeData['receivingTeam'];

        // Process offering team items
        $switchCounter = $tradeData['switchCounter'];
        for ($k = 0; $k < $switchCounter; $k++) {
            if (($tradeData['check'][$k] ?? null) == "on") {
                $result = $this->insertTradeItem(
                    $tradeOfferId,
                    $tradeData['index'][$k],
                    $tradeData['type'][$k],
                    $offeringTeam,
                    $receivingTeam,
                    $receivingTeam
                );
                $tradeText .= $result['tradeText'];
            }
        }

        // Process offering team cash
        if ($this->cashHandler->hasCashInTrade($tradeData['userSendsCash'])) {
            $result = $this->insertCashTradeOffer(
                $tradeOfferId,
                $offeringTeam,
                $receivingTeam,
                $tradeData['userSendsCash']
            );
            $tradeText .= $result['tradeText'];
        }

        // Process receiving team items
        $fieldsCounter = $tradeData['fieldsCounter'];
        for ($k = $switchCounter; $k < $fieldsCounter; $k++) {
            if (($tradeData['check'][$k] ?? null) == "on") {
                $result = $this->insertTradeItem(
                    $tradeOfferId,
                    $tradeData['index'][$k],
                    $tradeData['type'][$k],
                    $receivingTeam,
                    $offeringTeam,
                    $receivingTeam
                );
                $tradeText .= $result['tradeText'];
            }
        }

        // Process receiving team cash
        if ($this->cashHandler->hasCashInTrade($tradeData['partnerSendsCash'])) {
            $result = $this->insertCashTradeOffer(
                $tradeOfferId,
                $receivingTeam,
                $offeringTeam,
                $tradeData['partnerSendsCash']
            );
            $tradeText .= $result['tradeText'];
        }

        return [
            'success' => true,
            'tradeText' => $tradeText,
            'tradeOfferId' => $tradeOfferId
        ];
    }

    /**
     * Insert a single trade item (player or pick)
     * @param int $tradeOfferId Trade offer ID
     * @param int $itemId Item ID
     * @param int $itemType Item type (0=pick, 1=player)
     * @param string $from From team
     * @param string $to To team
     * @param string $approval Team that needs to approve
     * @return array Result
     */
    protected function insertTradeItem($tradeOfferId, $itemId, $itemType, $from, $to, $approval)
    {
        $query = "INSERT INTO ibl_trade_info 
          ( `tradeofferid`, 
            `itemid`, 
            `itemtype`, 
            `from`, 
            `to`, 
            `approval` ) 
        VALUES        ( '$tradeOfferId', 
            '$itemId', 
            '$itemType', 
            '$from', 
            '$to', 
            '$approval' )";
        
        $this->db->sql_query($query);

        $tradeText = "";
        if ($itemType == 0) {
            $tradeText = $this->getPickTradeText($itemId, $from, $to);
        } else {
            $tradeText = $this->getPlayerTradeText($itemId, $from, $to);
        }

        return ['tradeText' => $tradeText];
    }

    /**
     * Get trade text for a draft pick
     * @param int $pickId Pick ID
     * @param string $from From team
     * @param string $to To team
     * @return string Trade text
     */
    protected function getPickTradeText($pickId, $from, $to)
    {
        $sqlgetpick = "SELECT * FROM ibl_draft_picks WHERE pickid = '$pickId'";
        $resultgetpick = $this->db->sql_query($sqlgetpick);
        $rowsgetpick = $this->db->sql_fetchrow($resultgetpick);

        $pickteam = $rowsgetpick['teampick'];
        $pickyear = $rowsgetpick['year'];
        $pickround = $rowsgetpick['round'];
        $picknotes = $rowsgetpick['notes'];

        $tradeText = "The $from send the $pickteam $pickyear Round $pickround draft pick to the $to.<br>";
        if ($picknotes != NULL) {
            $tradeText .= "<i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $picknotes . "</i><br>";
        }

        return $tradeText;
    }

    /**
     * Get trade text for a player
     * @param int $playerId Player ID
     * @param string $from From team
     * @param string $to To team
     * @return string Trade text
     */
    protected function getPlayerTradeText($playerId, $from, $to)
    {
        $sqlgetplyr = "SELECT * FROM ibl_plr WHERE pid = '$playerId'";
        $resultgetplyr = $this->db->sql_query($sqlgetplyr);
        $rowsgetplyr = $this->db->sql_fetchrow($resultgetplyr);

        $plyrname = $rowsgetplyr['name'];
        $plyrpos = $rowsgetplyr['pos'];

        return "The $from send $plyrpos $plyrname to the $to.<br>";
    }

    /**
     * Insert cash trade offer
     * @param int $tradeOfferId Trade offer ID
     * @param string $sendingTeam Sending team
     * @param string $receivingTeam Receiving team
     * @param array $cashAmounts Cash amounts by year
     * @return array Result
     */
    protected function insertCashTradeOffer($tradeOfferId, $sendingTeam, $receivingTeam, $cashAmounts)
    {
        // Insert cash data
        $this->cashHandler->insertCashTradeData($tradeOfferId, $sendingTeam, $receivingTeam, $cashAmounts);

        // Insert trade info record for cash
        $teamIDSending = $this->sharedFunctions->getTidFromTeamname($sendingTeam);
        $teamIDReceiving = $this->sharedFunctions->getTidFromTeamname($receivingTeam);
        
        $query = "INSERT INTO ibl_trade_info
          ( `tradeofferid`,
            `itemid`,
            `itemtype`,
            `from`,
            `to`,
            `approval` )
        VALUES    ( '$tradeOfferId',
            '$teamIDSending" . "0" . "$teamIDReceiving" . "0',
            'cash',
            '$sendingTeam',
            '$receivingTeam',
            '$receivingTeam' )";
        
        $this->db->sql_query($query);

        $cashText = implode(' ', array_filter($cashAmounts));
        $tradeText = "The $sendingTeam send $cashText in cash to the $receivingTeam.<br>";

        return ['tradeText' => $tradeText];
    }

    /**
     * Send trade notification to receiving team
     * @param array $tradeData Trade data
     * @param string $tradeText Trade description text
     */
    protected function sendTradeNotification($tradeData, $tradeText)
    {
        $offeringTeam = $tradeData['offeringTeam'];
        $receivingTeam = $tradeData['receivingTeam'];

        $offeringUserDiscordID = Discord::getDiscordIDFromTeamname($this->db, $offeringTeam);
        $receivingUserDiscordID = Discord::getDiscordIDFromTeamname($this->db, $receivingTeam);

        $cleanTradeText = str_replace(['<br>', '&nbsp;', '<i>', '</i>'], ["\n", " ", "_", "_"], $tradeText);

        $discordDMmessage = 'New trade proposal from <@!' . $offeringUserDiscordID . '>!
' . $cleanTradeText . '
Go here to accept or decline: http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade';

        $arrayContent = [
            'message' => $discordDMmessage,
            'receivingUserDiscordID' => $receivingUserDiscordID,
        ];

        // $response = Discord::sendCurlPOST('http://localhost:50000/discordDM', $arrayContent);
    }
}