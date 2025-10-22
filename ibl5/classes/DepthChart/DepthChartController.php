<?php

namespace DepthChart;

/**
 * Main controller for depth chart entry module
 */
class DepthChartController
{
    private $db;
    private $repository;
    private $processor;
    private $view;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->repository = new DepthChartRepository($db);
        $this->processor = new DepthChartProcessor();
        $this->view = new DepthChartView($this->processor);
    }
    
    /**
     * Displays the depth chart entry form
     * 
     * @param string $username Username of logged-in user
     * @param int $useSet Selected offensive set (1-3)
     * @return void Renders the form
     */
    public function displayForm(string $username, int $useSet = 1): void
    {
        $sharedFunctions = new \Shared($this->db);
        $season = new \Season($this->db);
        
        // Get user's team information
        $teamName = $this->getUserTeamName($username);
        $teamID = $sharedFunctions->getTidFromTeamname($teamName);
        $team = \Team::initialize($this->db, $teamID);
        
        // Render header
        \Nuke\Header::header();
        OpenTable();
        \UI::displaytopmenu($this->db, $teamID);
        
        // Get offensive sets
        $offenseSetsResult = $this->repository->getOffenseSets($teamName);
        $offenseSet = $this->repository->getOffenseSet($teamName, $useSet);
        
        // Get team players
        $playersResult = $this->repository->getPlayersOnTeam($teamName, $teamID);
        
        // Render offensive set selector
        $this->view->renderOffenseSetLinks($offenseSetsResult, $this->db);
        
        echo "<hr>";
        
        // Display ratings table
        $table_ratings = \UI::ratings($this->db, $playersResult, $team, "", $season);
        echo $table_ratings;
        
        // Render form header
        $slotNames = [
            $offenseSet['PG_Depth_Name'],
            $offenseSet['SG_Depth_Name'],
            $offenseSet['SF_Depth_Name'],
            $offenseSet['PF_Depth_Name'],
            $offenseSet['C_Depth_Name']
        ];
        
        $this->view->renderFormHeader($teamName, $teamID, $offenseSet['offense_name'], $slotNames);
        
        // Define slot ranges (position eligibility)
        $slotRanges = [
            ['min' => 1, 'max' => 9],  // PG
            ['min' => 1, 'max' => 9],  // SG
            ['min' => 1, 'max' => 9],  // SF
            ['min' => 1, 'max' => 9],  // PF
            ['min' => 1, 'max' => 9]   // C
        ];
        
        // Render player rows
        $depthCount = 1;
        mysqli_data_seek($playersResult, 0);
        while ($player = $this->db->sql_fetchrow($playersResult)) {
            $this->view->renderPlayerRow($player, $depthCount, $slotRanges);
            $depthCount++;
        }
        
        // Render form footer
        $this->view->renderFormFooter();
        
        CloseTable();
        \Nuke\Footer::footer();
    }
    
    /**
     * Gets team name from username
     * 
     * @param string $username Username
     * @return string Team name
     */
    private function getUserTeamName(string $username): string
    {
        $sql = "SELECT user_ibl_team FROM nuke_users WHERE username='$username'";
        $result = $this->db->sql_query($sql);
        $userinfo = $this->db->sql_fetchrow($result);
        return $userinfo['user_ibl_team'];
    }
}
