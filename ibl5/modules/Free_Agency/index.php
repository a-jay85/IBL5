<?php

use FreeAgency\FreeAgencyRepository;
use FreeAgency\FreeAgencyDemandRepository;
use FreeAgency\FreeAgencyService;
use FreeAgency\FreeAgencyView;
use FreeAgency\FreeAgencyNegotiationView;
use FreeAgency\FreeAgencyFormComponents;
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
    global $mysqli_db, $cookie;
    $commonRepository = new Services\CommonMysqliRepository($mysqli_db);
    $season = new Season($mysqli_db);

    Nuke\Header::header();
    OpenTable();

    $username = strval($cookie[1] ?? '');
    $teamName = $commonRepository->getTeamnameFromUsername($username);
    $team = Team::initialize($mysqli_db, $teamName);

    // Service assembles data, view renders it
    $repository = new FreeAgencyRepository($mysqli_db);
    $demandRepository = new FreeAgencyDemandRepository($mysqli_db);
    $service = new FreeAgencyService($repository, $demandRepository, $mysqli_db);
    $view = new FreeAgencyView($mysqli_db);

    $mainPageData = $service->getMainPageData($team, $season);
    echo $view->render($mainPageData);

    CloseTable();
    Nuke\Footer::footer();
}

function negotiate($pid)
{
    global $cookie, $mysqli_db;
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

    // Service assembles data, view renders it
    $repository = new FreeAgencyRepository($mysqli_db);
    $demandRepository = new FreeAgencyDemandRepository($mysqli_db);
    $service = new FreeAgencyService($repository, $demandRepository, $mysqli_db);

    $negotiationData = $service->getNegotiationData($pid, $team, $season);
    $negotiationData['team'] = $team;

    // FormComponents needs the player and team name for rendering
    $formComponents = new FreeAgencyFormComponents($team->name, $negotiationData['player']);
    $view = new FreeAgencyNegotiationView($formComponents);
    echo $view->render($negotiationData);

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
