<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/FreeAgency/index.php.
 */
final class FreeAgencyFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \FreeAgency\FreeAgencyController
    {
        $db = $this->services->db();
        $commonRepo = $this->services->teamIdentity();

        $repository = new \FreeAgency\FreeAgencyRepository($db);
        $demandRepository = new \FreeAgency\FreeAgencyDemandRepository($db);
        $tableRenderer = new \FreeAgency\FreeAgencyTableRendererView($commonRepo);
        $service = new \FreeAgency\FreeAgencyService($repository, $demandRepository, $db, $commonRepo);
        $view = new \FreeAgency\FreeAgencyView(
            new \FreeAgency\FreeAgencyUnderContractSectionView($tableRenderer),
            new \FreeAgency\FreeAgencyContractOffersSectionView($tableRenderer),
            new \FreeAgency\FreeAgencyTeamFreeAgentsSectionView($tableRenderer),
            new \FreeAgency\FreeAgencyOtherFreeAgentsSectionView($tableRenderer)
        );
        $processor = new \FreeAgency\FreeAgencyProcessor($db, $commonRepo);

        return new \FreeAgency\FreeAgencyController(
            $db,
            $commonRepo,
            $this->services->auth(),
            $service,
            $view,
            $processor,
            $this->services->request()
        );
    }
}
