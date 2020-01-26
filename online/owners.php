<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_ibl5";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$query="SELECT * FROM nuke_ibl_team_info ORDER BY team_city ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$i=0;

echo "<HTML><HEAD>
</STYLE><TITLE>IBL Owners Contact Info Page</TITLE></HEAD>
<BODY>
<CENTER><h2>IBL Owners Page</h2>
Click on a team name to access that team's page; click on an owner name to e-mail the owner.<br>
<TABLE CELLPADDING=1 BORDER=1><TR>
<TH>Team</TH><TH>Owner</TH><TH>AIM</TH><TH>Skype</TH></TR>
";

while ($i < $num)
{
$tid=mysql_result($result,$i,"teamid");
$teamcity=mysql_result($result,$i,"team_city");
$teamname=mysql_result($result,$i,"team_name");
$color1=mysql_result($result,$i,"color1");
$color2=mysql_result($result,$i,"color2");
$owner=mysql_result($result,$i,"owner_name");
$email=mysql_result($result,$i,"owner_email");
$skype=mysql_result($result,$i,"skype");
$aim=mysql_result($result,$i,"aim");
$msn=mysql_result($result,$i,"msn");

echo "<tr><td bgcolor=#$color1><center><a href=\"team.php?tid=$tid\"><font color=#$color2>$teamcity $teamname</font></a></center></td><td><center><a href=\"mailto:$email\">$owner</center></a></td><td><center>$aim</center></td><td><center>$skype</center></td></tr>
";

$i++;

}

echo "</TR></TABLE>
</BODY></HTML>";

mysql_close();

?>