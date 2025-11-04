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
    public function renderTeamInfoRight($team): array
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
    public function renderTabs(int $teamID, string $display, string $insertyear, $season): string
    {
        $tabDefinitions = [
            'ratings' => 'Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
            'per36mins' => 'Per 36 Minutes',
            'chunk' => 'Sim Averages',
        ];

        // Add playoff tab if in appropriate phase
        if (in_array($season->phase, ["Playoffs", "Draft", "Free Agency"])) {
            $tabDefinitions['playoffs'] = 'Playoffs Averages';
        }

        // Contracts tab always comes last
        $tabDefinitions['contracts'] = 'Contracts';

        $tabs = "";
        foreach ($tabDefinitions as $tabKey => $tabLabel) {
            $tabs .= $this->buildTab($tabKey, $tabLabel, $display, $teamID, $insertyear);
        }

        return $tabs;
    }

    /**
     * Build a single tab HTML element
     */
    private function buildTab(string $tabKey, string $tabLabel, string $display, int $teamID, string $insertyear): string
    {
        $isActive = ($display === $tabKey) ? " bgcolor=#BBBBBB style=\"font-weight:bold\"" : "";
        return "<td{$isActive}><a href=\"modules.php?name=Team&op=team&teamID=$teamID&display=$tabKey$insertyear\">$tabLabel</a></td>";
    }

    /**
     * Get the display title based on the current display type
     */
    public function getDisplayTitle(string $display): string
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
    public function getTableOutput(string $display, $db, $result, $team, ?string $yr, $season, $sharedFunctions): string
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
