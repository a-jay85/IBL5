<?php

require 'mainfile.php';
$sharedFunctions = new Shared($db);

$query0 = "SELECT * FROM ibl_trade_autocounter ORDER BY `counter` DESC";
$result0 = $db->sql_query($query0);
$tradeofferid = $db->sql_result($result0, 0, "counter") + 1;

$query0a = "INSERT INTO ibl_trade_autocounter ( `counter` ) VALUES ( '$tradeofferid') ";
$result0a = $db->sql_query($query0a);

$offeringTeam = $_POST['Team_Name'];
$receivingTeam = $_POST['Team_Name2'];
$switchCounter = $_POST['half'];
$fieldsCounter = $_POST['counterfields'];
$fieldsCounter += 1;

$i = 1;
while ($i < 7) {
    $userSendsCash[$i] = $_POST['userSendsCash' . $i];
    $partnerSendsCash[$i] = $_POST['partnerSendsCash' . $i];
    $i++;
}

//-----CHECK IF SALARIES MATCH-----

$j = 0;
while ($j < $switchCounter) {
    $check = $_POST['check' . $j];
    $salary = $_POST['contract' . $j];
    $userCurrentSeasonCapTotal += $salary;
    if ($check == "on") {
        $userCapSentToPartner += $salary;
        echo "Total Trade Salary My Team: $userCapSentToPartner<br>";
    }
    $j++;
}

if ($userSendsCash[1] != 0) {
    $userCurrentSeasonCapTotal += $userSendsCash[1];
    $partnerCurrentSeasonCapTotal -= $userSendsCash[1];
    echo "Cash Consideration sent to them this season: $userSendsCash[1]<br>";
}

echo "My Payroll: $userCurrentSeasonCapTotal<br><br>";

while ($j < $fieldsCounter) {
    $check = $_POST['check' . $j];
    $salary = $_POST['contract' . $j];
    $partnerCurrentSeasonCapTotal += $salary;
    if ($check == "on") {
        $partnerCapSentToUser += $salary;
        echo "Total Trade Salary Their Team: $partnerCapSentToUser<br>";
    }
    $j++;
}

if ($partnerSendsCash[1] != 0) {
    $partnerCurrentSeasonCapTotal += $partnerSendsCash[1];
    $userCurrentSeasonCapTotal -= $partnerSendsCash[1];
    echo "Cash Consideration sent to me this season: $partnerSendsCash[1]<br>";
}

echo "Their Payroll this season: $partnerCurrentSeasonCapTotal<br><br>";

$userPostTradeCapTotal = $userCurrentSeasonCapTotal - $userCapSentToPartner + $partnerCapSentToUser;
echo "Your Payroll this season, if this trade is accepted: $userPostTradeCapTotal<br>";

$partnerPostTradeCapTotal = $partnerCurrentSeasonCapTotal - $partnerCapSentToUser + $userCapSentToPartner;
echo "Their Payroll this season, if this trade is accepted: $partnerPostTradeCapTotal<p>";

$error = 0;
//if ($userCurrentSeasonCapTotal < 7000)
//{
if ($userPostTradeCapTotal > 7000) {
    echo "This trade is illegal since it puts you over the hard cap.";
    $error = 1;
}
//}else{
//if ($userPostTradeCapTotal > $userCurrentSeasonCapTotal)
//{
//echo "This trade is illegal since you are over the cap and can only make trades that lower your total salary";
//$error=1;
//}
//}

//if ($partnerCurrentSeasonCapTotal < 7000)
//{
if ($partnerPostTradeCapTotal > 7000) {
    echo "This trade is illegal since it puts other team over the hard cap.";
    $error = 1;
}
//}else{
//if ($partnerPostTradeCapTotal > $partnerCurrentSeasonCapTotal)
//{
//echo "This trade is illegal since other team is over the cap and can only make trades that lower their total salary";
//$error=1;
//}
//}

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
VALUES      ( '$tradeofferid', 
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
VALUES      ( '$tradeofferid', 
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

    echo $tradeText;
    $tradeText = str_replace('<br>', "\n", $tradeText);
    $tradeText = str_replace('&nbsp;', " ", $tradeText);
    $tradeText = str_replace('<i>', "_", $tradeText);
    $tradeText = str_replace('</i>', "_", $tradeText);

    $offeringUserDiscordID = $sharedFunctions->getDiscordIDFromTeamname($offeringTeam);
    $receivingUserDiscordID = $sharedFunctions->getDiscordIDFromTeamname($receivingTeam);
    $discordDMmessage = 'New trade proposal from <@!' . $offeringUserDiscordID . '>!
'. $tradeText .'
Go here to accept or decline: http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade';
    $arrayContent = array(
            'message' => $discordDMmessage,
            'receivingUserDiscordID' => $receivingUserDiscordID,);

    echo "<p>";
    // $response = Discord::sendCurlPOST('http://localhost:50000/discordDM', $arrayContent);

    echo "<p>";
    echo "Trade Offer Entered Into Database. Go back <a href='modules.php?name=Trading&op=reviewtrade'>Trade Review Page</a>";
}
