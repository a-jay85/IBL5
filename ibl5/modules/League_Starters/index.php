<?php

declare(strict_types=1);

// Legacy URL for the LeagueStarters module, kept so old bookmarks and the Google Sheets importer follow the 302. See Module\ModuleRedirect::TARGETS.
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

\Module\ModuleRedirect::send('League_Starters');
return;
