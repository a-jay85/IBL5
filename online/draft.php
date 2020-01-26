<?php


$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_ibl5";


mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");


$queryfirstyear="SELECT draftyear FROM nuke_iblplyr ORDER BY draftyear ASC";
$resultfirstyear=mysql_query($queryfirstyear);


$startyear=mysql_result($resultfirstyear,0,"draftyear");


$querylastyear="SELECT draftyear FROM nuke_iblplyr ORDER BY draftyear DESC";
$resultlastyear=mysql_query($querylastyear);


$endyear=mysql_result($resultlastyear,0,"draftyear");


$year = $_REQUEST['year'];


$query="SELECT * FROM nuke_iblplyr WHERE draftyear = '$year' AND draftround > 0 ORDER BY draftround, draftpickno ASC";


$result=mysql_query($query);
$num=mysql_numrows($result);


echo "<html><head><title>Overview of $year IBL Draft</title></head><body>
<style>th{ font-size: 9pt; font-family:Arial; color:white; background-color: navy}td      { text-align: Left; font-size: 9pt; font-family:Arial; color:black; }.tdp { font-weight: bold; text-align: Left; font-size: 9pt; color:black; } </style>
";


echo "<center><h2>$year Draft</h2>
";


while ($startyear < $endyear+1)
{
echo "<a href=\"draft.php?year=$startyear\">$startyear</a> |
";
$startyear++;
}


if ($num == 0)
{
echo "<br><br> Please select a draft year.";
} else {
echo "<table>
<th>ROUND</th><th>PICK</th><th>Player</th><th>Selected by</th><th>Pic</th><th>College</th></tr>
";


$i=0;




while ($i < $num)
{
$draftedby=mysql_result($result,$i,"draftedby");
$name=mysql_result($result,$i,"name");
$pid=mysql_result($result,$i,"pid");
$round=mysql_result($result,$i,"draftround");
$draftpickno=mysql_result($result,$i,"draftpickno");
$college=mysql_result($result,$i,"college");
$collegeid=mysql_result($result,$i,"collegeid");


if ($i % 2)
{
echo "<tr bgcolor=#ffffff>";
} else {
echo "<tr bgcolor=#e6e7e2>";
}


echo "<td>$round</td><td>$draftpickno</td><td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td>$draftedby</td><td><img height=50 src=\"../images/player/$pid.jpg\"></td><td><a href=\"http://college.ijbl.net/rosters/roster$collegid.htm\">$college</a></td></tr>
";


$i++;
}
}


mysql_close();


echo "</table></center></body></html>";


?>