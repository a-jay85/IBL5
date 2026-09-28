<?php

declare(strict_types=1);

// Retired module: passes through to its new home at Leaderboards?tab=career.
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

\Module\ModuleRedirect::send('CareerLeaderboards');
return;
