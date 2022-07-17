<?php

require 'mainfile.php';

$Team_Name = $_POST['teamname'];
$Player_Name = $_POST['playername'];
$Position = $_POST['NewPos'];

$recipient = 'ibldepthcharts@gmail.com';
$emailsubject = "Position Migration - " . $Player_Name;
$filetext = $Team_Name . " migrates " . $Player_Name . " to " . $Position . ".";

$querymigrate = "UPDATE nuke_iblplyr SET altpos = '$Position' WHERE name = '$Player_Name'";
$resultmigrate = $db->sql_query($querymigrate);

echo "<html><head><title>Position Migration Processing</title></head><body>

Your position migration request has been processed and updated in the database.  It should show up on the Free Agency page immediately.  Please <a href=\"modules.php?name=Free_Agency\">click here to return to the Free Agency Screen</a>.<br><br>
";

if (mail($recipient, $emailsubject, $filetext, "From: migration@iblhoops.net")) {
    echo "<center> An e-mail regarding this extension has been successfully sent to the commissioner's office.  Thank you. </center>";
} else {
    echo " Message failed to e-mail properly; please notify the commissioner of the error.</center>";
}
