<?php

declare(strict_types=1);

try {
    require __DIR__ . '/../../mainfile.php';
} catch (Exception $e) {
    error_log("Failed to load mainfile.php: " . $e->getMessage());
    die("Error loading system files. Please contact the administrator.");
}

global $user, $bootApp;
/** @var \Bootstrap\Application $bootApp */
$services = \Module\ModuleServices::currentOrPublish(
    static fn (): \Bootstrap\Contracts\ContainerInterface => $bootApp->getContainer()
);

try {
    $services->db();
} catch (\Throwable) {
    \Logging\LoggerFactory::getChannel('trade')->critical('Database connection not available');
    die("Error: Database connection failed");
}

$httpRequest = \Http\HttpRequest::fromGlobals();
$serverNameRaw = $httpRequest->server('SERVER_NAME');
$serverName = is_string($serverNameRaw) ? $serverNameRaw : '';
$controller = $services->factory(\Module\Factories\TradingFactory::class)->controller($serverName);

$controller->submitTradeOffer($user, $httpRequest->allPost());
