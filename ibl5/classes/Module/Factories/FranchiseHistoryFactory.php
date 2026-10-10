<?php

declare(strict_types=1);

namespace Module\Factories;

use FranchiseHistory\FranchiseHistoryRepository;
use FranchiseHistory\FranchiseHistoryService;
use FranchiseHistory\FranchiseHistoryView;

/**
 * Composition for the FranchiseHistory module entry point.
 */
final class FranchiseHistoryFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function season(): \Season\Season
    {
        return $this->services->season();
    }

    public function service(): FranchiseHistoryService
    {
        return new FranchiseHistoryService(new FranchiseHistoryRepository($this->services->db()));
    }

    public function view(): FranchiseHistoryView
    {
        return new FranchiseHistoryView();
    }
}
