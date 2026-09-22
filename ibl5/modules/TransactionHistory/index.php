<?php

declare(strict_types=1);

/**
 * TransactionHistory Module (retired)
 *
 * Transaction stories are now served by the Search module's "transactions"
 * preset. This stub keeps old links and bookmarks working.
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

\Utilities\HtmxHelper::redirect('modules.php?name=Search&preset=transactions');
