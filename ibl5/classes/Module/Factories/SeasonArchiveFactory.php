<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/SeasonArchive/index.php.
 */
final class SeasonArchiveFactory implements ModuleFactoryInterface
{
    private ?\SeasonArchive\SeasonArchiveRepository $repository = null;

    public function __construct(private readonly ModuleServices $services)
    {
    }

    /**
     * One instance per factory: the entry point reads it directly and the service wraps it.
     */
    public function repository(): \SeasonArchive\SeasonArchiveRepository
    {
        if ($this->repository === null) {
            $this->repository = new \SeasonArchive\SeasonArchiveRepository(
                $this->services->db(),
                $this->services->leagueContext()
            );
        }

        return $this->repository;
    }

    public function service(): \SeasonArchive\SeasonArchiveService
    {
        return new \SeasonArchive\SeasonArchiveService($this->repository());
    }

    public function indexView(): \SeasonArchive\SeasonArchiveIndexView
    {
        return new \SeasonArchive\SeasonArchiveIndexView();
    }

    public function detailView(): \SeasonArchive\SeasonDetailView
    {
        return new \SeasonArchive\SeasonDetailView();
    }
}
