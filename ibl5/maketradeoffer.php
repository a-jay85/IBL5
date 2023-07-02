<?php

require 'mainfile.php';
$sharedFunctions = new Shared($db);

$query0 = "SELECT * FROM ibl_trade_autocounter ORDER BY `counter` DESC";
$result0 = $db->sql_query($query0);
$tradeofferid = $db->sql_result($result0, 0, "counter") + 1;

$query0a = "INSERT INTO ibl_trade_autocounter ( `counter` ) VALUES ( '$tradeofferid') ";
$result0a = $db->sql_query($query0a);

$Team_Offering = $_POST['Team_Name'];
$Team_Receiving = $_POST['Team_Name2'];
$Swapat = $_POST['half'];
$Fields_Counter = $_POST['counterfields'];
$Fields_Counter = $Fields_Counter + 1;
$error = 0;
//-----CHECK IF SALARIES MATCH-----

$j = 0;
while ($j < $Swapat) {
    $Type = $_POST['type' . $j];
    $Index = $_POST['index' . $j];
    $Check = $_POST['check' . $j];
    $Salary = $_POST['contract' . $j];
    $PayrollA = $PayrollA + $Salary;
    if ($Check == "on") {
        $Total_SalaryA = $Total_SalaryA + $Salary;
        echo "Total Trade Salary My Team: $$Total_SalaryA<br>";
    }
    $j++;
}
echo "My Payroll: $$PayrollA<br><br>";
while ($j < $Fields_Counter) {
    $Type = $_POST['type' . $j];
    $Index = $_POST['index' . $j];
    $Check = $_POST['check' . $j];
    $Salary = $_POST['contract' . $j];
    $PayrollB = $PayrollB + $Salary;
    if ($Check == "on") {
        $Total_SalaryB = $Total_SalaryB + $Salary;
        echo "Total Trade Salary Their Team: $$Total_SalaryB<br>";
    }
    $j++;
}
echo "His Payroll: $$PayrollB<br><br>";
$New_PayrollA = $PayrollA - $Total_SalaryA + $Total_SalaryB;
$New_PayrollB = $PayrollB - $Total_SalaryB + $Total_SalaryA;
echo "Your New Payroll: $$New_PayrollA<br>His New Payroll: $$New_PayrollB<p>";
//if ($PayrollA < 7000)
//{
if ($New_PayrollA > 7000) {
    echo "This trade is illegal since it puts you over the hard cap.";
    $error = 1;
}
//}else{
//if ($New_PayrollA > $PayrollA)
//{
//echo "This trade is illegal since you are over the cap and can only make trades that lower your total salary";
//$error=1;
//}
//}

//if ($PayrollB < 7000)
//{
if ($New_PayrollB > 7000) {
    echo "This trade is illegal since it puts other team over the hard cap.";
    $error = 1;
}
//}else{
//if ($New_PayrollB > $PayrollB)
//{
//echo "This trade is illegal since other team is over the cap and can only make trades that lower their total salary";
//$error=1;
//}
//}

//-----END SALARY MATCH CHECK-----
if ($error == 0) {
    $tradeText = "";

    $k = 0;
    while ($k < $Swapat) {
        $itemtype = $_POST['type' . $k];
        $itemid = $_POST['index' . $k];
        $Check = $_POST['check' . $k];
        if ($Check == "on") {
            $queryi = "INSERT INTO ibl_trade_info 
            ( ` tradeofferid ` , 
              ` itemid ` , 
              ` itemtype ` , 
              ` from ` , 
              ` to ` , 
              ` approval ` ) 
VALUES      ( '$tradeofferid', 
              '$itemid', 
              '$itemtype', 
              '$Team_Offering', 
              '$Team_Receiving', 
              '$Team_Receiving' ) ";
            $resulti = $db->sql_query($queryi);

            if ($itemtype == 0) {
                $sqlgetpick = "SELECT * FROM ibl_draft_picks WHERE pickid = '$itemid'";
                $resultgetpick = $db->sql_query($sqlgetpick);
                $rowsgetpick = $db->sql_fetchrow($resultgetpick);
    
                $pickteam = $rowsgetpick['teampick'];
                $pickyear = $rowsgetpick['year'];
                $pickround = $rowsgetpick['round'];
                $picknotes = $rowsgetpick['notes'];
    
                $tradeText .= "The $Team_Offering send the $pickteam $pickyear Round $pickround draft pick to the $Team_Receiving.<br>";
                if ($picknotes != NULL) {
                    $tradeText .= "<i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $picknotes . "</i><br>";
                }
            } else {
                $sqlgetplyr = "SELECT * FROM ibl_plr WHERE pid = '$itemid'";
                $resultgetplyr = $db->sql_query($sqlgetplyr);
                $rowsgetplyr = $db->sql_fetchrow($resultgetplyr);
    
                $plyrname = $rowsgetplyr['name'];
                $plyrpos = $rowsgetplyr['pos'];
    
                $tradeText .= "The $Team_Offering send $plyrpos $plyrname to the $Team_Receiving.<br>";
            }
        }

        $k++;
    }

    while ($k < $Fields_Counter) {
        $itemtype = $_POST['type' . $k];
        $itemid = $_POST['index' . $k];
        $Check = $_POST['check' . $k];
        if ($Check == "on") {
            $queryi = "INSERT INTO ibl_trade_info 
            ( ` tradeofferid ` , 
              ` itemid ` , 
              ` itemtype ` , 
              ` from ` , 
              ` to ` , 
              ` approval ` ) 
VALUES      ( '$tradeofferid', 
              '$itemid', 
              '$itemtype', 
              '$Team_Receiving', 
              '$Team_Offering', 
              '$Team_Receiving' ) ";
            $resulti = $db->sql_query($queryi);

            if ($itemtype == 0) {
                $sqlgetpick = "SELECT * FROM ibl_draft_picks WHERE pickid = '$itemid'";
                $resultgetpick = $db->sql_query($sqlgetpick);
                $rowsgetpick = $db->sql_fetchrow($resultgetpick);
    
                $pickteam = $rowsgetpick['teampick'];
                $pickyear = $rowsgetpick['year'];
                $pickround = $rowsgetpick['round'];
                $picknotes = $rowsgetpick['notes'];
    
                $tradeText .= "The $Team_Receiving send the $pickteam $pickyear Round $pickround draft pick to the $Team_Offering.<br>";
                if ($picknotes != NULL) {
                    $tradeText .= "<i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $picknotes . "</i><br>";
                }
            } else {
                $sqlgetplyr = "SELECT * FROM ibl_plr WHERE pid = '$itemid'";
                $resultgetplyr = $db->sql_query($sqlgetplyr);
                $rowsgetplyr = $db->sql_fetchrow($resultgetplyr);
    
                $plyrname = $rowsgetplyr['name'];
                $plyrpos = $rowsgetplyr['pos'];
    
                $tradeText .= "The $Team_Receiving send $plyrpos $plyrname to the $Team_Offering.<br>";
            }
        }

        $k++;
    }

    echo $tradeText;
    $tradeText = str_replace('<br>', "\n", $tradeText);
    $tradeText = str_replace('&nbsp;', " ", $tradeText);
    $tradeText = str_replace('<i>', "_", $tradeText);
    $tradeText = str_replace('</i>', "_", $tradeText);

    $offeringUserDiscordID = $sharedFunctions->getDiscordIDFromTeamname($Team_Offering);
    $receivingUserDiscordID = $sharedFunctions->getDiscordIDFromTeamname($Team_Receiving);
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
