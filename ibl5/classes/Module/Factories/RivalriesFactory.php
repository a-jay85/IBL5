<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Rivalries/index.php.
 */
final class RivalriesFactory implements ModuleFactoryInterface
{
    private ?\Rivalries\CachedRivalriesRepository $repository = null;

    public function __construct(private readonly ModuleServices $services)
    {
    }

    /**
     * Shared by index.php (GM name lookup) and the controller.
     */
    public function repository(): \Rivalries\CachedRivalriesRepository
    {
        return $this->repository ??= \Rivalries\RivalriesServiceFactory::createRepository($this->services->db());
    }

    public function controller(string $logoDir): \Rivalries\RivalriesController
    {
        $db = $this->services->db();
        $season = \Season\SharedSeason::get($db, $this->services->leagueContext());

        return new \Rivalries\RivalriesController(
            \Rivalries\RivalriesServiceFactory::create($db, $season, $logoDir),
            new \Rivalries\RivalriesView(),
            $this->repository(),
            $this->services->teamIdentity(),
        );
    }
}
