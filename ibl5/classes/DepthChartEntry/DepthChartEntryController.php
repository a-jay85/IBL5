<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryControllerInterface;
use DepthChartEntry\Contracts\DepthChartEntryServiceInterface;
use NextSim\NextSimService;
use NextSim\NextSimView;
use SavedDepthChart\SavedDepthChartService;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Standings\StandingsRepository;
use Team\Contracts\TeamTableServiceInterface;
use Team\TeamRepository;
use Team\TeamTableService;
use TeamSchedule\TeamScheduleRepository;
use UI\Components\TableViewDropdown;
use Team\Team;
use Season\Season;

/**
 * @see DepthChartEntryControllerInterface
 */
class DepthChartEntryController implements DepthChartEntryControllerInterface
{
    private \mysqli $db;
    private DepthChartEntryRepository $repository;
    private DepthChartEntryView $view;
    private TeamIdentityRepositoryInterface $commonRepository;
    private TeamTableServiceInterface $teamTableService;
    private DepthChartEntryServiceInterface $service;

    public function __construct(\mysqli $db, TeamIdentityRepositoryInterface $commonRepository)
    {
        $this->db = $db;
        $this->repository = new DepthChartEntryRepository($db);
        $this->view = new DepthChartEntryView();
        $this->commonRepository = $commonRepository;
        $teamRepository = new TeamRepository($db);
        $this->teamTableService = new TeamTableService($db, $teamRepository);
        $this->service = new DepthChartEntryService();
    }

    /**
     * @see DepthChartEntryControllerInterface::displayForm()
     */
    public function displayForm(string $username): void
    {
        $display = 'ratings';
        if (isset($_REQUEST['display']) && is_string($_REQUEST['display'])) {
            $display = $_REQUEST['display'];
        }

        // Validate split parameter when display=split
        $split = null;
        if ($display === 'split' && isset($_REQUEST['split']) && is_string($_REQUEST['split'])) {
            $splitRepo = new \Team\SplitStatsRepository($this->db);
            $rawSplit = $_REQUEST['split'];
            if (in_array($rawSplit, $splitRepo->getValidSplitKeys(), true)) {
                $split = $rawSplit;
            } else {
                $display = 'ratings';
            }
        } elseif ($display === 'split') {
            $display = 'ratings';
        }

        $season = new Season($this->db);

        // Consume the PRG failure flash if present. Stashed by the submission
        // handler (`_ibl_depth_chart_flash`) on validation failure or empty
        // team name so the redirected GET can (1) surface validator errors
        // above the form and (2) re-populate fields from the user's submitted
        // POST rather than the DB — preserving in-flight edits.
        $flashErrorsHtml = '';
        $override = [];
        if (isset($_SESSION['_ibl_depth_chart_flash']) && is_array($_SESSION['_ibl_depth_chart_flash'])) {
            $flash = $_SESSION['_ibl_depth_chart_flash'];
            unset($_SESSION['_ibl_depth_chart_flash']);
            if (isset($flash['errors_html']) && is_string($flash['errors_html'])) {
                $flashErrorsHtml = $flash['errors_html'];
            }
            if (isset($flash['post_data']) && is_array($flash['post_data'])) {
                $override = $this->service->buildFormOverride($flash['post_data']);
            }
        }

        $teamName = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $teamid = $this->commonRepository->getTidFromTeamname($teamName) ?? 0;
        $team = Team::initialize($this->db, $teamid);

        \PageLayout\PageLayout::header();

        echo '<h2 class="ibl-title">Depth Chart Entry</h2>';

        if ($flashErrorsHtml !== '') {
            echo '<div class="ibl-alert ibl-alert--error">' . $flashErrorsHtml . '</div>';
        }

        $this->view->renderTeamLogo($teamid);

        // Render saved depth chart dropdown
        $savedDcService = new SavedDepthChartService($this->db);
        $dropdownOptions = $savedDcService->getDropdownOptions($teamid, $season);
        $currentLiveLabel = $savedDcService->buildCurrentLiveLabel($teamid, $season);
        $this->view->renderSavedDepthChartDropdown($dropdownOptions, $currentLiveLabel);

        $this->view->renderHelpSection();

        $playersResult = $this->repository->getPlayersOnTeam($teamid);

        // Compute quality scores and apply any flash override so the form
        // re-renders with the user's last submitted values after a validation
        // failure, not the stale DB values.
        $playersWithQuality = array_map(
            function (array $player) use ($override): array {
                $player['quality_score'] = $this->service->computeQualityScore($player);
                $pid = $player['pid'];
                if (isset($override[$pid])) {
                    $player = array_merge($player, $override[$pid]);
                }
                return $player;
            },
            $playersResult
        );

        // Collect current roster PIDs for JS config
        $currentRosterPids = array_map(
            static fn(array $p): int => $p['pid'],
            $playersWithQuality
        );

        $slotNames = \JSB::PLAYER_POSITIONS;

        $this->view->renderLineupPreview();
        $this->view->renderFormHeader($teamName, $teamid, $slotNames);

        $depthCount = 1;
        foreach ($playersWithQuality as $player) {
            $this->view->renderPlayerRow($player, $depthCount);
            $depthCount++;
        }

        $this->view->renderFormFooter();
        $this->view->renderMobileView($playersWithQuality, $slotNames);

        echo '<div class="table-scroll-wrapper"><div class="table-scroll-container" tabindex="0" role="region" aria-label="Player ratings">';
        echo $this->getTableOutput($teamid, $display, $split);
        echo '</div></div>';

        // Output JS configuration for saved depth charts
        $jsConfig = json_encode([
            'teamId' => $teamid,
            'apiBaseUrl' => 'modules.php?name=DepthChartEntry&op=api',
            'currentRosterPids' => $currentRosterPids,
        ], JSON_THROW_ON_ERROR);
        echo '<script>window.IBL_DEPTH_CHART_CONFIG = ' . $jsConfig . ';</script>';
        echo '<script src="jslib/depth-chart-changes.js"></script>';
        echo '<script src="jslib/depth-chart-lineup-preview.js"></script>';
        echo '<script src="jslib/saved-depth-charts.js"></script>';
        echo '<script src="jslib/depth-chart-mobile.js"></script>';

        // NextSim position tables section
        $this->renderNextSimSection($teamid, $team, $season);

        \PageLayout\PageLayout::footer();
    }

    /**
     * @see DepthChartEntryControllerInterface::getTableOutput()
     */
    public function getTableOutput(int $teamid, string $display, ?string $split = null): string
    {
        $season = new Season($this->db);
        $team = Team::initialize($this->db, $teamid);

        // Delegate roster + starters to TeamService (single source of truth)
        $rosterData = $this->teamTableService->getRosterAndStarters($teamid);

        $groups = $this->teamTableService->buildDropdownGroups($season);

        $activeValue = ($display === 'split' && $split !== null) ? 'split:' . $split : $display;
        $baseUrl = 'modules.php?name=DepthChartEntry';
        $apiUrl = 'modules.php?name=DepthChartEntry&op=tab-api&teamid=' . $teamid;
        $dropdown = new TableViewDropdown($groups, $activeValue, $baseUrl, $team->color1, $team->color2, $apiUrl);
        $tableHtml = $this->teamTableService->renderTableForDisplay($display, $rosterData['roster'], $team, null, $season, $rosterData['starterPids'], $split);

        return $dropdown->wrap($tableHtml);
    }

    private function renderNextSimSection(int $teamid, Team $team, Season $season): void
    {
        // Load power rankings for SOS tier indicators
        $standingsRepo = new StandingsRepository($this->db);
        $allStreakData = $standingsRepo->getAllStreakData();
        /** @var array<int, float> $teamPowerRankings */
        $teamPowerRankings = [];
        foreach ($allStreakData as $rankedTeamid => $data) {
            $teamPowerRankings[$rankedTeamid] = (float) $data['ranking'];
        }

        $teamScheduleRepository = new TeamScheduleRepository($this->db);
        $nextSimService = new NextSimService($this->db, $teamScheduleRepository, $teamPowerRankings);
        $nextSimView = new NextSimView($season);

        $games = $nextSimService->getNextSimGames($teamid, $season);
        $userStarters = $nextSimService->getUserStartingLineup($team);

        echo '<div class="next-sim-depth-chart-section">';
        echo '<h2 class="ibl-title">Next Sim</h2>';

        if ($games === []) {
            echo '<div class="next-sim-empty">No games projected next sim!</div>';
        } else {
            echo $nextSimView->renderScheduleStrip($games);
            echo '<div class="nextsim-tab-container">';
            echo $nextSimView->renderTabbedPositionTable($games, 'PG', $team, $userStarters);
            echo '</div>';
            echo $nextSimView->renderColumnHighlightScript();
        }

        echo '</div>';
    }

}
