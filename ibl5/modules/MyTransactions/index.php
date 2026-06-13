<?php

declare(strict_types=1);

/**
 * My Team Transactions Module - the logged-in GM's own team ledger.
 *
 * Read-only view of the GM's own team's transaction history (waiver moves,
 * trades, extensions, FA signings, rookie extensions, position changes) plus
 * outstanding trade offers and FA bids. Team identity is resolved server-side
 * from the logged-in username — the module never reads a team parameter, so a
 * GM can only ever see their own team.
 *
 * @see MyTransactions\MyTransactionsService For orchestration / identity resolution
 * @see MyTransactions\MyTransactionsView For HTML rendering
 */

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = '- My Team Transactions';

global $mysqli_db;
global $user;
global $authService;

// Auth gate: logged-out users get the login box (mirrors Trading/FreeAgency).
$nukeCompat = new \Utilities\NukeCompat();
if (!$nukeCompat->isUser($user)) {
    $nukeCompat->loginBox();
    return;
}

// Identity comes ONLY from the session username — never from request input.
$username = $authService->getUsername();

$teamIdentityRepo = new \Repositories\TeamIdentityRepository($mysqli_db);
$transactionRepo = new \TransactionHistory\TransactionHistoryRepository($mysqli_db);
$faRepo = new \FreeAgency\FreeAgencyRepository($mysqli_db);
$offerRepo = new \Trading\TradeOfferRepository($mysqli_db, $_SERVER['SERVER_NAME'] ?? '');

$service = new \MyTransactions\MyTransactionsService(
    $transactionRepo,
    $offerRepo,
    $faRepo,
    $teamIdentityRepo
);
$view = new \MyTransactions\MyTransactionsView();

$pageData = $service->getPageData($username);

PageLayout\PageLayout::header();
echo $view->render($pageData);
PageLayout\PageLayout::footer();
