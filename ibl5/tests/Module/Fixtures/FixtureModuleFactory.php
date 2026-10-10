<?php

declare(strict_types=1);

namespace Tests\Module\Fixtures;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Minimal ModuleFactoryInterface implementation for ModuleServicesTest.
 */
final class FixtureModuleFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function services(): ModuleServices
    {
        return $this->services;
    }
}
