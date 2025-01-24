<?php

global $db, $cookie;
$sharedFunctions = new Shared($db);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

$username = $cookie[1];
$userTeam = Team::initialize($db, $sharedFunctions->getTeamnameFromUsername($username));

?>

<?php
    Nuke\Header::header();
    OpenTable();
?>

<center>
    <h1>Next Sim</h1>

    <table>
        <tr></tr>
    </table>

<?php
    CloseTable();
    Nuke\Footer::footer();
?>