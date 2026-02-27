<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryControllerInterface;
use NextSim\NextSimService;
use NextSim\NextSimView;
use SavedDepthChart\SavedDepthChartService;
use Standings\StandingsRepository;
use Team\Contracts\TeamTableServiceInterface;
use Team\TeamRepository;
use Team\TeamTableService;
use TeamSchedule\TeamScheduleRepository;
use UI\Components\TableViewDropdown;

/**
 * @see DepthChartEntryControllerInterface
 */
class DepthChartEntryController implements DepthChartEntryControllerInterface
{
    private \mysqli $db;
    private DepthChartEntryRepository $repository;
    private DepthChartEntryView $view;
    private \Services\CommonMysqliRepository $commonRepository;
    private TeamTableServiceInterface $teamTableService;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new DepthChartEntryRepository($db);
        $this->view = new DepthChartEntryView();
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
        $teamRepository = new TeamRepository($db);
        $this->teamTableService = new TeamTableService($db, $teamRepository);
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

        $season = new \Season($this->db);

        $teamName = $this->getUserTeamName($username);
        $teamID = $this->commonRepository->getTidFromTeamname($teamName) ?? 0;
        $team = \Team::initialize($this->db, $teamID);

        \PageLayout\PageLayout::header();

        echo '<h2 class="ibl-title">Depth Chart Entry</h2>';
        $this->view->renderTeamLogo($teamID);

        // Render saved depth chart dropdown
        $savedDcService = new SavedDepthChartService($this->db);
        $dropdownOptions = $savedDcService->getDropdownOptions($teamID, $season);
        $currentLiveLabel = $savedDcService->buildCurrentLiveLabel($teamID, $season);
        $this->view->renderSavedDepthChartDropdown($dropdownOptions, $currentLiveLabel);

        $playersResult = $this->repository->getPlayersOnTeam($teamName, $teamID);

        // Collect current roster PIDs for JS config
        $currentRosterPids = array_map(
            static fn(array $p): int => $p['pid'],
            $playersResult
        );

        $slotNames = \JSB::PLAYER_POSITIONS;

        $this->view->renderFormHeader($teamName, $teamID, $slotNames);

        $depthCount = 1;
        foreach ($playersResult as $player) {
            $this->view->renderPlayerRow($player, $depthCount);
            $depthCount++;
        }

        $this->view->renderFormFooter();

        echo '<div class="table-scroll-wrapper"><div class="table-scroll-container">';
        echo $this->getTableOutput($teamID, $display, $split);
        echo '</div></div>';

        // Output JS configuration for saved depth charts
        $jsConfig = json_encode([
            'teamId' => $teamID,
            'apiBaseUrl' => 'modules.php?name=DepthChartEntry&op=api',
            'currentRosterPids' => $currentRosterPids,
        ], JSON_THROW_ON_ERROR);
        echo '<script>window.IBL_DEPTH_CHART_CONFIG = ' . $jsConfig . ';</script>';
        echo '<script src="jslib/depth-chart-changes.js" defer></script>';
        echo '<script src="jslib/saved-depth-charts.js" defer></script>';

        // Output JS configuration for AJAX tab switching
        $ajaxTabsConfig = json_encode([
            'apiBaseUrl' => 'modules.php?name=DepthChartEntry&op=tab-api',
            'params' => ['teamID' => $teamID],
            'fallbackBaseUrl' => 'modules.php?name=DepthChartEntry',
        ], JSON_THROW_ON_ERROR);
        echo '<script>window.IBL_AJAX_TABS_CONFIG = ' . $ajaxTabsConfig . ';</script>';
        echo '<script src="jslib/ajax-tabs.js" defer></script>';

        // NextSim position tables section
        $this->renderNextSimSection($teamID, $team, $season);

        \PageLayout\PageLayout::footer();
    }

    /**
     * @see DepthChartEntryControllerInterface::getTableOutput()
     */
    public function getTableOutput(int $teamID, string $display, ?string $split = null): string
    {
        $season = new \Season($this->db);
        $team = \Team::initialize($this->db, $teamID);

        // Delegate roster + starters to TeamService (single source of truth)
        $rosterData = $this->teamTableService->getRosterAndStarters($teamID);

        $groups = $this->teamTableService->buildDropdownGroups($season);

        $activeValue = ($display === 'split' && $split !== null) ? 'split:' . $split : $display;
        $baseUrl = 'modules.php?name=DepthChartEntry';
        $dropdown = new TableViewDropdown($groups, $activeValue, $baseUrl, $team->color1, $team->color2);
        $tableHtml = $this->teamTableService->renderTableForDisplay($display, $rosterData['roster'], $team, null, $season, $rosterData['starterPids'], $split);

        return $dropdown->wrap($tableHtml);
    }

    private function renderNextSimSection(int $teamID, \Team $team, \Season $season): void
    {
        // Load power rankings for SOS tier indicators
        $standingsRepo = new StandingsRepository($this->db);
        $allStreakData = $standingsRepo->getAllStreakData();
        /** @var array<int, float> $teamPowerRankings */
        $teamPowerRankings = [];
        foreach ($allStreakData as $tid => $data) {
            $teamPowerRankings[$tid] = (float) $data['ranking'];
        }

        $teamScheduleRepository = new TeamScheduleRepository($this->db);
        $nextSimService = new NextSimService($this->db, $teamScheduleRepository, $teamPowerRankings);
        $nextSimView = new NextSimView($season);

        $games = $nextSimService->getNextSimGames($teamID, $season);
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

        // Output JS configuration for NextSim AJAX tab switching
        $nextSimTabsConfig = json_encode([
            'apiBaseUrl' => 'modules.php?name=DepthChartEntry&op=nextsim-api',
            'params' => ['teamID' => $teamID],
        ], JSON_THROW_ON_ERROR);
        echo '<script>window.IBL_NEXTSIM_TABS_CONFIG = ' . $nextSimTabsConfig . ';</script>';
        echo '<script src="jslib/nextsim-tabs.js" defer></script>';
    }

    private function getUserTeamName(string $username): string
    {
        $teamName = $this->commonRepository->getTeamnameFromUsername($username);
        return $teamName ?? '';
    }
}
