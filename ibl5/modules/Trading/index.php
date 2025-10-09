<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

function menu()
{
    global $db;

    Nuke\Header::header();
    OpenTable();

    UI::displaytopmenu($db, 0);

    CloseTable();
    Nuke\Footer::footer();
}

function reviewtrade($user)
{
    global $db;
    $controller = new Trading_TradeController($db);
    $controller->routeToTradeReview($user);
}

function offertrade($user)
{
    global $db, $partner;
    $controller = new Trading_TradeController($db);
    $controller->routeToTradeOffer($user, $partner);
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
