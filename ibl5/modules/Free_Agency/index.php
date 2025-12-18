<?php

use Player\Player;
use FreeAgency\FreeAgencyDisplayHelper;
use FreeAgency\FreeAgencyNegotiationHelper;
use FreeAgency\FreeAgencyProcessor;

/************************************************************************/
/*                     IBL Free Agency Module                           */
/*               (c) July 22, 2005 by Spencer Cooley                    */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Free Agency System";

function main($user)
{
    global $stop;
    if (!is_user($user)) {
        Nuke\Header::header();
        OpenTable();
        echo "<center><font class=\"title\"><b>" . ($stop ? _LOGININCOR : _USERREGLOGIN) . "</b></font></center>";
        CloseTable();
        echo "<br>";
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        display();
    }
}

function display()
{
    global $db, $mysqli_db, $cookie;
    $commonRepository = new Services\CommonMysqliRepository($mysqli_db);
    $season = new Season($mysqli_db);

    Nuke\Header::header();
    OpenTable();

    $username = strval($cookie[1] ?? '');
    $teamName = $commonRepository->getTeamnameFromUsername($username);
    $team = Team::initialize($mysqli_db, $teamName);

    UI::displaytopmenu($mysqli_db, $team->teamID);

    $displayHelper = new FreeAgencyDisplayHelper($mysqli_db, $team, $season);
    echo $displayHelper->renderMainPage();

    CloseTable();
    Nuke\Footer::footer();
}

function negotiate($pid)
{
    global $db, $cookie, $mysqli_db;
    $commonRepository = new Services\CommonMysqliRepository($mysqli_db);

    $pid = intval($pid);

    // Get user team information
    $username = strval($cookie[1] ?? '');
    $userTeamName = $commonRepository->getTeamnameFromUsername($username);
    $teamID = $commonRepository->getTidFromTeamname($userTeamName);

    Nuke\Header::header();
    OpenTable();

    $team = \Team::initialize($mysqli_db, $teamID);
    $season = new Season($mysqli_db);
    $negotiationHelper = new FreeAgencyNegotiationHelper($mysqli_db, $season);
    echo $negotiationHelper->renderNegotiationPage($pid, $team);

    CloseTable();
    Nuke\Footer::footer();
}

function processOffer()
{
    global $mysqli_db;
    $processor = new FreeAgencyProcessor($mysqli_db);
    echo $processor->processOfferSubmission($_POST);
}

function deleteOffer()
{
    global $mysqli_db;
    $processor = new FreeAgencyProcessor($mysqli_db);
    $playerID = (int) ($_POST['playerID'] ?? 0);
    echo $processor->deleteOffers($_POST['teamname'], $playerID);
}

switch ($pa) {
    case 'negotiate':
        negotiate($pid);
        break;
    case 'processoffer':
        processOffer();
        break;
    case 'deleteoffer':
        deleteOffer();
        break;
    default:
        main($user);
}
