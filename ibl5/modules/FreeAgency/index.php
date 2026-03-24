<?php

declare(strict_types=1);

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

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Free Agency System";

function main($user)
{
    if (!is_user($user)) {
        loginbox();
    } else {
        display();
    }
}

function display()
{
    global $mysqli_db, $cookie;
    $commonRepository = new Services\CommonMysqliRepository($mysqli_db);
    $season = new \Season\Season($mysqli_db);

    PageLayout\PageLayout::header();

    $username = strval($cookie[1] ?? '');
    $teamName = $commonRepository->getTeamnameFromUsername($username) ?? '';
    $team = \Team\Team::initialize($mysqli_db, $teamName);

    // Service assembles data, view renders it
    $repository = new FreeAgencyRepository($mysqli_db);
    $demandRepository = new FreeAgencyDemandRepository($mysqli_db);
    $service = new FreeAgencyService($repository, $demandRepository, $mysqli_db);
    $view = new FreeAgencyView($mysqli_db);

    $mainPageData = $service->getMainPageData($team, $season);
    $result = $_GET['result'] ?? null;
    echo $view->render($mainPageData, $result);

    PageLayout\PageLayout::footer();
}

function negotiate($pid)
{
    global $cookie, $mysqli_db;
    $commonRepository = new Services\CommonMysqliRepository($mysqli_db);

    $pid = intval($pid);

    PageLayout\PageLayout::header();

    // Get user team information (must be after header() which populates $cookie)
    $username = strval($cookie[1] ?? '');
    $userTeamName = $commonRepository->getTeamnameFromUsername($username) ?? '';
    $teamID = $commonRepository->getTidFromTeamname($userTeamName) ?? 0;

    $team = \Team\Team::initialize($mysqli_db, $teamID);
    $season = new \Season\Season($mysqli_db);

    // Service assembles data, view renders it
    $repository = new FreeAgencyRepository($mysqli_db);
    $demandRepository = new FreeAgencyDemandRepository($mysqli_db);
    $service = new FreeAgencyService($repository, $demandRepository, $mysqli_db);

    $negotiationData = $service->getNegotiationData($pid, $team, $season);
    $negotiationData['team'] = $team;

    // FormComponents needs the player and team name for rendering
    $formComponents = new FreeAgencyFormComponents($team->name, $negotiationData['player']);
    $view = new FreeAgencyNegotiationView($formComponents);
    $error = $_GET['error'] ?? null;
    echo $view->render($negotiationData, $error);

    PageLayout\PageLayout::footer();
}

function processOffer()
{
    global $mysqli_db;
    $processor = new FreeAgencyProcessor($mysqli_db);
    $result = $processor->processOfferSubmission($_POST);
    $pid = $result['playerID'];

    if ($result['success']) {
        \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=offer_success');
    } elseif ($result['type'] === 'already_signed') {
        \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=already_signed');
    } else {
        \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&pa=negotiate&pid=' . $pid . '&error=' . rawurlencode($result['message']));
    }
}

function deleteOffer()
{
    global $mysqli_db;
    $processor = new FreeAgencyProcessor($mysqli_db);
    $playerID = (int) ($_POST['playerID'] ?? 0);
    $processor->deleteOffers((string) ($_POST['teamname'] ?? ''), $playerID);
    \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=deleted');
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
