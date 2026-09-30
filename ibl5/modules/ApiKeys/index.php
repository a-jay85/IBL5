<?php

declare(strict_types=1);

/**
 * ApiKeys Module - Self-service API key management
 *
 * Lets logged-in users generate, view, and revoke their own API keys
 * for use with the Player Export CSV endpoint and Google Sheets IMPORTDATA.
 *
 * @see ApiKeys\ApiKeysService For key generation logic
 * @see ApiKeys\ApiKeysView For HTML rendering
 */

if (stripos($_SERVER['PHP_SELF'], 'modules.php') === false) {
    die("You can't access this file directly...");
}

global $mysqli_db, $user, $authService;

$httpRequest = \Http\HttpRequest::fromGlobals();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : 'main';

$repository = new \ApiKeys\ApiKeysRepository($mysqli_db);
$service    = new \ApiKeys\ApiKeysService($repository);
$view       = new \ApiKeys\ApiKeysView();
$nukeCompat = new \Utilities\NukeCompat();
$googleFlow   = null;
$googleExport = null;
if ($mysqli_db instanceof \mysqli && \GoogleSheets\GoogleOAuthConfig::isConfigured()) {
    try {
        $googleConfig = \GoogleSheets\GoogleOAuthConfig::fromEnv(
            is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : 'localhost',
            ($_SERVER['HTTPS'] ?? 'off') !== 'off'
        );
        $googleHttp   = new \GoogleSheets\CurlGoogleHttpClient();
        $googleOAuth  = new \GoogleSheets\GoogleOAuthClient($googleConfig, $googleHttp);
        $googleExport = new \GoogleSheets\GoogleSheetExportService(
            new \GoogleSheets\GoogleSheetConnectionRepository($mysqli_db),
            $googleOAuth,
            new \GoogleSheets\GoogleSheetsClient($googleHttp),
            \Security\SecretBox::fromEnv(),
            new \Api\Repository\ApiPlayerRepository($mysqli_db),
            new \Api\Transformer\PlayerExportTransformer(),
            \Logging\LoggerFactory::getChannel('google-sheets'),
        );
        $googleFlow = new \GoogleSheets\GoogleOAuthFlowHandler($googleOAuth, $googleExport);
    } catch (\Security\SecretBoxKeyUnavailableException | \GoogleSheets\GoogleOAuthNotConfiguredException $e) {
        $googleExport = null;
        \Logging\LoggerFactory::getChannel('google-sheets')->error('google sheets disabled: ' . $e->getMessage());
    }
}

$controller = new \ApiKeys\ApiKeysController($service, $view, $nukeCompat, $authService, $googleFlow, $googleExport);

$controller->handle($op, $user);
