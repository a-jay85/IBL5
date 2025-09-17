<?php

require '../../mainfile.php';

$name = null;
$team = null;

$name = $_POST["name"];
$team = $_POST["team"];

if ($name == null) {
    echo "<html><head><title>Draft Update Page</title></head><body>
    Please enter the name of the player drafted (spelling must match exactly) and the team drafting the player into the boxes provided below.  It will automatically update the Draft Declarants page.

    <form action=\"draftupdate.php\" method=\"POST\">
    Enter the Player's Name: <input type=\"text\" name=\"name\" />
    <input type=\"submit\" name=\"UPDATE!\" />
    </form>";
} else {
    $query2 = "UPDATE `ibl_scout_rookieratings` SET `drafted` = '1' WHERE `name` = '$name'";
    $result2 = $db->sql_query($query2);

    echo "<html><head><title>Draft Update Page</title></head><body>";
    if ($result2) {
        echo "<center><font color=#ff0000>$name should now be listed as having been drafted.</font></center></p>";
    } else {
        echo "<center><font color=#ff0000>No updates were made; $name is not a player in the database (possibly due to misspelling of the name).</font></center></p>";
    }

    echo "Please enter the name of the player drafted (spelling must match exactly) into the boxes provided below.  It will update the Draft Declarants page.

    <form action=\"draftupdate.php\" method=\"POST\">
    Enter the Player's Name: <input type=\"text\" name=\"name\" />
    <input type=\"submit\" name=\"UPDATE!\" />
    </form>";
}

$db->sql_close();

echo "</table></center></body></html>";
