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

        return new ApiKeysController($service, $view, $this->services->nukeCompat(), $this->services->auth());
    }
}
