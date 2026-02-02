<?php

use Trading\TradingRepository;
use Trading\TradingService;
use Trading\TradingView;

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

function menu()
{
    Nuke\Header::header();
    Nuke\Footer::footer();
}

function tradeoffer($username)
{
    global $partner, $mysqli_db;

    $repository = new TradingRepository($mysqli_db);
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    $service = new TradingService($repository, $commonRepository, $mysqli_db);
    $view = new TradingView();

    $pageData = $service->getTradeOfferPageData($username, $partner);
    $pageData['result'] = $_GET['result'] ?? null;
    $pageData['error'] = $_GET['error'] ?? null;

    Nuke\Header::header();
    echo $view->renderTradeOfferForm($pageData);
    Nuke\Footer::footer();
}

function tradereview($username)
{
    global $mysqli_db;

    $repository = new TradingRepository($mysqli_db);
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    $service = new TradingService($repository, $commonRepository, $mysqli_db);
    $view = new TradingView();

    $pageData = $service->getTradeReviewPageData($username);
    $pageData['result'] = $_GET['result'] ?? null;
    $pageData['error'] = $_GET['error'] ?? null;

    Nuke\Header::header();
    echo $view->renderTradeReview($pageData);
    Nuke\Footer::footer();
}

function reviewtrade($user)
{
    global $stop, $mysqli_db;
    $season = new Season($mysqli_db);

    if (!is_user($user)) {
        Nuke\Header::header();
        if ($stop) {
            echo '<div style="text-align: center;"><span class="title"><strong>' . _LOGININCOR . '</strong></span></div>' . "\n";
        } else {
            echo '<div style="text-align: center;"><span class="title"><strong>' . _USERREGLOGIN . '</strong></span></div>' . "\n";
        }
        if (!is_user($user)) {
            loginbox();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        if ($season->allowTrades === 'Yes') {
            global $cookie;
            cookiedecode($user);
            tradereview(strval($cookie[1] ?? ''));
        } else {
            $view = new TradingView();
            Nuke\Header::header();
            echo $view->renderTradesClosed($season);
            Nuke\Footer::footer();
        }
    }
}

function offertrade($user)
{
    global $stop;

    if (!is_user($user)) {
        Nuke\Header::header();
        if ($stop) {
            echo '<div style="text-align: center;"><span class="title"><strong>' . _LOGININCOR . '</strong></span></div>' . "\n";
        } else {
            echo '<div style="text-align: center;"><span class="title"><strong>' . _USERREGLOGIN . '</strong></span></div>' . "\n";
        }
        if (!is_user($user)) {
            loginbox();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        tradeoffer(strval($cookie[1] ?? ''));
    }
}

switch ($op) {
    case "reviewtrade":
        reviewtrade($user);
        break;

    case "offertrade":
        offertrade($user);
        break;

    default:
        menu();
        break;
}
