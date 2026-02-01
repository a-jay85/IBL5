<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use UI\Components\StartersLineupComponent;
use UI\Components\TableViewSwitcher;

/**
 * @see TeamServiceInterface
 */
class TeamService implements TeamServiceInterface
{
    private object $db;
    private TeamRepositoryInterface $repository;

    public function __construct(object $db, TeamRepositoryInterface $repository)
    {
        $this->db = $db;
        $this->repository = $repository;
    }

    /**
     * @see TeamServiceInterface::getTeamPageData()
     */
    public function getTeamPageData(int $teamID, ?string $yr, string $display): array
    {
        global $leagueContext;

        $leagueConfig = $leagueContext->getConfig();
        $imagesPath = $leagueConfig['images_path'];

        $team = \Team::initialize($this->db, $teamID);

        $sharedFunctions = new \Shared($this->db);
        $season = new \Season($this->db);

        $isFreeAgencyModuleActive = $sharedFunctions->isFreeAgencyModuleActive();

        if ($teamID === 0) {
            if ($isFreeAgencyModuleActive === 0) {
                $result = $this->repository->getFreeAgents(false);
            } else {
                $result = $this->repository->getFreeAgents(true);
            }
        } elseif ($teamID === -1) {
            $result = $this->repository->getEntireLeagueRoster();
        } else {
            if ($yr !== null && $yr !== '') {
                $result = $this->repository->getHistoricalRoster($teamID, $yr);
            } elseif ($isFreeAgencyModuleActive === 1) {
                $result = $this->repository->getFreeAgencyRoster($teamID);
            } else {
                $result = $this->repository->getRosterUnderContract($teamID);
            }
        }

        $insertyear = ($yr !== null && $yr !== '') ? "&yr=$yr" : "";

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

        $baseUrl = "modules.php?name=Team&op=team&teamID=$teamID" . $insertyear;
        $teamData = $this->repository->getTeam($teamID);
        $teamColor1 = $teamData['color1'] ?? '000000';
        $teamColor2 = $teamData['color2'] ?? 'FFFFFF';

        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $teamColor1, $teamColor2);
        $tableHtml = $this->renderTableForDisplay($display, $result, $team, $yr, $season, $sharedFunctions);
        $tableOutput = $switcher->wrap($tableHtml);

        $startersTable = "";
        if ($teamID > 0 && ($yr === null || $yr === '')) {
            $startersTable = $this->getLastSimsStarters($result, $team);
        }

        $isActualTeam = ($teamID !== 0);

        $teamModules = new \UI\Modules\Team($this->repository);
        $draftPicksTable = ($isActualTeam && $team) ? $teamModules->draftPicks($team) : "";

        $teamInfoRight = "";
        $rafters = "";
        if ($isActualTeam) {
            $inforight = $this->renderTeamInfoRight($team);
            $teamInfoRight = $inforight[0];
            $rafters = $inforight[1];
        }

        return [
            'teamID' => $teamID,
            'team' => $team,
            'imagesPath' => $imagesPath,
            'yr' => $yr,
            'display' => $display,
            'insertyear' => $insertyear,
            'isActualTeam' => $isActualTeam,
            'tableOutput' => $tableOutput,
            'startersTable' => $startersTable,
            'draftPicksTable' => $draftPicksTable,
            'teamInfoRight' => $teamInfoRight,
            'rafters' => $rafters,
        ];
    }

    /**
     * @see TeamServiceInterface::extractStartersData()
     */
    public function extractStartersData(array $roster): array
    {
        $starters = [
            'PG' => ['name' => null, 'pid' => null],
            'SG' => ['name' => null, 'pid' => null],
            'SF' => ['name' => null, 'pid' => null],
            'PF' => ['name' => null, 'pid' => null],
            'C' => ['name' => null, 'pid' => null],
        ];

        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];

        foreach ($roster as $player) {
            foreach ($positions as $position) {
                $depthField = $position . 'Depth';
                if (isset($player[$depthField]) && (int) $player[$depthField] === 1) {
                    $starters[$position]['name'] = $player['name'] ?? null;
                    $starters[$position]['pid'] = $player['pid'] ?? null;
                }
            }
        }

        return $starters;
    }

    /**
     * Render the appropriate table HTML based on display type
     */
    private function renderTableForDisplay(string $display, mixed $result, object $team, ?string $yr, object $season, object $sharedFunctions): string
    {
        switch ($display) {
            case 'total_s':
                return \UI::seasonTotals($this->db, $result, $team, $yr);
            case 'avg_s':
                return \UI::seasonAverages($this->db, $result, $team, $yr);
            case 'per36mins':
                return \UI::per36Minutes($this->db, $result, $team, $yr);
            case 'chunk':
                return \UI::periodAverages($this->db, $team, $season);
            case 'playoffs':
                return \UI::periodAverages($this->db, $team, $season, $season->playoffsStartDate, $season->playoffsEndDate);
            case 'contracts':
                return \UI::contracts($this->db, $result, $team, $sharedFunctions);
            default:
                return \UI::ratings($this->db, $result, $team, $yr, $season);
        }
    }

    /**
     * Render team information right sidebar
     *
     * Absorbed from TeamUIService::renderTeamInfoRight()
     */
    private function renderTeamInfoRight(object $team): array
    {
        $output = "<table style=\"background-color: #eeeeee;\" width=\"220\">";

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
     * Render HTML table for team's last simulation starting lineup
     *
     * Absorbed from TeamStatsService::getLastSimsStarters()
     */
    private function getLastSimsStarters(array $result, object $team): string
    {
        $starters = $this->extractStartersData($result);
        $startersComponent = new StartersLineupComponent();
        return $startersComponent->render($starters, $team->color1, $team->color2);
    }
}
