<?php

use Trading\TradingRepository;
use Trading\TradingService;
use Trading\TradingView;

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

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

    // Restore previous form selections from session (after a failed trade attempt)
    $pageData['previousFormData'] = $_SESSION['tradeFormData'] ?? null;
    unset($_SESSION['tradeFormData']);

    PageLayout\PageLayout::header();
    echo $view->renderTradeOfferForm($pageData);
    PageLayout\PageLayout::footer();
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

    PageLayout\PageLayout::header();
    echo $view->renderTradeReview($pageData);
    PageLayout\PageLayout::footer();
}

function reviewtrade($user)
{
    global $mysqli_db;
    $season = new Season($mysqli_db);

    if (!is_user($user)) {
        loginbox();
    } else {
        if ($season->allowTrades === 'Yes') {
            global $cookie;
            cookiedecode($user);
            tradereview(strval($cookie[1] ?? ''));
        } else {
            $view = new TradingView();
            PageLayout\PageLayout::header();
            echo $view->renderTradesClosed($season);
            PageLayout\PageLayout::footer();
        }
    }
}

function offertrade($user)
{
    if (!is_user($user)) {
        loginbox();
    } else {
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
        reviewtrade($user);
        break;
}
