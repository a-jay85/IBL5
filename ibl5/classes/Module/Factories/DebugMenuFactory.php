<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/DebugMenu/index.php.
 */
final class DebugMenuFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \Debug\DebugController
    {
        return new \Debug\DebugController($this->services->auth());
    }
}
