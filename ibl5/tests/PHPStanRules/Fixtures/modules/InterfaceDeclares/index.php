<?php

declare(strict_types=1);

use Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\LegacyStatsService;
use Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\SeriesRecordsController;
use Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\SeriesRecordsFactory;

$controller = new SeriesRecordsController();
$controller->main();
$controller->handle();
$factory = new SeriesRecordsFactory();
$factory->service();
$legacy = new LegacyStatsService();
$legacy->getExtra();
