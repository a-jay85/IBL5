<?php

namespace Team;

/**
 * TeamUIService - Handles UI rendering for team pages
 * 
 * This service extracts UI/presentation logic from the controller,
 * generating HTML for various team information displays.
 */
class TeamUIService
{
    private $db;
    private $repository;

    public function __construct($db, TeamRepository $repository)
    {
        $this->db = $db;
        $this->repository = $repository;
    }

    /**
     * Render team info right sidebar
     * Returns array with [main content, rafters/banners]
     */
    public function renderTeamInfoRight($team)
    {
        $output = "<table bgcolor=#eeeeee width=220>";

        $output .= \UI\Modules\Team::currentSeason($this->db, $team);
        $output .= \UI\Modules\Team::gmHistory($this->db, $team);
        $rafters = \UI\Modules\Team::championshipBanners($this->db, $team);
        $output .= \UI\Modules\Team::teamAccomplishments($this->db, $team);
        $output .= \UI\Modules\Team::resultsRegularSeason($this->db, $team);
        $output .= \UI\Modules\Team::resultsHEAT($this->db, $team);
        $output .= \UI\Modules\Team::resultsPlayoffs($this->db, $team);

        $output .= "</table>";

        return [$output, $rafters];
    }

    /**
     * Render tab navigation for team displays
     */
    public function renderTabs($teamID, $display, $insertyear, $team)
    {
        $tabs = "";
        
        // Ratings tab
        if ($display == "ratings") {
            $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
        } else {
            $tabs .= "<td>";
        }
        $tabs .= "<a href=\"modules.php?name=Team&op=team&teamID=$teamID&display=ratings$insertyear\">Ratings</a></td>";

        // Season Totals tab
        if ($display == "total_s") {
            $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
        } else {
            $tabs .= "<td>";
        }
        $tabs .= "<a href=\"modules.php?name=Team&op=team&teamID=$teamID&display=total_s$insertyear\">Season Totals</a></td>";

        // Season Averages tab
        if ($display == "avg_s") {
            $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
        } else {
            $tabs .= "<td>";
        }
        $tabs .= "<a href=\"modules.php?name=Team&op=team&teamID=$teamID&display=avg_s$insertyear\">Season Averages</a></td>";

        // Per 36 Minutes tab
        if ($display == "per36mins") {
            $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
        } else {
            $tabs .= "<td>";
        }
        $tabs .= "<a href=\"modules.php?name=Team&op=team&teamID=$teamID&display=per36mins$insertyear\">Per 36 Minutes</a></td>";

        // Chunk Averages tab
        if ($display == "chunk") {
            $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
        } else {
            $tabs .= "<td>";
        }
        $tabs .= "<a href=\"modules.php?name=Team&op=team&teamID=$teamID&display=chunk$insertyear\">Sim Averages</a></td>";

        return $tabs;
    }

    /**
     * Add playoff tab if in playoff/draft/free agency phase
     */
    public function addPlayoffTab($display, $teamID, $insertyear, $season)
    {
        $tabs = "";
        
        if (
            $season->phase == "Playoffs"
            OR $season->phase == "Draft"
            OR $season->phase == "Free Agency"
        ) {
            if ($display == "playoffs") {
                $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
            } else {
                $tabs .= "<td>";
            }
            $tabs .= "<a href=\"modules.php?name=Team&op=team&teamID=$teamID&display=playoffs$insertyear\">Playoffs Averages</a></td>";
        }
        
        return $tabs;
    }

    /**
     * Add contracts tab
     */
    public function addContractsTab($display, $teamID, $insertyear)
    {
        $tabs = "";
        
        if ($display == "contracts") {
            $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
        } else {
            $tabs .= "<td>";
        }
        $tabs .= "<a href=\"modules.php?name=Team&op=team&teamID=$teamID&display=contracts$insertyear\">Contracts</a></td>";
        
        return $tabs;
    }

    /**
     * Get the display title based on the current display type
     */
    public function getDisplayTitle($display)
    {
        $titles = [
            'ratings' => 'Player Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
            'per36mins' => 'Per 36 Minutes',
            'chunk' => 'Chunk Averages',
            'playoffs' => 'Playoff Averages',
            'contracts' => 'Contracts',
        ];

        return $titles[$display] ?? 'Player Ratings';
    }

    /**
     * Get the table output based on display type
     */
    public function getTableOutput($display, $db, $result, $team, $yr, $season, $sharedFunctions)
    {
        switch ($display) {
            case 'ratings':
                return \UI::ratings($db, $result, $team, $yr, $season);
            case 'total_s':
                return \UI::seasonTotals($db, $result, $team, $yr);
            case 'avg_s':
                return \UI::seasonAverages($db, $result, $team, $yr);
            case 'per36mins':
                return \UI::per36Minutes($db, $result, $team, $yr);
            case 'chunk':
                return \UI::periodAverages($db, $team, $season);
            case 'playoffs':
                return \UI::periodAverages($db, $team, $season, $season->playoffsStartDate, $season->playoffsEndDate);
            case 'contracts':
                return \UI::contracts($db, $result, $team, $sharedFunctions);
            default:
                return \UI::ratings($db, $result, $team, $yr, $season);
        }
    }
}
