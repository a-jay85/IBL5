<?php

declare(strict_types=1);

/**
 * ContractList Module (retired)
 *
 * The master contract list now lives on the Contracts page's Players tab
 * (modules.php?name=Contracts&tab=players). This stub keeps old links and
 * bookmarks working. The ContractList\ classes stay in use by the Contracts module.
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

\Module\ModuleRedirect::sendWithPassthrough('modules.php?name=Contracts&tab=players', [], $_GET + $_POST);
return;
