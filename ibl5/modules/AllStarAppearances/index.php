<?php

declare(strict_types=1);

// Retired module: redirects to its new home. See Module\ModuleRedirect::TARGETS.
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

\Module\ModuleRedirect::send('AllStarAppearances');
return;
