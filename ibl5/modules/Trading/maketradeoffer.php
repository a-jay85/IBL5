<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
$sharedFunctions = new Shared($db);
$season = new Season($db);

$query0 = "SELECT * FROM ibl_trade_autocounter ORDER BY `counter` DESC";
$result0 = $db->sql_query($query0);
$tradeofferid = $db->sql_result($result0, 0, "counter") + 1;

$query0a = "INSERT INTO ibl_trade_autocounter ( `counter` ) VALUES ( '$tradeofferid') ";
$result0a = $db->sql_query($query0a);

$offeringTeam = $_POST['Team_Name'];
$teamIDOfferingTeam = $sharedFunctions->getTidFromTeamname($offeringTeam); // This function now returns an integer
$receivingTeam = $_POST['Team_Name2'];
$teamIDReceivingTeam = $sharedFunctions->getTidFromTeamname($receivingTeam); // This function now returns an integer
$switchCounter = $_POST['half'];
$fieldsCounter = $_POST['counterfields'];
$fieldsCounter += 1;

while ($i < 7) {
    $userSendsCash[$i] = (int) $_POST['userSendsCash' . $i];
    $partnerSendsCash[$i] = (int) $_POST['partnerSendsCash' . $i];
    $i++;
}

$filteredUserSendsCash = array_filter($userSendsCash);
$filteredPartnerSendsCash = array_filter($partnerSendsCash);

if (!empty($filteredUserSendsCash) AND min($filteredUserSendsCash) < 100) {
        echo "This trade is illegal: the minimum amount of cash that your team can send in any one season is 100.";
        exit;
}
if (!empty($filteredPartnerSendsCash) AND min($filteredPartnerSendsCash) < 100) {
        echo "This trade is illegal: the minimum amount of cash that the other team can send in any one season is 100.";
        exit;
}

//-----CHECK IF SALARIES MATCH-----

$j = 0;
while ($j < $switchCounter) {
    $check = $_POST['check' . $j];
    $salary = (int) $_POST['contract' . $j];
    $userCurrentSeasonCapTotal += $salary;
    if ($check == "on") {
        $userCapSentToPartner += $salary;
        echo "Total Trade Salary My Team: $userCapSentToPartner<br>";
    }
    $j++;
}

// If the current season phase shifts cap situations to next season, evaluate next season's cap limits.
if (
    $season->phase == "Playoffs"
    OR $season->phase == "Draft"
    OR $season->phase == "Free Agency"
) {
    $cashConsiderationSentToThemThisSeason = $userSendsCash[2];
    $cashConsiderationSentToMeThisSeason = $partnerSendsCash[2];
} else {
    $cashConsiderationSentToThemThisSeason = $userSendsCash[1];
    $cashConsiderationSentToMeThisSeason = $partnerSendsCash[1];
}

if ($cashConsiderationSentToThemThisSeason != 0) {
    $userCurrentSeasonCapTotal += $cashConsiderationSentToThemThisSeason;
    $partnerCurrentSeasonCapTotal -= $cashConsiderationSentToThemThisSeason;
    echo "Cash Consideration sent to them this season: $$cashConsiderationSentToThemThisSeason<br>";
}

echo "My Payroll: $userCurrentSeasonCapTotal<br><br>";

while ($j < $fieldsCounter) {
    $check = $_POST['check' . $j];
    $salary = $_POST['contract' . $j];
    $partnerCurrentSeasonCapTotal += (int) $salary;
    if ($check == "on") {
        $partnerCapSentToUser += $salary;
        echo "Total Trade Salary Their Team: $partnerCapSentToUser<br>";
    }
    $j++;
}

if ($cashConsiderationSentToMeThisSeason != 0) {
    $partnerCurrentSeasonCapTotal += $cashConsiderationSentToMeThisSeason;
    $userCurrentSeasonCapTotal -= $cashConsiderationSentToMeThisSeason;
    echo "Cash Consideration sent to me this season: $cashConsiderationSentToMeThisSeason<br>";
}

echo "Their Payroll this season: $partnerCurrentSeasonCapTotal<br><br>";

$userPostTradeCapTotal = $userCurrentSeasonCapTotal - $userCapSentToPartner + $partnerCapSentToUser;
echo "Your Payroll this season, if this trade is accepted: $userPostTradeCapTotal<br>";

$partnerPostTradeCapTotal = $partnerCurrentSeasonCapTotal - $partnerCapSentToUser + $userCapSentToPartner;
echo "Their Payroll this season, if this trade is accepted: $partnerPostTradeCapTotal<p>";

$error = 0;

if ($userPostTradeCapTotal > League::HARD_CAP_MAX) {
    echo "This trade is illegal since it puts you over the hard cap.";
    $error = 1;
}
if ($partnerPostTradeCapTotal > League::HARD_CAP_MAX) {
    echo "This trade is illegal since it puts other team over the hard cap.";
    $error = 1;
}

//-----END SALARY MATCH CHECK-----
if ($error == 0) {
    $tradeText = "";

    $k = 0;
    while ($k < $switchCounter) {
        $itemtype = $_POST['type' . $k];
        $itemid = $_POST['index' . $k];
        $check = $_POST['check' . $k];
        if ($check == "on") {
            $queryi = "INSERT INTO ibl_trade_info 
              ( `tradeofferid`, 
                `itemid`, 
                `itemtype`, 
                `from`, 
                `to`, 
                `approval` ) 
VALUES        ( '$tradeofferid', 
                '$itemid', 
                '$itemtype', 
                '$offeringTeam', 
                '$receivingTeam', 
                '$receivingTeam' );";
            $resulti = $db->sql_query($queryi);

            if ($itemtype == 0) {
                $sqlgetpick = "SELECT * FROM ibl_draft_picks WHERE pickid = '$itemid'";
                $resultgetpick = $db->sql_query($sqlgetpick);
                $rowsgetpick = $db->sql_fetchrow($resultgetpick);
    
                $pickteam = $rowsgetpick['teampick'];
                $pickyear = $rowsgetpick['year'];
                $pickround = $rowsgetpick['round'];
                $picknotes = $rowsgetpick['notes'];
    
                $tradeText .= "The $offeringTeam send the $pickteam $pickyear Round $pickround draft pick to the $receivingTeam.<br>";
                if ($picknotes != NULL) {
                    $tradeText .= "<i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $picknotes . "</i><br>";
                }
            } else {
                $sqlgetplyr = "SELECT * FROM ibl_plr WHERE pid = '$itemid'";
                $resultgetplyr = $db->sql_query($sqlgetplyr);
                $rowsgetplyr = $db->sql_fetchrow($resultgetplyr);
    
                $plyrname = $rowsgetplyr['name'];
                $plyrpos = $rowsgetplyr['pos'];
    
                $tradeText .= "The $offeringTeam send $plyrpos $plyrname to the $receivingTeam.<br>";
            }
        }

        $k++;
    }

    $userSendsCash[1] = $userSendsCash[1] ?? 0;
    $userSendsCash[2] = $userSendsCash[2] ?? 0;
    $userSendsCash[3] = $userSendsCash[3] ?? 0;
    $userSendsCash[4] = $userSendsCash[4] ?? 0;
    $userSendsCash[5] = $userSendsCash[5] ?? 0;
    $userSendsCash[6] = $userSendsCash[6] ?? 0;

    if (
        $userSendsCash[1] != 0
        OR $userSendsCash[2] != 0
        OR $userSendsCash[3] != 0
        OR $userSendsCash[4] != 0
        OR $userSendsCash[5] != 0
        OR $userSendsCash[6] != 0
    ) {
        $queryUserSendsCash = "INSERT INTO ibl_trade_cash
          ( `tradeOfferID`,
            `sendingTeam`,
            `receivingTeam`,
            `cy1`,
            `cy2`,
            `cy3`,
            `cy4`,
            `cy5`,
            `cy6` )
VALUES    ( '$tradeofferid',
            '$offeringTeam',
            '$receivingTeam',
            '$userSendsCash[1]',
            '$userSendsCash[2]',
            '$userSendsCash[3]',
            '$userSendsCash[4]',
            '$userSendsCash[5]',
            '$userSendsCash[6]' );";
        $resultUserSendsCash = $db->sql_query($queryUserSendsCash);

        $queryUserInsertCashTradeInfo = "INSERT INTO ibl_trade_info
          ( `tradeofferid`,
            `itemid`,
            `itemtype`,
            `from`,
            `to`,
            `approval` )
VALUES    ( '$tradeofferid',
            '$teamIDOfferingTeam" . '0' . "$teamIDReceivingTeam" . '0' . "',
            'cash',
            '$offeringTeam',
            '$receivingTeam',
            '$receivingTeam' );";
        $resultUserInsertCashTradeInfo = $db->sql_query($queryUserInsertCashTradeInfo);

        if ($resultUserSendsCash AND $resultUserInsertCashTradeInfo) {
            $tradeText .= "The $offeringTeam send 
            $userSendsCash[1] $userSendsCash[2] $userSendsCash[3] $userSendsCash[4] $userSendsCash[5] $userSendsCash[6]
            in cash to the $receivingTeam.<br>";
        }
    }

    while ($k < $fieldsCounter) {
        $itemtype = $_POST['type' . $k];
        $itemid = $_POST['index' . $k];
        $check = $_POST['check' . $k];
        if ($check == "on") {
            $queryi = "INSERT INTO ibl_trade_info 
              ( `tradeofferid`, 
                `itemid`, 
                `itemtype`, 
                `from`, 
                `to`, 
                `approval` ) 
VALUES        ( '$tradeofferid', 
                '$itemid', 
                '$itemtype', 
                '$receivingTeam', 
                '$offeringTeam', 
                '$receivingTeam' );";
            $resulti = $db->sql_query($queryi);

            if ($itemtype == 0) {
                $sqlgetpick = "SELECT * FROM ibl_draft_picks WHERE pickid = '$itemid'";
                $resultgetpick = $db->sql_query($sqlgetpick);
                $rowsgetpick = $db->sql_fetchrow($resultgetpick);
    
                $pickteam = $rowsgetpick['teampick'];
                $pickyear = $rowsgetpick['year'];
                $pickround = $rowsgetpick['round'];
                $picknotes = $rowsgetpick['notes'];
    
                $tradeText .= "The $receivingTeam send the $pickteam $pickyear Round $pickround draft pick to the $offeringTeam.<br>";
                if ($picknotes != NULL) {
                    $tradeText .= "<i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $picknotes . "</i><br>";
                }
            } else {
                $sqlgetplyr = "SELECT * FROM ibl_plr WHERE pid = '$itemid'";
                $resultgetplyr = $db->sql_query($sqlgetplyr);
                $rowsgetplyr = $db->sql_fetchrow($resultgetplyr);
    
                $plyrname = $rowsgetplyr['name'];
                $plyrpos = $rowsgetplyr['pos'];
    
                $tradeText .= "The $receivingTeam send $plyrpos $plyrname to the $offeringTeam.<br>";
            }
        }

        $k++;
    }

    $partnerSendsCash[1] = $partnerSendsCash[1] ?? 0;
    $partnerSendsCash[2] = $partnerSendsCash[2] ?? 0;
    $partnerSendsCash[3] = $partnerSendsCash[3] ?? 0;
    $partnerSendsCash[4] = $partnerSendsCash[4] ?? 0;
    $partnerSendsCash[5] = $partnerSendsCash[5] ?? 0;
    $partnerSendsCash[6] = $partnerSendsCash[6] ?? 0;

    if (
        $partnerSendsCash[1] != 0
        OR $partnerSendsCash[2] != 0
        OR $partnerSendsCash[3] != 0
        OR $partnerSendsCash[4] != 0
        OR $partnerSendsCash[5] != 0
        OR $partnerSendsCash[6] != 0
    ) {
        $queryPartnerSendsCash = "INSERT INTO ibl_trade_cash
          ( `tradeOfferID`,
            `sendingTeam`,
            `receivingTeam`,
            `cy1`,
            `cy2`,
            `cy3`,
            `cy4`,
            `cy5`,
            `cy6` )
VALUES    ( '$tradeofferid',
            '$receivingTeam',
            '$offeringTeam',
            '$partnerSendsCash[1]',
            '$partnerSendsCash[2]',
            '$partnerSendsCash[3]',
            '$partnerSendsCash[4]',
            '$partnerSendsCash[5]',
            '$partnerSendsCash[6]' );";
        $resultPartnerSendsCash = $db->sql_query($queryPartnerSendsCash);

        $queryPartnerInsertCashTradeInfo = "INSERT INTO ibl_trade_info
          ( `tradeofferid`,
            `itemid`,
            `itemtype`,
            `from`,
            `to`,
            `approval` )
VALUES    ( '$tradeofferid',
            '$teamIDReceivingTeam" . '0' . "$teamIDOfferingTeam" . '0' . "',
            'cash',
            '$receivingTeam',
            '$offeringTeam',
            '$receivingTeam' );";
        $resultPartnerInsertCashTradeInfo = $db->sql_query($queryPartnerInsertCashTradeInfo);

        if ($resultPartnerSendsCash AND $resultPartnerInsertCashTradeInfo) {
            $tradeText .= "The $receivingTeam send
            $partnerSendsCash[1] $partnerSendsCash[2] $partnerSendsCash[3] $partnerSendsCash[4] $partnerSendsCash[5] $partnerSendsCash[6]
            in cash to the $offeringTeam.<br>";
        }
    }

    echo $tradeText;
    $tradeText = str_replace('<br>', "\n", $tradeText);
    $tradeText = str_replace('&nbsp;', " ", $tradeText);
    $tradeText = str_replace('<i>', "_", $tradeText);
    $tradeText = str_replace('</i>', "_", $tradeText);

    $offeringUserDiscordID = Discord::getDiscordIDFromTeamname($db, $offeringTeam);
    $receivingUserDiscordID = Discord::getDiscordIDFromTeamname($db, $receivingTeam);
    $discordDMmessage = 'New trade proposal from <@!' . $offeringUserDiscordID . '>!
'. $tradeText .'
Go here to accept or decline: http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade';
    $arrayContent = array(
            'message' => $discordDMmessage,
            'receivingUserDiscordID' => $receivingUserDiscordID,);

    echo "<p>";
    // $response = Discord::sendCurlPOST('http://localhost:50000/discordDM', $arrayContent);

    echo "<p>";
    echo "Trade Offer Entered Into Database. Go back <a href='/ibl5/modules.php?name=Trading&op=reviewtrade'>Trade Review Page</a>";
}
