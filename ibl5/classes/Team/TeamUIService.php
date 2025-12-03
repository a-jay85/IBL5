<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamUIServiceInterface;

/**
 * @see TeamUIServiceInterface
 */
class TeamUIService implements TeamUIServiceInterface
{
    private $db;
    private $repository;

    public function __construct($db, TeamRepository $repository)
    {
        $this->db = $db;
        $this->repository = $repository;
    }

    /**
     * @see TeamUIServiceInterface::renderTeamInfoRight()
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
     * @see TeamUIServiceInterface::renderTabs()
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

        if (in_array($season->phase, ["Playoffs", "Draft", "Free Agency"])) {
            $tabDefinitions['playoffs'] = 'Playoffs Averages';
        }

        $tabDefinitions['contracts'] = 'Contracts';

        $tabs = "";
        foreach ($tabDefinitions as $tabKey => $tabLabel) {
            $tabs .= $this->buildTab($tabKey, $tabLabel, $display, $teamID, $insertyear);
        }

        return $tabs;
    }

    private function buildTab(string $tabKey, string $tabLabel, string $display, int $teamID, string $insertyear): string
    {
        $team = \Team::initialize($this->db, $teamID);

        if ($display === $tabKey) {
            $isActiveLink = ' style="font-weight:bold; color: black !important;"';
            $isActiveTableCell = ' bgcolor="' . $team->color2 . '"';
        } else {
            $isActiveLink = '';
            $isActiveTableCell = '';
        }

        return "<td{$isActiveTableCell}><a href=\"modules.php?name=Team&op=team&teamID=$teamID&display=$tabKey$insertyear\"{$isActiveLink}>$tabLabel</a></td>";
    }

    /**
     * @see TeamUIServiceInterface::getTableOutput()
     */
    public function getTableOutput(string $display, object $db, mixed $result, object $team, ?string $yr, object $season, object $sharedFunctions): string
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

