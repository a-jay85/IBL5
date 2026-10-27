<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the DraftPickLocator module entry point.
 */
final class DraftPickLocatorFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function season(): \Season\Season
    {
        return $this->services->season();
    }

    public function service(): \DraftPickLocator\DraftPickLocatorService
    {
        return new \DraftPickLocator\DraftPickLocatorService(
            new \DraftPickLocator\DraftPickLocatorRepository($this->services->db())
        );
    }

    public function view(): \DraftPickLocator\DraftPickLocatorView
    {
        return new \DraftPickLocator\DraftPickLocatorView();
    }
}
