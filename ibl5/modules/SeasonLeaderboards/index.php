<?php

declare(strict_types=1);

// Retired module: passes through to its new home at Leaderboards?tab=season.
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

\Module\ModuleRedirect::sendWithPassthrough('modules.php?name=Leaderboards&tab=season', [], $_GET + $_POST);
return;
