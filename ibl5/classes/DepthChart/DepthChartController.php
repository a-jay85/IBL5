<?php

declare(strict_types=1);

namespace DepthChart;

use DepthChart\Contracts\DepthChartControllerInterface;

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
        $season = new \Season($this->db);
        
        $teamName = $this->getUserTeamName($username);
        $teamID = $this->commonRepository->getTidFromTeamname($teamName);
        $team = \Team::initialize($this->db, $teamID);
        
        \Nuke\Header::header();
        OpenTable();
        \UI::displaytopmenu($this->db, $teamID);

        $this->view->renderTeamLogo($teamID);
        
        $playersResult = $this->repository->getPlayersOnTeam($teamName, $teamID);
        
        $table_ratings = \UI::ratings($this->db, $playersResult, $team, "", $season);
        echo $table_ratings;
        
        $slotNames = \JSB::PLAYER_POSITIONS;
        
        $this->view->renderFormHeader($teamName, $teamID, $slotNames);
        
        $depthCount = 1;
        foreach ($playersResult as $player) {
            $this->view->renderPlayerRow($player, $depthCount);
            $depthCount++;
        }
        
        $this->view->renderFormFooter();
        
        CloseTable();
        \Nuke\Footer::footer();
    }
    
    private function getUserTeamName(string $username): string
    {
        $teamName = $this->commonRepository->getTeamnameFromUsername($username);
        return $teamName ?? '';
    }
}
