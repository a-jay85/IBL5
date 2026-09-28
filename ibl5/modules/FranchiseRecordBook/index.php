<?php

declare(strict_types=1);

/**
 * FranchiseRecordBook Module - HTMX fragment API for the Records page's By Franchise tab
 *
 * op=api serves the team-switch fragment. Every other request redirects to
 * the Records page.
 *
 * @see FranchiseRecordBook\FranchiseRecordBookApiHandler For the HTMX fragment
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

global $mysqli_db;

// Route HTMX API requests (no PageLayout, returns HTML fragment only)
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : '';
if ($op === 'api') {
    $handler = new FranchiseRecordBook\FranchiseRecordBookApiHandler($mysqli_db);
    $handler->handle();
    return;
}

\Module\ModuleRedirect::sendWithPassthrough(
    'modules.php?name=Records&tab=' . \Records\RecordsController::TAB_BYFRANCHISE,
    ['teamid'],
    $_GET + $_POST,
    ['teamid' => 'ctype_digit']
);
return;
