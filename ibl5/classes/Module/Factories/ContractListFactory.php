<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the ContractList module entry point.
 */
final class ContractListFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function service(): \ContractList\ContractListService
    {
        return new \ContractList\ContractListService(
            new \ContractList\ContractListRepository($this->services->db())
        );
    }

    public function view(): \ContractList\ContractListView
    {
        return new \ContractList\ContractListView();
    }
}
