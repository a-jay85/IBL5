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
    global $db, $cookie;
    $commonRepository = new Services\CommonRepository($db);
    $season = new Season($db);

    Nuke\Header::header();
    OpenTable();

    $username = $cookie[1];
    $teamName = $commonRepository->getTeamnameFromUsername($username);
    $team = Team::initialize($db, $teamName);

    UI::displaytopmenu($db, $team->teamID);

    $displayHelper = new FreeAgencyDisplayHelper($db, $team, $season);
    echo $displayHelper->renderMainPage();

    CloseTable();
    Nuke\Footer::footer();
}

function negotiate($pid)
{
    global $db, $cookie;
    $commonRepository = new Services\CommonRepository($db);

    $pid = intval($pid);

    // Get user team information
    $username = $cookie[1];
    $userTeamName = $commonRepository->getTeamnameFromUsername($username);
    $teamID = $commonRepository->getTidFromTeamname($userTeamName);

    Nuke\Header::header();
    OpenTable();

    $team = \Team::initialize($db, $teamID);
    $season = new Season($db);
    $negotiationHelper = new FreeAgencyNegotiationHelper($db, $season);
    echo $negotiationHelper->renderNegotiationPage($pid, $team);

    CloseTable();
    Nuke\Footer::footer();
}

function processOffer()
{
    global $db;
    $processor = new FreeAgencyProcessor($db);
    echo $processor->processOfferSubmission($_POST);
}

function deleteOffer()
{
    global $db;
    $processor = new FreeAgencyProcessor($db);
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
