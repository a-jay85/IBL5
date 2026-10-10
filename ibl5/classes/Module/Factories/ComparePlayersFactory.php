<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/ComparePlayers/index.php.
 */
final class ComparePlayersFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \ComparePlayers\ComparePlayersController
    {
        $repository = new \ComparePlayers\ComparePlayersRepository($this->services->db());
        $service = new \ComparePlayers\ComparePlayersService($repository);
        $view = new \ComparePlayers\ComparePlayersView();

        return new \ComparePlayers\ComparePlayersController(
            $repository,
            $service,
            $view,
            $this->services->nukeCompat()
        );
    }
}
