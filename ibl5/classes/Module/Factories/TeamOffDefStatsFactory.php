<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/TeamOffDefStats/index.php.
 */
final class TeamOffDefStatsFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function repository(): \TeamOffDefStats\TeamOffDefStatsRepository
    {
        return new \TeamOffDefStats\TeamOffDefStatsRepository($this->services->db());
    }

    public function service(): \TeamOffDefStats\TeamOffDefStatsService
    {
        return new \TeamOffDefStats\TeamOffDefStatsService();
    }

    public function view(): \TeamOffDefStats\TeamOffDefStatsView
    {
        return new \TeamOffDefStats\TeamOffDefStatsView();
    }

    public function season(): \Season\Season
    {
        return $this->services->season();
    }
}
