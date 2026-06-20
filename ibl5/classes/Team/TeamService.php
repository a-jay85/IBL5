<?php

declare(strict_types=1);

namespace Team;

use League\League;
use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use Team\Contracts\TeamQueryRepositoryInterface;

/**
 * @phpstan-import-type TeamPageData from Contracts\TeamServiceInterface
 * @phpstan-import-type SidebarData from Contracts\TeamServiceInterface
 *
 * @see TeamServiceInterface
 */
class TeamService implements TeamServiceInterface
{
    private \mysqli $db;
    private TeamRepositoryInterface $repository;
    private \League\LeagueContext $leagueContext;
    private TeamQueryRepositoryInterface $teamQueryRepository;
    private League $league;
    private TeamPageDataPreparer $preparer;
    private TeamCardRenderer $cardRenderer;

    public function __construct(
        \mysqli $db,
        TeamRepositoryInterface $repository,
        \League\LeagueContext $leagueContext,
        ?TeamQueryRepositoryInterface $teamQueryRepository = null,
        ?League $league = null,
        ?TeamPageDataPreparer $preparer = null,
        ?TeamCardRenderer $cardRenderer = null,
    ) {
        $this->db = $db;
        $this->repository = $repository;
        $this->leagueContext = $leagueContext;
        $this->teamQueryRepository = $teamQueryRepository ?? new TeamQueryRepository($db);
        $this->league = $league ?? new League($db);
        $this->preparer = $preparer ?? new TeamPageDataPreparer(
            $this->db,
            $this->repository,
            $this->teamQueryRepository,
            $this->league,
        );
        $this->cardRenderer = $cardRenderer ?? new TeamCardRenderer();
    }

    /**
     * @see TeamServiceInterface::getTeamPageData()
     * @return TeamPageData
     */
    public function getTeamPageData(int $teamid, ?string $yr, string $display, string $userTeamName = '', ?string $split = null): array
    {
        $leagueConfig = $this->leagueContext->getConfig();
        /** @var string $imagesPath */
        $imagesPath = $leagueConfig['images_path'];

        $team = Team::initialize($this->db, $teamid);

        $insertyear = ($yr !== null && $yr !== '') ? "&yr=$yr" : "";

        $tableService = new TeamTableService($this->db, $this->repository);
        $tableOutput = $tableService->getTableOutput($teamid, $yr, $display, $split);

        $isActualTeam = ($teamid !== 0);

        $draftPicksTable = '';
        $currentSeasonCard = "";
        $awardsCard = "";
        $franchiseHistoryCard = "";
        $rafters = "";

        if ($isActualTeam) {
            $draftPicksData = $this->preparer->prepareDraftPicksData($team);
            $draftPicksTable = $this->cardRenderer->renderDraftPicksTable($draftPicksData);

            $sidebarData = $this->cardRenderer->renderSidebarCards(
                $team,
                $this->preparer->prepareBannerData($team),
                $this->preparer->prepareCurrentSeasonData($team),
                $this->preparer->prepareAwardsData($team),
                $this->preparer->prepareWinLossHistoryData($team, 'regular'),
                $this->preparer->prepareWinLossHistoryData($team, 'heat'),
                $this->preparer->preparePlayoffData($team),
            );
            $currentSeasonCard = $sidebarData['currentSeasonCard'];
            $awardsCard = $sidebarData['awardsCard'];
            $franchiseHistoryCard = $sidebarData['franchiseHistoryCard'];
            $rafters = $sidebarData['rafters'];
        }

        return [
            'teamid' => $teamid,
            'team' => $team,
            'imagesPath' => $imagesPath,
            'yr' => $yr,
            'display' => $display,
            'insertyear' => $insertyear,
            'isActualTeam' => $isActualTeam,
            'tableOutput' => $tableOutput,
            'draftPicksTable' => $draftPicksTable,
            'currentSeasonCard' => $currentSeasonCard,
            'awardsCard' => $awardsCard,
            'franchiseHistoryCard' => $franchiseHistoryCard,
            'rafters' => $rafters,
            'userTeamName' => $userTeamName,
            'isOwnTeam' => ($userTeamName !== '' && $userTeamName === $team->name),
            'extensionResult' => null,
            'extensionMsg' => null,
        ];
    }
}
