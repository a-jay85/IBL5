<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the AllStarAppearances module entry point.
 */
final class AllStarAppearancesFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function repository(): \AllStarAppearances\AllStarAppearancesRepository
    {
        return new \AllStarAppearances\AllStarAppearancesRepository($this->services->db());
    }

    public function view(): \AllStarAppearances\AllStarAppearancesView
    {
        return new \AllStarAppearances\AllStarAppearancesView();
    }
}
