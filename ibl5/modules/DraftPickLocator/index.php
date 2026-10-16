<?php

declare(strict_types=1);

// Consolidated into modules/DraftInfo (tab: picks). Kept so old links still resolve.
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

\Module\ModuleRedirect::sendWithPassthrough(
    'modules.php?name=DraftInfo&tab=picks',
    [],
    $_GET + $_POST
);
return;
