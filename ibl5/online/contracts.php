<?php

require '../config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$query="SELECT * FROM nuke_iblplyr WHERE retired = 0 ORDER BY ordinal ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

echo "<html><head><title>Master Contract List</title></head><body><table>
   <tr><th colspan=18><center>Contracts</center></th></tr>
<tr><th>Pos</th><th colspan=3>Player</th><th>Bird</th><th>Year1</th><th>Year2</th><th>Year3</th><th>Year4</th><th>Year5</th><th>Year6</th><td bgcolor=#000000 width=3></td><th>Team</th></tr>";

/* =======================CONTRACTS ET AL */

$i=0;
$cap1=0;
$cap2=0;
$cap3=0;
$cap4=0;
$cap5=0;
$cap6=0;

while ($i < $num)
{

$team=mysql_result($result,$i,"teamname");
$name=mysql_result($result,$i,"name");
$pos=mysql_result($result,$i,"pos");

$cy=mysql_result($result,$i,"cy");
$cyt=mysql_result($result,$i,"cyt");

if ($faon == 0) {
$year1=$cy;
$year2=$cy+1;
$year3=$cy+2;
$year4=$cy+3;
$year5=$cy+4;
$year6=$cy+5;
} else {
$year1=$cy+1;
$year2=$cy+2;
$year3=$cy+3;
$year4=$cy+4;
$year5=$cy+5;
$year6=$cy+6;
}

if ($cy==0)
{

if ($year1 < 7)
{
$con1=mysql_result($result,$i,"cy1");
} else {
$con1=0;
}

if ($year2 < 7)
{
$con2=mysql_result($result,$i,"cy2");
} else {
$con2=0;
}

if ($year3 < 7)
{
$con3=mysql_result($result,$i,"cy3");
} else {
$con3=0;
}

if ($year4 < 7)
{
$con4=mysql_result($result,$i,"cy4");
} else {
$con4=0;
}

if ($year5 < 7)
{
$con5=mysql_result($result,$i,"cy5");
} else {
$con5=0;
}

if ($year6 < 7)
{
$con6=mysql_result($result,$i,"cy6");
} else {
$con6=0;
}

} else {
if ($year1 < 7)
{
$con1=mysql_result($result,$i,"cy$year1");
} else {
$con1=0;
}

if ($year2 < 7)
{
$con2=mysql_result($result,$i,"cy$year2");
} else {
$con2=0;
}

if ($year3 < 7)
{
$con3=mysql_result($result,$i,"cy$year3");
} else {
$con3=0;
}

if ($year4 < 7)
{
$con4=mysql_result($result,$i,"cy$year4");
} else {
$con4=0;
}

if ($year5 < 7)
{
$con5=mysql_result($result,$i,"cy$year5");
} else {
$con5=0;
}

if ($year6 < 7)
{
$con6=mysql_result($result,$i,"cy$year6");
} else {
$con6=0;
}
}

$bird=mysql_result($result,$i,"bird");

if ($i % 2)
{
echo "      <tr bgcolor=#ffffff align=center>";
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
}
echo "      <td>$pos</td><td colspan=3>$name</td><td>$bird</td><td>$con1</td><td>$con2</td><td>$con3</td><td>$con4</td><td>$con5</td><td>$con6</td><td bgcolor=#000000></td><td>$team</td></tr>";

$cap1=$cap1+$con1;
$cap2=$cap2+$con2;
$cap3=$cap3+$con3;
$cap4=$cap4+$con4;
$cap5=$cap5+$con5;
$cap6=$cap6+$con6;

$i++;

}

$acap1=$cap1/2800;
$acap2=$cap2/2800;
$acap3=$cap3/2800;
$acap4=$cap4/2800;
$acap5=$cap5/2800;
$acap6=$cap6/2800;

$cap1=$cap1/100;
$cap2=$cap2/100;
$cap3=$cap3/100;
$cap4=$cap4/100;
$cap5=$cap5/100;
$cap6=$cap6/100;

echo "      <tr bgcolor=#bbbbff><td></td><td colspan=3>Cap Totals</td><td></td><td>";
printf('%01.2f', $cap1);
echo "</td><td>";
printf('%01.2f', $cap2);
echo "</td><td>";
printf('%01.2f', $cap3);
echo "</td><td>";
printf('%01.2f', $cap4);
echo "</td><td>";
printf('%01.2f', $cap5);
echo "</td><td>";
printf('%01.2f', $cap6);

echo "</td><td></td></tr>";

echo "      <tr bgcolor=#bbbbff><td></td><td colspan=3>Average Team Cap</td><td></td><td>";
printf('%01.2f', $acap1);
echo "</td><td>";
printf('%01.2f', $acap2);
echo "</td><td>";
printf('%01.2f', $acap3);
echo "</td><td>";
printf('%01.2f', $acap4);
echo "</td><td>";
printf('%01.2f', $acap5);
echo "</td><td>";
printf('%01.2f', $acap6);

echo "</td><td></td></tr>
</table>
</body></html>";

mysql_close();

?>
