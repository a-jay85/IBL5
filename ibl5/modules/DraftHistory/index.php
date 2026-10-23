<?php

declare(strict_types=1);

// Consolidated into modules/DraftInfo (tab: history). Kept so old links still resolve.
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

global $mysqli_db;
/** @var \mysqli $mysqli_db */

// Keep serving op=api HTMX fast path — a redirect here would swap a full page layout
// into the HTMX target. Route directly to the handler before the redirect.
$getOp = is_string($_GET['op'] ?? null) ? $_GET['op'] : '';
if ($getOp === 'api') {
    $handler = new DraftHistory\DraftHistoryApiHandler($mysqli_db);
    $handler->handle();
    return;
}

// Old Discord links use camelCase ?teamID=N; accept it alongside ?teamid=N.
if (!isset($_GET['teamid']) && isset($_GET['teamID'])) {
    $_GET['teamid'] = $_GET['teamID'];
}

\Module\ModuleRedirect::sendWithPassthrough(
    'modules.php?name=DraftInfo&tab=history',
    ['year', 'teamid'],
    $_GET + $_POST,
    ['year' => 'ctype_digit', 'teamid' => 'ctype_digit']
);
return;
