<?php

require 'mainfile.php';

echo "<HTML><HEAD><TITLE>Free Agency Offer Entry</TITLE></HEAD><BODY>";

$Team_Name = $_POST['teamname'];
$Player_Name = $_POST['playername'];
$Demands_Years = $_POST['demyrs'];
$Demands_Total = $_POST['demtot']*100;
$amendedCapSpaceYear1 = $_POST['amendedCapSpaceYear1'];
$Cap_Space = $_POST['capnumber'];
$Cap_Space2 = $_POST['capnumber2'];
$Cap_Space3 = $_POST['capnumber3'];
$Cap_Space4 = $_POST['capnumber4'];
$Cap_Space5 = $_POST['capnumber5'];
$Cap_Space6 = $_POST['capnumber6'];
$Offer_1 = $_POST['offeryear1'];
$Offer_2 = $_POST['offeryear2'];
$Offer_3 = $_POST['offeryear3'];
$Offer_4 = $_POST['offeryear4'];
$Offer_5 = $_POST['offeryear5'];
$Offer_6 = $_POST['offeryear6'];
$MLE_Years = $_POST['MLEyrs'];
$Bird_Years = $_POST['bird'];
$Year1_Max = $_POST['max'];
$Minimum = $_POST['vetmin'];
$MLE=0;
$LLE=0;

if ($MLE_Years == 8) {
$Offer_1=$Minimum;
$Offer_2=0;
$Offer_3=0;
$Offer_4=0;
$Offer_5=0;
$Offer_6=0;
}

if ($MLE_Years == 7) {
$Offer_1=145;
$Offer_2=0;
$Offer_3=0;
$Offer_4=0;
$Offer_5=0;
$Offer_6=0;
$LLE=1;
}

if ($MLE_Years == 6) {
$Offer_1=450;
$Offer_2=495;
$Offer_3=540;
$Offer_4=585;
$Offer_5=630;
$Offer_6=675;
$MLE=1;
}

if ($MLE_Years == 5) {
$Offer_1=450;
$Offer_2=495;
$Offer_3=540;
$Offer_4=585;
$Offer_5=630;
$Offer_6=0;
$MLE=1;
}

if ($MLE_Years == 4) {
$Offer_1=450;
$Offer_2=495;
$Offer_3=540;
$Offer_4=585;
$Offer_5=0;
$Offer_6=0;
$MLE=1;
}

if ($MLE_Years == 3) {
$Offer_1=450;
$Offer_2=495;
$Offer_3=540;
$Offer_4=0;
$Offer_5=0;
$Offer_6=0;
$MLE=1;
}

if ($MLE_Years == 2) {
$Offer_1=450;
$Offer_2=495;
$Offer_3=0;
$Offer_4=0;
$Offer_5=0;
$Offer_6=0;
$MLE=1;
}

if ($MLE_Years == 1) {
$Offer_1=450;
$Offer_2=0;
$Offer_3=0;
$Offer_4=0;
$Offer_5=0;
$Offer_6=0;
$MLE=1;
}

$yrsinoffer=6;
if ($Offer_6 == 0) {
$yrsinoffer=5;
}
if ($Offer_5 == 0) {
$yrsinoffer=4;
}
if ($Offer_4 == 0) {
$yrsinoffer=3;
}
if ($Offer_3 == 0) {
$yrsinoffer=2;
}
if ($Offer_2 == 0) {
$yrsinoffer=1;
}

$Offer_Avg = ($Offer_1+$Offer_2+$Offer_3+$Offer_4+$Offer_5+$Offer_6)/$yrsinoffer;

// LOOP TO GET MILLIONS COMMITTED AT POSITION

$queryposition="SELECT * FROM nuke_iblplyr WHERE `name` ='$Player_Name'";
$resultposition=$db->sql_query($queryposition);

$player_pos = $db->sql_result($resultposition,0,"pos");

$querymillions="SELECT * FROM nuke_iblplyr WHERE `teamname`='$Team_Name' AND `pos`='$player_pos' AND `name`!='$Player_Name'";
$resultmillions=$db->sql_query($querymillions);
$nummillions=$db->sql_numrows($resultmillions);

$tf_millions = 0;
$i=0;

    while ($i < $nummillions)
  {

    $millionscy = $db->sql_result($resultmillions,$i,"cy");
    $millionscy1 = $db->sql_result($resultmillions,$i,"cy1");
    $millionscy2 = $db->sql_result($resultmillions,$i,"cy2");
    $millionscy3 = $db->sql_result($resultmillions,$i,"cy3");
    $millionscy4 = $db->sql_result($resultmillions,$i,"cy4");
    $millionscy5 = $db->sql_result($resultmillions,$i,"cy5");
    $millionscy6 = $db->sql_result($resultmillions,$i,"cy6");

// LOOK AT SALARY COMMITTED NEXT YEAR, NOT THIS YEAR

if ($millionscy == 0) {
$tf_millions = $tf_millions+$millionscy1;
}
if ($millionscy == 1) {
$tf_millions = $tf_millions+$millionscy2;
}
if ($millionscy == 2) {
$tf_millions = $tf_millions+$millionscy3;
}
if ($millionscy == 3) {
$tf_millions = $tf_millions+$millionscy4;
}
if ($millionscy == 4) {
$tf_millions = $tf_millions+$millionscy5;
}
if ($millionscy == 5) {
$tf_millions = $tf_millions+$millionscy6;
}

$i++;
}

// GET MILLIONS AT ADJACENT POSITIONS

if ($player_pos == 'PG')
{
 $adjpos1='G';
 $adjpos2='';
}
if ($player_pos == 'G')
{
 $adjpos1='PG';
 $adjpos2='SG';
}
if ($player_pos == 'SG')
{
 $adjpos1='G';
 $adjpos2='GF';
}
if ($player_pos == 'GF')
{
 $adjpos1='SG';
 $adjpos2='SF';
}
if ($player_pos == 'SF')
{
 $adjpos1='GF';
 $adjpos2='F';
}
if ($player_pos == 'F')
{
 $adjpos1='SF';
 $adjpos2='PF';
}
if ($player_pos == 'PF')
{
 $adjpos1='F';
 $adjpos2='FC';
}
if ($player_pos == 'FC')
{
 $adjpos1='PF';
 $adjpos2='C';
}
if ($player_pos == 'C')
{
 $adjpos1='FC';
 $adjpos2='';
}


$querymillions="SELECT * FROM nuke_iblplyr WHERE `teamname`='$Team_Name' AND `pos`='$adjpos1' AND `name`!='$player_name'";
$resultmillions=$db->sql_query($querymillions);
$nummillions=$db->sql_numrows($resultmillions);
$i=0;
    while ($i < $nummillions)
  {

    $millionscy = $db->sql_result($resultmillions,$i,"cy");
    $millionscy1 = $db->sql_result($resultmillions,$i,"cy1");
    $millionscy2 = $db->sql_result($resultmillions,$i,"cy2");
    $millionscy3 = $db->sql_result($resultmillions,$i,"cy3");
    $millionscy4 = $db->sql_result($resultmillions,$i,"cy4");
    $millionscy5 = $db->sql_result($resultmillions,$i,"cy5");
    $millionscy6 = $db->sql_result($resultmillions,$i,"cy6");

// LOOK AT SALARY COMMITTED NEXT YEAR, NOT THIS YEAR

if ($millionscy == 0) {
$tf_millions = $tf_millions+$millionscy1;
}
if ($millionscy == 1) {
$tf_millions = $tf_millions+$millionscy2;
}
if ($millionscy == 2) {
$tf_millions = $tf_millions+$millionscy3;
}
if ($millionscy == 3) {
$tf_millions = $tf_millions+$millionscy4;
}
if ($millionscy == 4) {
$tf_millions = $tf_millions+$millionscy5;
}
if ($millionscy == 5) {
$tf_millions = $tf_millions+$millionscy6;
}
$i++;
}

$querymillions="SELECT * FROM nuke_iblplyr WHERE `teamname`='$Team_Name' AND `pos`='$adjpos2' AND `name`!='$player_name'";
$resultmillions=$db->sql_query($querymillions);
$nummillions=$db->sql_numrows($resultmillions);
$i=0;
    while ($i < $nummillions)
  {

    $millionscy = $db->sql_result($resultmillions,$i,"cy");
    $millionscy1 = $db->sql_result($resultmillions,$i,"cy1");
    $millionscy2 = $db->sql_result($resultmillions,$i,"cy2");
    $millionscy3 = $db->sql_result($resultmillions,$i,"cy3");
    $millionscy4 = $db->sql_result($resultmillions,$i,"cy4");
    $millionscy5 = $db->sql_result($resultmillions,$i,"cy5");
    $millionscy6 = $db->sql_result($resultmillions,$i,"cy6");

// LOOK AT SALARY COMMITTED NEXT YEAR, NOT THIS YEAR

if ($millionscy == 0) {
$tf_millions = $tf_millions+$millionscy1;
}
if ($millionscy == 1) {
$tf_millions = $tf_millions+$millionscy2;
}
if ($millionscy == 2) {
$tf_millions = $tf_millions+$millionscy3;
}
if ($millionscy == 3) {
$tf_millions = $tf_millions+$millionscy4;
}
if ($millionscy == 4) {
$tf_millions = $tf_millions+$millionscy5;
}
if ($millionscy == 5) {
$tf_millions = $tf_millions+$millionscy6;
}
$i++;

}

// END LOOPS

// ==== GET MOD FACTORS

if ($tf_millions > 2000) {
	$tf_millions = 2000;
}

$query1="SELECT * FROM nuke_ibl_team_info WHERE team_name = '$Team_Name'";
$result1=$db->sql_query($query1);

$tf_wins=$db->sql_result($result1,0,"Contract_Wins");
$tf_loss=$db->sql_result($result1,0,"Contract_Losses");
$tf_trdw=$db->sql_result($result1,0,"Contract_AvgW");
$tf_trdl=$db->sql_result($result1,0,"Contract_AvgL");
$tf_coach=$db->sql_result($result1,0,"Contract_Coach");

$queryteam="SELECT * FROM nuke_iblplyr WHERE name = '$Player_Name'";
$resultteam=$db->sql_query($queryteam);

    $player_team = $db->sql_result($resultteam,0,"teamname");
    $player_winner = $db->sql_result($resultteam,0,"winner");
    $player_tradition = $db->sql_result($resultteam,0,"tradition");
    $player_coach = $db->sql_result($resultteam,0,"coach");
    $player_security = $db->sql_result($resultteam,0,"security");
    $player_loyalty = $db->sql_result($resultteam,0,"loyalty");
    $player_playingtime = $db->sql_result($resultteam,0,"playingTime");

//$modfactor1 = (0.000433*($tf_wins-$tf_loss)*($player_winner-1));
$modfactor1 = (0.000153*($tf_wins-$tf_loss)*($player_winner-1));
//$modfactor2 = (0.000433*($tf_trdw-$tf_trdl)*($player_tradition-1));
$modfactor2 = (0.000153*($tf_trdw-$tf_trdl)*($player_tradition-1));
$modfactor3 = (0.0025*($tf_coach)*($player_coach-1));

if ($Team_Name == $player_team) {
$modfactor4 = (.025*($player_loyalty-1));
} else {
$modfactor4 = -(.025*($player_loyalty-1));
}

$modfactor5 = (.01*($yrsinoffer-1)-0.025)*($player_security-1);
$modfactor6 = -(.0025*$tf_millions/100-0.025)*($player_playingtime-1);

$modifier = 1+$modfactor1+$modfactor2+$modfactor3+$modfactor4+$modfactor5+$modfactor6;
$modfactor1 = $modfactor1*100;
$modfactor2 = $modfactor2*100;
$modfactor3 = $modfactor3*100;
$modfactor4 = $modfactor4*100;
$modfactor5 = $modfactor5*100;
$modfactor6 = $modfactor6*100;
$random = (rand(5, -5));
$modrandom = (100+$random)/100;
//echo "Winner Bonus: $modfactor1 %<br> Wins: $tf_wins<br> Tradition Bonus: $modfactor2 %<br> Tradition Wins: $tf_trdw<br> Coach Bonus: $modfactor3 %<br> Coaching Points: $tf_coach<br> Loyalty Bonus: $modfactor4 %<br> Security Bonus: $modfactor5 %<br> Years Offered: $yrsinoffer<br> Play Time Bonus: $modfactor6 %<br>Money Commited: $tf_millions<br>Random: $modrandom%<br>";

$perceivedvalue = $Offer_Avg*$modifier*$modrandom;

$nooffer=0;

// ==== CHECK FOR ILLEGAL OFFERS WITH ZERO CONTRACT AMOUNTS ====

// ====== (ADD HANDLING FOR MLE, LLE, VETMIN) ======

if ($Offer_1 == 0) {
echo "Sorry, you must enter an amount greater than zero in the first year of a free agency offer. Your offer in Year 1 was zero, so this offer is not valid.<br>";
$nooffer=1;
}

if ($Offer_1 < $Minimum) {
    echo "Sorry, you must enter an amount greater than the Veteran's Minimum in the first year of a free agency offer.<br>
        Your offer in Year 1 was <b>$Offer_1</b>, but should be at least <b>$Minimum</b>.<br>";
    $nooffer = 1;
}

// ===== BIRD RIGHTS TREATMENT

if ($player_team == $Team_Name) {
} else {
$Bird_Years=0;
}

if ($Bird_Years > 2)
{
$Offer_max_increase = round($Offer_1*0.125,0);
} else {
$Offer_max_increase = round($Offer_1*0.1,0);
}

// ==== CHECK FOR ILLEGAL OFFERS THAT ARE OVER THE SALARY CAP
if ($Bird_Years < 3) {
    if ($Offer_1 > $amendedCapSpaceYear1) {
        if ($MLE_Years > 0) {
        } else {
            echo "Sorry, you do not have sufficient cap space under the soft cap to make the offer.  You offered $Offer_1 in the first year of the contract, which is more than $Cap_Space, the amount of cap space you have available.<br>";
            $nooffer = 1;
        }
    }
} else {
  $Hard_Cap_Space=$Cap_Space+2000;
  $Hard_Cap_Space2=$Cap_Space2+2000;
  $Hard_Cap_Space3=$Cap_Space3+2000;
  $Hard_Cap_Space4=$Cap_Space4+2000;
  $Hard_Cap_Space5=$Cap_Space5+2000;
  $Hard_Cap_Space6=$Cap_Space6+2000;

  if ($nooffer == 0) {
	  if ($Offer_1 > $Hard_Cap_Space) {
		echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_1 in the first year of the contract, which is more than $Hard_Cap_Space, the amount of cap space you have available.<br>";
		$nooffer=1;
	  }
  }
//  if ($nooffer == 0) {
//	  if (($Offer_2 > $Hard_Cap_Space2) AND ($Offer_2 > 0)){
//		echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_2 in the second year of the contract, which is more than $Hard_Cap_Space2, the amount of cap space you have available.<br>";
//		$nooffer=1;
//	  }
//  }
//  if ($nooffer == 0) {
//	  if ($Offer_3 > $Hard_Cap_Space3) {
//		echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_3 in the third year of the contract, which is more than $Hard_Cap_Space3, the amount of cap space you have available.<br>";
//		$nooffer=1;
//	  }
//  }
//  if ($nooffer == 0) {
//	  if ($Offer_4 > $Hard_Cap_Space4) {
//		echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_4 in the fourth year of the contract, which is more than $Hard_Cap_Space4, the amount of cap space you have available.<br>";
//		$nooffer=1;
//      }
//  }
//  if ($nooffer == 0) {
//	  if ($Offer_5 > $Hard_Cap_Space5) {
//		echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_5 in the fifth year of the contract, which is more than $Hard_Cap_Space5, the amount of cap space you have available.<br>";
//		$nooffer=1;
//	  }
//  }
//  if ($nooffer == 0) {
//	  if ($Offer_6 > $Hard_Cap_Space6) {
//		echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_6 in the sixth year of the contract, which is more than $Hard_Cap_Space6, the amount of cap space you have available.<br>";
//		$nooffer=1;
//	  }
//  }

}

// ==== CHECK FOR OFFERS OVER MAX

  if ($nooffer == 0) {
	  if ($Offer_1 > $Year1_Max) {
		echo "Sorry, you tried to offer a contract larger than the maximum allowed for this player based on their years of service.  The maximum you are allowed to offer this player is $Year1_Max in the first year of their contract.<br>";
		$nooffer=1;
	  }
}
// ==== CHECK FOR ILLEGAL RAISES

  if ($nooffer == 0) {
	  if ($Offer_2 > $Offer_1+$Offer_max_increase) {
		$legaloffer=$Offer_1+$Offer_max_increase;
		echo "Sorry, you tried to offer a larger raise than is permitted.  Your first year offer was $Offer_1 which means the maximum raise allowed each year is $Offer_max_increase.  Your offer in Year 2 was $Offer_2, which is more than your Year 1 offer, $Offer_1, plus the max increase of $Offer_max_increase.  Given your offer in Year 1, the most you can offer in Year 2 is $legaloffer.<br>";
		$nooffer=1;
	  }
}
  if ($nooffer == 0) {
	  if ($Offer_3 > $Offer_2+$Offer_max_increase) {
		$legaloffer=$Offer_2+$Offer_max_increase;
		echo "Sorry, you tried to offer a larger raise than is permitted.  Your first year offer was $Offer_1 which means the maximum raise allowed each year is $Offer_max_increase.  Your offer in Year 3 was $Offer_3, which is more than your Year 2 offer, $Offer_2, plus the max increase of $Offer_max_increase.  Given your offer in Year 2, the most you can offer in Year 3 is $legaloffer.<br>";
		$nooffer=1;
	  }
  }
  if ($nooffer == 0) {
	  if ($Offer_4 > $Offer_3+$Offer_max_increase) {
		$legaloffer=$Offer_3+$Offer_max_increase;
		echo "Sorry, you tried to offer a larger raise than is permitted.  Your first year offer was $Offer_1 which means the maximum raise allowed each year is $Offer_max_increase.  Your offer in Year 4 was $Offer_4, which is more than your Year 3 offer, $Offer_3, plus the max increase of $Offer_max_increase.  Given your offer in Year 3, the most you can offer in Year 4 is $legaloffer.<br>";
		$nooffer=1;
	  }
  }
  if ($nooffer == 0) {
	  if ($Offer_5 > $Offer_4+$Offer_max_increase) {
		$legaloffer=$Offer_4+$Offer_max_increase;
		echo "Sorry, you tried to offer a larger raise than is permitted.  Your first year offer was $Offer_1 which means the maximum raise allowed each year is $Offer_max_increase.  Your offer in Year 5 was $Offer_5, which is more than your Year 4 offer, $Offer_4, plus the max increase of $Offer_max_increase.  Given your offer in Year 4, the most you can offer in Year 5 is $legaloffer.<br>";
		$nooffer=1;
		}
  }
  if ($nooffer == 0) {
	  if ($Offer_6 > $Offer_5+$Offer_max_increase) {
		$legaloffer=$Offer_5+$Offer_max_increase;
		echo "Sorry, you tried to offer a larger raise than is permitted.  Your first year offer was $Offer_1 which means the maximum raise allowed each year is $Offer_max_increase.  Your offer in Year 5 was $Offer_5, which is more than your Year 4 offer, $Offer_4, plus the max increase of $Offer_max_increase.  Given your offer in Year 5, the most you can offer in Year 5 is $legaloffer.<br>";
		$nooffer=1;
	  }
  }
// ==== CHECK FOR ILLEGAL LOWERING OF SALARY
if ($nooffer == 0) {
	if ($Offer_2 < $Offer_1) {
	  if ($Offer_2 == 0) {
	  } else {
	  echo "Sorry, you cannot decrease salary in later years of a contract.  You offered $Offer_2 in the second year, which is less than you offered in the first year, $Offer_1.<br>";
	  $nooffer=1;
	  }
	}

}
if ($nooffer == 0) {
	if (($Offer_3 < $Offer_2) AND ($Offer_2 > 0)){
	  if ($Offer_3 == 0) {
	  } else {
	  echo "Sorry, you cannot decrease salary in later years of a contract.  You offered $Offer_3 in the third year, which is less than you offered in the second year, $Offer_2.<br>";
	  $nooffer=1;
	  }
	}
}
if ($nooffer == 0) {
	if ($Offer_4 < $Offer_3) {
	  if ($Offer_4 == 0) {
	  } else {
	  echo "Sorry, you cannot decrease salary in later years of a contract.  You offered $Offer_4 in the fourth year, which is less than you offered in the third year, $Offer_3.<br>";
	  $nooffer=1;
	  }
	}
}
if ($nooffer == 0) {
	if ($Offer_5 < $Offer_4) {
	  if ($Offer_5 == 0) {
	  } else {
	  echo "Sorry, you cannot decrease salary in later years of a contract.  You offered $Offer_5 in the fifth year, which is less than you offered in the fourth year, $Offer_4.<br>";
	  $nooffer=1;
	  }
	}
}
if ($nooffer == 0) {
	if ($Offer_6 < $Offer_5) {
	  if ($Offer_6 == 0) {
	  } else {
	  echo "Sorry, you cannot decrease salary in later years of a contract.  You offered $Offer_6 in the sixth year, which is less than you offered in the fifth year, $Offer_5.<br>";
	  $nooffer=1;
	  }
	}
}
// ==== CHECK FOR OFFERS THAT EXCEED THE HARD CAP ====

  $Hard_Cap_Space=$Cap_Space+2000;
  $Hard_Cap_Space2=$Cap_Space2+2000;
  $Hard_Cap_Space3=$Cap_Space3+2000;
  $Hard_Cap_Space4=$Cap_Space4+2000;
  $Hard_Cap_Space5=$Cap_Space5+2000;
  $Hard_Cap_Space6=$Cap_Space6+2000;
if ($nooffer == 0) {
	if ($Offer_1 > $Hard_Cap_Space) {
	  echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_1 in the first year of the contract, which is more than $Hard_Cap_Space, the amount of hard cap space you have available.<br>";
	  $nooffer=1;
	}
}
// if ($nooffer == 0) {
//	if (($Offer_2 > $Hard_Cap_Space2) AND ($Offer_2 > 0)){
//	  echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_2 in the second year of the contract, which is more than $Hard_Cap_Space2, the amount of hard cap space you have available.<br>";
//	  $nooffer=1;
//	}
//}
//if ($nooffer == 0) {
//	if ($Offer_3 > $Hard_Cap_Space3) {
//	  echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_3 in the third year of the contract, which is more than $Hard_Cap_Space3, the amount of hard cap space you have available.<br>";
//	  $nooffer=1;
//	}
//}
//if ($nooffer == 0) {
//	if ($Offer_4 > $Hard_Cap_Space4) {
//	  echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_4 in the fourth year of the contract, which is more than $Hard_Cap_Space4, the amount of hard cap space you have available.<br>";
//	  $nooffer=1;
//	}
//}
//if ($nooffer == 0) {
//	if ($Offer_5 > $Hard_Cap_Space5) {
//	  echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_5 in the fifth year of the contract, which is more than $Hard_Cap_Space5, the amount of hard cap space you have available.<br>";
//	  $nooffer=1;
//	}
//}
//if ($nooffer == 0) {
//	if ($Offer_6 > $Hard_Cap_Space6) {
//	  echo "Sorry, you do not have sufficient cap space under the Hard Cap to make the offer.  You offered $Offer_6 in the sixth year of the contract, which is more than $Hard_Cap_Space6, the amount of hard cap space you have available.<br>";
//	  $nooffer=1;
//	}
//}
// ==== IF OFFER IS LEGIT, PROCESS OFFER ====

if ($nooffer == 0) {

// ==== ENTER OFFER INTO DATABASE (OR UPDATE IF OFFER ALREADY EXISTS) ====

$querydrop="DELETE FROM `nuke_ibl_fa_offers` WHERE `name` = '$Player_Name' AND `team` = '$Team_Name' LIMIT 1";
$resultdrop=$db->sql_query($querydrop);

$querychunk="INSERT INTO `nuke_ibl_fa_offers` ( `name` , `team` , `offer1` , `offer2` , `offer3` , `offer4` , `offer5` , `offer6` , `modifier` , `random` , `perceivedvalue` , `MLE` , `LLE` )
VALUES ( '$Player_Name', '$Team_Name', '$Offer_1', '$Offer_2', '$Offer_3', '$Offer_4', '$Offer_5', '$Offer_6', '$modifier', '$random', '$perceivedvalue', '$MLE', '$LLE' )";

$resultchunk=$db->sql_query($querychunk);

echo "Your offer is legal, and has been entered into the system.  It should show up immediately.  Please <a href=\"modules.php?name=Free_Agency\">click here to return to the Free Agency main page</a> (your offer should now be visible).</br>";

} else {

echo "<font color=#ff0000>Your offer was not legal, and will not be recorded. You may press the \"Back\" Button on your browser to try again.</font>";

}

?>
