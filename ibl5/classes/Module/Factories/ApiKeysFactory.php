<?php

declare(strict_types=1);

namespace Module\Factories;

use ApiKeys\ApiKeysController;
use ApiKeys\ApiKeysRepository;
use ApiKeys\ApiKeysService;
use ApiKeys\ApiKeysView;

/**
 * Composition for the ApiKeys module entry point.
 */
final class ApiKeysFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function request(): \Http\HttpRequest
    {
        return $this->services->request();
    }

    public function controller(): ApiKeysController
    {
        $repository = new ApiKeysRepository($this->services->db());
        $service = new ApiKeysService($repository);
        $view = new ApiKeysView();
        $googleFlow = null;
        $googleExport = null;
        if (\GoogleSheets\GoogleOAuthConfig::isConfigured()) {
            try {
                $db = $this->services->db();
                $request = $this->services->request();
                $host = $request->server('HTTP_HOST');
                $googleConfig = \GoogleSheets\GoogleOAuthConfig::fromEnv(
                    is_string($host) ? $host : 'localhost',
                    ($request->server('HTTPS') ?? 'off') !== 'off'
                );
                $googleHttp = new \GoogleSheets\CurlGoogleHttpClient();
                $googleOAuth = new \GoogleSheets\GoogleOAuthClient($googleConfig, $googleHttp);
                $googleExport = new \GoogleSheets\GoogleSheetExportService(
                    new \GoogleSheets\GoogleSheetConnectionRepository($db),
                    $googleOAuth,
                    new \GoogleSheets\GoogleSheetsClient($googleHttp),
                    \Security\SecretBox::fromEnv(),
                    new \Api\Repository\ApiPlayerRepository($db),
                    new \Api\Transformer\PlayerExportTransformer(),
                    \Logging\LoggerFactory::getChannel('google-sheets'),
                );
                $googleFlow = new \GoogleSheets\GoogleOAuthFlowHandler($googleOAuth, $googleExport);
            } catch (\Security\SecretBoxKeyUnavailableException | \GoogleSheets\GoogleOAuthNotConfiguredException $e) {
                $googleFlow = null;
                $googleExport = null;
                \Logging\LoggerFactory::getChannel('google-sheets')->error('google sheets disabled: ' . $e->getMessage());
            }
        }

        return new ApiKeysController($service, $view, $this->services->nukeCompat(), $this->services->auth(), $googleFlow, $googleExport);
    }
}
