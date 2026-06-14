<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Module inputs are read from $_REQUEST explicitly (ConfigBootstrap no longer
// extracts request keys to globals). Leave $op null so the handleRequest()
// 'browse' fallback applies for the bare module URL.
$op = (is_string($_REQUEST['op'] ?? null) && $_REQUEST['op'] !== '') ? $_REQUEST['op'] : null;

$pagetitle = "- Trade Block";

/**
 * Main entry point for the Trade Block module.
 *
 * @param mixed $user Current user
 */
function tradeBlock($user)
{
    global $mysqli_db, $op;

    cookiedecode($user);

    $repo = new TradeBlock\TradeBlockRepository($mysqli_db);
    $teamIdentityRepo = new Repositories\TeamIdentityRepository($mysqli_db);
    $teamQueryRepo = new Team\TeamQueryRepository($mysqli_db);
    $validator = new TradeBlock\TradeBlockValidator();
    $processor = new TradeBlock\TradeBlockProcessor($repo, $teamQueryRepo);
    $view = new TradeBlock\TradeBlockView();
    $service = new TradeBlock\TradeBlockService($repo, $teamQueryRepo, $mysqli_db);
    $nukeCompat = new Utilities\NukeCompat();
    $controller = new TradeBlock\TradeBlockController(
        $service,
        $processor,
        $view,
        $validator,
        $teamIdentityRepo,
        $nukeCompat
    );
    $controller->handleRequest($user, $op ?? 'browse');
}

tradeBlock($user);
