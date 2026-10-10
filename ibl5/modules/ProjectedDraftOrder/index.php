<?php

declare(strict_types=1);

// Consolidated into modules/DraftInfo (tab: order). Kept so old links still resolve.
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

if (($_REQUEST['op'] ?? '') === 'save_order') {
    \Module\ModuleRedirect::sendWithPassthrough(
        'modules.php?name=DraftInfo&op=save_order',
        [],
        $_GET + $_POST,
        null,
        307
    );
    return;
}

\Module\ModuleRedirect::sendWithPassthrough(
    'modules.php?name=DraftInfo&tab=order',
    [],
    $_GET + $_POST
);
return;
