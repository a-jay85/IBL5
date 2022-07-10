<?php

require 'mainfile.php';

$queryo="SELECT * FROM nuke_users WHERE user_ibl_team != '' ORDER BY user_ibl_team ASC";
$resulto=$db->sql_query($queryo);
$numo=$db->sql_numrows($resulto);

echo "<table border=1><tr><td><b>TEAM NAME</td><td><b>HEALTHY PLAYERS</td><td><b>WAIVERS NEEDED</td><td><b>NEW LINEUP NEEDED</td></tr>";

$j=0;
while ($j < $numo)
{
	$user_team=$db->sql_result($resulto,$j,"user_ibl_team");

	$sql="SELECT * FROM nuke_iblplyr WHERE teamname='$user_team' AND retired = '0' AND ordinal < '961' AND injured = '0' ORDER BY ordinal ASC ";
	$result1 = $db->sql_query($sql);
	$num1=$db->sql_numrows($result1);

	$sql2="SELECT * FROM nuke_iblplyr WHERE teamname='$user_team' AND retired = '0' AND ordinal < '961' AND injured > '0' AND active = '1' ORDER BY ordinal ASC ";
	$result2 = $db->sql_query($sql2);
	$num2=$db->sql_numrows($result2);

	if ($num2>0)
	{
		$new_lineups='Yes';
	}else{
		$new_lineups='No';
	}

	$waivers_needed=12;
	$healthy=0;
	$i=0;
	while($i < $num1)
	{
		$healthy++;
		$i++;
	}

	$waivers_needed = $waivers_needed - $healthy;
	if ($waivers_needed < 0)
	{
		$waivers_needed=0;
	}
	echo "<tr><td>$user_team</td><td>$healthy</td><td>$waivers_needed</td><td>$new_lineups</td>

</tr>";
	$j++;
}



?>
