<?php

declare(strict_types=1);

namespace DepthChart;

use DepthChart\Contracts\DepthChartControllerInterface;
use UI\Components\TableViewSwitcher;

/**
 * @see DepthChartControllerInterface
 */
class DepthChartController implements DepthChartControllerInterface
{
    private $db;
    private $repository;
    private $processor;
    private $view;
    private $commonRepository;
    
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new DepthChartRepository($db);
        $this->processor = new DepthChartProcessor();
        $this->view = new DepthChartView($this->processor);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
    }
    
    /**
     * @see DepthChartControllerInterface::displayForm()
     */
    public function displayForm(string $username): void
    {
        $display = $_REQUEST['display'] ?? 'ratings';
        $season = new \Season($this->db);

        $teamName = $this->getUserTeamName($username);
        $teamID = $this->commonRepository->getTidFromTeamname($teamName);
        $team = \Team::initialize($this->db, $teamID);

        \Nuke\Header::header();

        echo '<h2 class="ibl-title">Depth Chart Entry</h2>';
        $this->view->renderTeamLogo($teamID);

        $playersResult = $this->repository->getPlayersOnTeam($teamName, $teamID);

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
            'contracts' => 'Contracts',
        ];

        $baseUrl = 'modules.php?name=Depth_Chart_Entry';
        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $team->color1, $team->color2);
        $tableHtml = $this->renderTableForDisplay($display, $playersResult, $team, $season);
        echo $switcher->wrap($tableHtml);

        \Nuke\Footer::footer();
    }
    
    /**
     * Render the appropriate table HTML based on display type
     */
    private function renderTableForDisplay(string $display, array $result, object $team, object $season): string
    {
        switch ($display) {
            case 'total_s':
                return \UI::seasonTotals($this->db, $result, $team, '');
            case 'avg_s':
                return \UI::seasonAverages($this->db, $result, $team, '');
            case 'per36mins':
                return \UI::per36Minutes($this->db, $result, $team, '');
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
