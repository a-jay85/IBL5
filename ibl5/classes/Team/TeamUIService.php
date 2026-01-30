<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamUIServiceInterface;

/**
 * @see TeamUIServiceInterface
 */
class TeamUIService implements TeamUIServiceInterface
{
    private $repository;

    public function __construct(TeamRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see TeamUIServiceInterface::renderTeamInfoRight()
     */
    public function renderTeamInfoRight($team): array
    {
        $output = "<table bgcolor=#eeeeee width=220>";

        $teamModules = new \UI\Modules\Team($this->repository);
        $output .= $teamModules->currentSeason($team);
        $output .= $teamModules->gmHistory($team);
        $rafters = $teamModules->championshipBanners($team);
        $output .= $teamModules->teamAccomplishments($team);
        $output .= $teamModules->resultsRegularSeason($team);
        $output .= $teamModules->resultsHEAT($team);
        $output .= $teamModules->resultsPlayoffs($team);

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

        // Fetch team data once to avoid N+1 queries
        $teamData = $this->repository->getTeam($teamID);
        $teamColor1 = \UI\TableStyles::sanitizeColor($teamData['color1'] ?? '000000');
        $teamColor2 = \UI\TableStyles::sanitizeColor($teamData['color2'] ?? 'FFFFFF');

        $tabs = '';
        foreach ($tabDefinitions as $tabKey => $tabLabel) {
            $tabs .= $this->buildTab($tabKey, $tabLabel, $display, $teamID, $insertyear);
        }

        return '<div class="ibl-tabs" style="--team-tab-bg-color: #' . $teamColor1 . '; --team-tab-active-color: #' . $teamColor2 . '">' . $tabs . '</div>';
    }

    private function buildTab(string $tabKey, string $tabLabel, string $display, int $teamID, string $insertyear): string
    {
        $activeClass = ($display === $tabKey) ? ' ibl-tab--active' : '';
        $href = "modules.php?name=Team&amp;op=team&amp;teamID=$teamID&amp;display=$tabKey" . \Utilities\HtmlSanitizer::safeHtmlOutput($insertyear);

        return '<a href="' . $href . '" class="ibl-tab' . $activeClass . '">' . \Utilities\HtmlSanitizer::safeHtmlOutput($tabLabel) . '</a>';
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

