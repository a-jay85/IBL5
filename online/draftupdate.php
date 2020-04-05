<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$name = NULL;
$team = NULL;

$name=$_POST["name"];
$team=$_POST["team"];

if ($name == NULL)
{
echo "<html><head><title>Draft Update Page</title></head><body>
Please enter the name of the player drafted (spelling must match exactly) and the team drafting the player into the boxes provided below.  It will automatically update the Draft Declarants page.

<form action=\"draftupdate.php\" method=\"POST\">
Enter the Player's Name: <input type=\"text\" name=\"name\" />
Enter the Team Drafting: <input type=\"text\" name=\"team\" />
<input type=\"submit\" name=\"UPDATE!\" />
</form>
<center><a href=\"http://college.ibl.net/draftdeclarants.php\">Link to Draft Declarants Page</a> (to easily check results)</center>


";

} else {
if ($team == NULL)
{
echo "<html><head><title>Draft Update Page</title></head><body>
A player name was entered, but no team was entered. Please enter the name of the player drafted (spelling must match exactly) and the team drafting the player into the boxes provided below.  It will automatically update the Draft Declarants page.

<form action=\"draftupdate.php\" method=\"POST\">
Enter the Player's Name: <input type=\"text\" name=\"name\" />
Enter the Team Drafting: <input type=\"text\" name=\"team\" />
<input type=\"submit\" name=\"UPDATE!\" />
</form>
<center><a href=\"http://college.ibl.net/draftdeclarants.php\">Link to Draft Declarants Page</a> (to easily check results)</center>

";

} else {

$query="UPDATE `college_players2` SET `draftedby` = '$team' WHERE `name` = '$name'";
$result=mysql_query($query);

$query2="UPDATE `nuke_scout_rookieratings` SET `drafted` = '1' WHERE `name` = '$name'";
$result2=mysql_query($query2);


echo "<html><head><title>Draft Update Page</title></head><body>";
if ($result) {
echo "<center><font color=#ff0000>$name should now be listed as having been drafted by the $team.</font></center></p>";
} else {
echo "<center><font color=#ff0000>No updates were made; either $name is already listed as being drafted by $team or $name is not a player in the database (possibly due to misspelling of the name).</font></center></p>";
}

echo "Please enter the name of the player drafted (spelling must match exactly) and the team drafting the player into the boxes provided below.  It will automatically update the Draft Declarants page.

<form action=\"draftupdate.php\" method=\"POST\">
Enter the Player's Name: <input type=\"text\" name=\"name\" />
Enter the Team Drafting: <input type=\"text\" name=\"team\" />
<input type=\"submit\" name=\"UPDATE!\" />
</form>
<center><a href=\"http://college.ibl.net/draftdeclarants.php\">Link to Draft Declarants Page</a> (to easily check results)</center>

";

}
}


mysql_close();

echo "</table></center></body></html>";

?>