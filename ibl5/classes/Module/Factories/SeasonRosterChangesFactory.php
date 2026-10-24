<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the SeasonRosterChanges module entry point.
 */
final class SeasonRosterChangesFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function season(): \Season\Season
    {
        return $this->services->season();
    }

    public function repository(): \SeasonRosterChanges\SeasonRosterChangesRepository
    {
        return new \SeasonRosterChanges\SeasonRosterChangesRepository($this->services->db());
    }

    public function view(): \SeasonRosterChanges\SeasonRosterChangesView
    {
        return new \SeasonRosterChanges\SeasonRosterChangesView();
    }
}
