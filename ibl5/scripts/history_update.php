<?php

declare(strict_types=1);

/**
 * Franchise History Update Script
 *
 * Updates franchise history counts: division titles, conference titles,
 * IBL titles, H.E.A.T. titles, and playoff appearances.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/autoloader.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/db/db.php';

use Scripts\MaintenanceRepository;

$repository = new MaintenanceRepository($mysqli_db);

$repository->updateAllTitlesAndAppearances();

echo "Franchise History update is complete!<br>";
