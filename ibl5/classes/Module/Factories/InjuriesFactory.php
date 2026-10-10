<?php

declare(strict_types=1);

namespace Module\Factories;

use Injuries\InjuriesService;
use Injuries\InjuriesView;

/**
 * Composition for the Injuries module entry point.
 */
final class InjuriesFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function service(): InjuriesService
    {
        return new InjuriesService($this->services->db());
    }

    public function view(): InjuriesView
    {
        return new InjuriesView();
    }
}
