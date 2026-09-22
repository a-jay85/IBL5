<?php

declare(strict_types=1);

// Retired module: redirects to its new home. See Module\ModuleRedirect::TARGETS.
if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

\Module\ModuleRedirect::send('PlayerExportGuide');
return;
