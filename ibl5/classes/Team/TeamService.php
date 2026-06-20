<?php

declare(strict_types=1);

namespace Team;

use League\League;
use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Views\AwardsView;
use Team\Views\BannersView;
use Team\Views\CurrentSeasonView;
use Team\Views\DraftPicksView;
use Team\Views\FranchiseHistoryView;
use Team\Views\SidebarView;

/**
 * @phpstan-import-type TeamPageData from Contracts\TeamServiceInterface
 * @phpstan-import-type SidebarData from Contracts\TeamServiceInterface
 * @phpstan-import-type CurrentSeasonData from Contracts\TeamServiceInterface
 * @phpstan-import-type BannerData from Contracts\TeamServiceInterface
 * @phpstan-import-type BannerItemData from Contracts\TeamServiceInterface
 * @phpstan-import-type BannerGroupData from Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffData from Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffRoundData from Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffResultItem from Contracts\TeamServiceInterface
 * @phpstan-import-type WinLossHistoryData from Contracts\TeamServiceInterface
 * @phpstan-import-type WinLossRecord from Contracts\TeamServiceInterface
 * @phpstan-import-type DraftPickItemData from Contracts\TeamServiceInterface
 * @phpstan-import-type FranchiseSeasonRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type TeamInfoRow from \Repositories\Contracts\TeamIdentityRepositoryInterface
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

    public function __construct(
        \mysqli $db,
        TeamRepositoryInterface $repository,
        \League\LeagueContext $leagueContext,
        ?TeamQueryRepositoryInterface $teamQueryRepository = null,
        ?League $league = null,
        ?TeamPageDataPreparer $preparer = null,
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
        if ($isActualTeam) {
            $draftPicksData = $this->preparer->prepareDraftPicksData($team);
            $draftPicksView = new DraftPicksView();
            $draftPicksTable = $draftPicksView->render($draftPicksData);
        }

        $currentSeasonCard = "";
        $awardsCard = "";
        $franchiseHistoryCard = "";
        $rafters = "";
        if ($isActualTeam) {
            $sidebarData = $this->renderTeamInfoRight($team);
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

    /**
     * Render team information right sidebar
     *
     * @return SidebarData
     */
    private function renderTeamInfoRight(Team $team): array
    {
        $teamColorStyle = \UI\TableStyles::inlineTeamVars($team->color1, $team->color2);

        $sidebarView = new SidebarView();

        // Banners
        $bannerData = $this->preparer->prepareBannerData($team);
        $bannersView = new BannersView();
        $rafters = $bannersView->render($bannerData);

        // Current Season card
        $currentSeasonData = $this->preparer->prepareCurrentSeasonData($team);
        $currentSeasonHtml = '';
        if ($currentSeasonData !== null) {
            $currentSeasonView = new CurrentSeasonView();
            $currentSeasonHtml = $currentSeasonView->render($currentSeasonData);
        }
        $currentSeasonCard = $sidebarView->renderCurrentSeasonCard($currentSeasonHtml, $teamColorStyle);

        // Awards card — combines GM History and Team Accomplishments
        $awardsData = $this->preparer->prepareAwardsData($team);

        $awardsRenderer = new AwardsView();
        $gmHistoryHtml = $awardsRenderer->renderGmHistory($awardsData['tenures'], $awardsData['gmAwards']);
        $teamAccomplishmentsHtml = $awardsRenderer->renderTeamAccomplishments($awardsData['teamAccomplishments']);
        $awardsCard = $sidebarView->renderAwardsCard($gmHistoryHtml, $teamAccomplishmentsHtml, $teamColorStyle);

        // Franchise History card
        $regularSeasonData = $this->preparer->prepareWinLossHistoryData($team, 'regular');
        $heatData = $this->preparer->prepareWinLossHistoryData($team, 'heat');
        $playoffData = $this->preparer->preparePlayoffData($team);

        $franchiseHistoryView = new FranchiseHistoryView();
        $regularSeasonHtml = $franchiseHistoryView->renderRegularSeason($regularSeasonData);
        $heatHtml = $franchiseHistoryView->renderHeat($heatData);
        $playoffsHtml = $franchiseHistoryView->renderPlayoffs($playoffData);
        $franchiseHistoryCard = $sidebarView->renderFranchiseHistoryCard($heatHtml, $regularSeasonHtml, $playoffsHtml, $teamColorStyle);

        return [
            'currentSeasonCard' => $currentSeasonCard,
            'awardsCard' => $awardsCard,
            'franchiseHistoryCard' => $franchiseHistoryCard,
            'rafters' => $rafters,
        ];
    }

}
