<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryControllerInterface;
use SavedDepthChart\SavedDepthChartService;
use UI\Components\TableViewSwitcher;

/**
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @see DepthChartEntryControllerInterface
 */
class DepthChartEntryController implements DepthChartEntryControllerInterface
{
    private \mysqli $db;
    private DepthChartEntryRepository $repository;
    private DepthChartEntryProcessor $processor;
    private DepthChartEntryView $view;
    private \Services\CommonMysqliRepository $commonRepository;
    
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new DepthChartEntryRepository($db);
        $this->processor = new DepthChartEntryProcessor();
        $this->view = new DepthChartEntryView($this->processor);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
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

        \Nuke\Header::header();

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
        $tableHtml = $this->renderTableForDisplay($display, $playersResult, $team, $season);
        echo $switcher->wrap($tableHtml);

        // Output JS configuration for saved depth charts
        $jsConfig = json_encode([
            'teamId' => $teamID,
            'apiBaseUrl' => 'modules.php?name=DepthChartEntry&op=api',
            'currentRosterPids' => $currentRosterPids,
        ], JSON_THROW_ON_ERROR);
        echo '<script>window.IBL_DEPTH_CHART_CONFIG = ' . $jsConfig . ';</script>';
        echo '<script src="jslib/depth-chart-changes.js" defer></script>';
        echo '<script src="jslib/saved-depth-charts.js" defer></script>';

        \Nuke\Footer::footer();
    }
    
    /**
     * Render the appropriate table HTML based on display type
     *
     * @param list<PlayerRow> $result
     */
    private function renderTableForDisplay(string $display, array $result, \Team $team, \Season $season): string
    {
        switch ($display) {
            case 'total_s':
                return \UI::seasonTotals($this->db, $result, $team, '');
            case 'avg_s':
                return \UI::seasonAverages($this->db, $result, $team, '');
            case 'per36mins':
                return \UI::per36Minutes($this->db, $result, $team, '');
            case 'chunk':
                return \UI::periodAverages($this->db, $team, $season);
            case 'contracts':
                $sharedFunctions = new \Shared($this->db);
                return \UI::contracts($this->db, $result, $team, $sharedFunctions);
            default:
                return \UI::ratings($this->db, $result, $team, '', $season);
        }
    }

    private function getUserTeamName(string $username): string
    {
        $teamName = $this->commonRepository->getTeamnameFromUsername($username);
        return $teamName ?? '';
    }
}
