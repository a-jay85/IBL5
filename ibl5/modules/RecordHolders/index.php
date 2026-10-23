<?php

declare(strict_types=1);

/**
 * Retired module: redirects to the Records page (All-Time tab) or AllStarAppearances.
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$target = \Records\RecordsController::legacyRedirectUrl('RecordHolders', $_GET + $_POST)
    ?? 'modules.php?name=Records&tab=' . \Records\RecordsController::TAB_ALLTIME;
(new \Api\Response\RedirectResponder())->redirect($target);
return;
