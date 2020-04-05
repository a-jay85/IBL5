<?php

require '../config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$query="SELECT * FROM nuke_iblplyr WHERE `retired` = '0' ORDER BY ordinal ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$i=0;
echo "<table><tr><td>Name</td><td>Year</td><td>Playoff Minures</td></tr>";
while ($i < $num)
{
	$name=mysql_result($result,$i,"name");
	$query2="SELECT * FROM nuke_ibl_playoff_stats WHERE name='$name' ORDER BY year ASC";
	$result2=mysql_query($query2);
	$num2=mysql_numrows($result2);

	$j=0;
	$total_minutes=0;
	while ($j < $num2)
	{
		$year=mysql_result($result2,$j,"year");
		$minutes=mysql_result($result2,$j,"minutes");
		$total_minutes=$total_minutes+$minutes;
		echo "<tr><td>$name</td><td>$year</td><td>$minutes</td></tr>";
		$j++;
	}
	echo "<tr><td></td><td></td><td>$total_minutes</td></tr>";
	echo "Updating $name's records... $total_minutes total minutes.<br>";
	$query3="UPDATE nuke_iblplyr SET car_playoff_min = '$total_minutes' WHERE name = '$name'";
	$result3=mysql_query($query3);

	$i++;
}



?>
