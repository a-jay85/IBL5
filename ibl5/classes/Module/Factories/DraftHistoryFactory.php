<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the DraftHistory module entry point.
 */
final class DraftHistoryFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function apiHandler(): \DraftHistory\DraftHistoryApiHandler
    {
        return new \DraftHistory\DraftHistoryApiHandler($this->services->db());
    }

    public function repository(): \DraftHistory\DraftHistoryRepository
    {
        return new \DraftHistory\DraftHistoryRepository($this->services->db());
    }

    public function view(): \DraftHistory\DraftHistoryView
    {
        return new \DraftHistory\DraftHistoryView();
    }

    public function team(int $teamid): \Team\Team
    {
        return \Team\Team::initialize($this->services->db(), $teamid);
    }
}
