<?php

declare(strict_types=1);

// Retired module: redirects to Leaderboards?tab=season. The query string is dropped.
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

\Module\ModuleRedirect::send('SeasonLeaderboards');
return;
