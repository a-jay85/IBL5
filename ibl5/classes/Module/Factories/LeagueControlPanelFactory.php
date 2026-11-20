<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/LeagueControlPanel/index.php.
 *
 * index.php resolves this factory only after its authentication and admin gates.
 */
final class LeagueControlPanelFactory implements ModuleFactoryInterface
{
    private ?\LeagueControlPanel\LeagueControlPanelRepository $repository = null;

    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function repository(): \LeagueControlPanel\LeagueControlPanelRepository
    {
        if ($this->repository === null) {
            $this->repository = new \LeagueControlPanel\LeagueControlPanelRepository(
                $this->services->db(),
                $this->services->leagueContext()
            );
        }

        return $this->repository;
    }

    public function service(): \LeagueControlPanel\LeagueControlPanelService
    {
        return new \LeagueControlPanel\LeagueControlPanelService(
            $this->repository(),
            $this->services->leagueContext()->getCurrentLeague()
        );
    }

    public function processor(): \LeagueControlPanel\LeagueControlPanelProcessor
    {
        $db = $this->services->db();
        // @phpstan-ignore ibl.factoryOmitsLeagueContext (Award tallies read ibl_votes_ASG and ibl_votes_EOY, which have no Olympics entry in LeagueContext::TABLE_MAP, so voting stays on IBL tables)
        $votingRepository = new \Voting\VotingRepository($db);
        $votingResultsService = new \Voting\VotingResultsService($votingRepository);
        $awardGenerationService = new \LeagueControlPanel\LeagueControlPanelAwardGenerationService(
            $this->repository(),
            $votingResultsService
        );
        // @phpstan-ignore ibl.factoryOmitsLeagueContext (Maintenance pairs ibl_team_info with ibl_team_win_loss and IBL ibl_settings; only ibl_team_info has an Olympics mapping, so an LC would split one job across leagues)
        $maintenanceRepository = new \Maintenance\MaintenanceRepository($db);

        return new \LeagueControlPanel\LeagueControlPanelProcessor(
            $this->repository(),
            $awardGenerationService,
            $this->services->leagueContext()->getCurrentLeague(),
            $maintenanceRepository
        );
    }

    public function view(): \LeagueControlPanel\LeagueControlPanelView
    {
        return new \LeagueControlPanel\LeagueControlPanelView();
    }

    public function csvExporter(): \LeagueControlPanel\ActivePlayersCsvExporter
    {
        return new \LeagueControlPanel\ActivePlayersCsvExporter($this->repository());
    }

    /**
     * The export timestamp, taken at the moment the export runs.
     */
    public function exportTimestamp(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
