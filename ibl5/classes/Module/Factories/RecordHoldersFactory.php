<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the RecordHolders module entry point.
 */
final class RecordHoldersFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function allStarRepository(): \AllStarAppearances\AllStarAppearancesRepository
    {
        return new \AllStarAppearances\AllStarAppearancesRepository($this->services->db());
    }

    public function allStarView(): \AllStarAppearances\AllStarAppearancesView
    {
        return new \AllStarAppearances\AllStarAppearancesView();
    }

    public function service(): \RecordHolders\CachedRecordHoldersService
    {
        $db = $this->services->db();
        $repository = new \RecordHolders\RecordHoldersRepository($db, $this->services->leagueContext());
        $innerService = new \RecordHolders\RecordHoldersService($repository);
        $cache = new \Cache\DatabaseCache($db);

        return new \RecordHolders\CachedRecordHoldersService($innerService, $cache);
    }

    public function view(): \RecordHolders\RecordHoldersView
    {
        return new \RecordHolders\RecordHoldersView();
    }
}
