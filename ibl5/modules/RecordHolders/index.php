<?php

declare(strict_types=1);

/**
 * Retired module: redirects to the Records page (All-Time tab) or AllStarAppearances.
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

if (($_GET['op'] ?? null) === 'allstar') {
    \Module\ModuleRedirect::sendWithPassthrough('modules.php?name=AllStarAppearances', [], $_GET + $_POST);
    return;
}

\Module\ModuleRedirect::sendWithPassthrough(
    'modules.php?name=Records&tab=' . \Records\RecordsController::TAB_ALLTIME,
    [],
    $_GET + $_POST
);
return;
