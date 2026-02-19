<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryControllerInterface;
use SavedDepthChart\SavedDepthChartService;
use Team\Contracts\TeamServiceInterface;
use Team\TeamRepository;
use Team\TeamService;
use UI\Components\TableViewSwitcher;

/**
 * @see DepthChartEntryControllerInterface
 */
class DepthChartEntryController implements DepthChartEntryControllerInterface
{
    private \mysqli $db;
    private DepthChartEntryRepository $repository;
    private DepthChartEntryView $view;
    private \Services\CommonMysqliRepository $commonRepository;
    private TeamServiceInterface $teamService;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new DepthChartEntryRepository($db);
        $this->view = new DepthChartEntryView();
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
        $teamRepository = new TeamRepository($db);
        $this->teamService = new TeamService($db, $teamRepository);
    }

    /**
     * @see DepthChartEntryControllerInterface::displayForm()
     */
    public function displayForm(string $username): void
    {
        /** @var string $display */
        $display = $_REQUEST['display'] ?? 'ratings';
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
        echo $this->getTableOutput($teamID, $display);
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

        \PageLayout\PageLayout::footer();
    }

    /**
     * @see DepthChartEntryControllerInterface::getTableOutput()
     */
    public function getTableOutput(int $teamID, string $display): string
    {
        $season = new \Season($this->db);
        $team = \Team::initialize($this->db, $teamID);

        // Delegate roster + starters to TeamService (single source of truth)
        $rosterData = $this->teamService->getRosterAndStarters($teamID);

        $tabDefinitions = [
            'ratings' => 'Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
            'per36mins' => 'Per 36 Minutes',
            'chunk' => 'Sim Averages',
            'contracts' => 'Contracts',
        ];

        $baseUrl = 'modules.php?name=DepthChartEntry';
        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $team->color1, $team->color2);
        $tableHtml = $this->teamService->renderTableForDisplay($display, $rosterData['roster'], $team, null, $season, $rosterData['starterPids']);

        return $switcher->wrap($tableHtml);
    }

    private function getUserTeamName(string $username): string
    {
        $teamName = $this->commonRepository->getTeamnameFromUsername($username);
        return $teamName ?? '';
    }
}
