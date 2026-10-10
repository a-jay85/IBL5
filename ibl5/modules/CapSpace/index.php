<?php

declare(strict_types=1);

/**
 * CapSpace Module (retired)
 *
 * Cap space now lives on the Contracts page's Teams tab
 * (modules.php?name=Contracts&tab=teams). This stub keeps old links and
 * bookmarks working. The CapSpace\ classes stay in use by the Contracts module.
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

\Module\ModuleRedirect::sendWithPassthrough('modules.php?name=Contracts&tab=teams', [], $_GET + $_POST);
return;
