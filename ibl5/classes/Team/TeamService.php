<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use UI\Components\TableViewSwitcher;

/**
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type TeamInfoRow from \Services\CommonMysqliRepository
 * @phpstan-import-type TeamPageData from Contracts\TeamServiceInterface
 * @phpstan-import-type StartersData from Contracts\TeamServiceInterface
 * @phpstan-import-type SidebarData from Contracts\TeamServiceInterface
 *
 * @see TeamServiceInterface
 */
class TeamService implements TeamServiceInterface
{
    private \mysqli $db;
    private TeamRepositoryInterface $repository;

    public function __construct(\mysqli $db, TeamRepositoryInterface $repository)
    {
        $this->db = $db;
        $this->repository = $repository;
    }

    /**
     * @see TeamServiceInterface::getTeamPageData()
     * @return TeamPageData
     */
    public function getTeamPageData(int $teamID, ?string $yr, string $display): array
    {
        global $leagueContext;
        /** @var \League\LeagueContext $leagueContext */

        $leagueConfig = $leagueContext->getConfig();
        /** @var string $imagesPath */
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

        if (in_array($season->phase, ["Playoffs", "Draft", "Free Agency"], true)) {
            $tabDefinitions['playoffs'] = 'Playoffs Averages';
        }

        $tabDefinitions['contracts'] = 'Contracts';

        $baseUrl = "modules.php?name=Team&op=team&teamID=$teamID" . $insertyear;
        $teamData = $this->repository->getTeam($teamID);
        $teamColor1 = $teamData['color1'] ?? '000000';
        $teamColor2 = $teamData['color2'] ?? 'FFFFFF';

        /** @var list<int> $starterPids */
        $starterPids = [];
        if ($teamID > 0 && ($yr === null || $yr === '')) {
            $starters = $this->extractStartersData($result);
            foreach ($starters as $data) {
                if ($data['pid'] !== null) {
                    $starterPids[] = $data['pid'];
                }
            }
        }

        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $teamColor1, $teamColor2);
        $tableHtml = $this->renderTableForDisplay($display, $result, $team, $yr, $season, $sharedFunctions, $starterPids);
        $tableOutput = $switcher->wrap($tableHtml);

        $isActualTeam = ($teamID !== 0);

        $teamModules = new TeamComponentsView($this->repository);
        $draftPicksTable = $isActualTeam ? $teamModules->draftPicks($team) : "";

        $currentSeasonCard = "";
        $awardsCard = "";
        $franchiseHistoryCard = "";
        $rafters = "";
        if ($isActualTeam) {
            $sidebarData = $this->renderTeamInfoRight($team);
            $currentSeasonCard = $sidebarData['currentSeasonCard'];
            $awardsCard = $sidebarData['awardsCard'];
            $franchiseHistoryCard = $sidebarData['franchiseHistoryCard'];
            $rafters = $sidebarData['rafters'];
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
            'draftPicksTable' => $draftPicksTable,
            'currentSeasonCard' => $currentSeasonCard,
            'awardsCard' => $awardsCard,
            'franchiseHistoryCard' => $franchiseHistoryCard,
            'rafters' => $rafters,
        ];
    }

    /**
     * @see TeamServiceInterface::extractStartersData()
     * @param list<array<string, mixed>> $roster
     * @return StartersData
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
                $depthValue = $player[$depthField] ?? null;
                $depthInt = is_int($depthValue) ? $depthValue : (is_string($depthValue) ? (int) $depthValue : 0);
                if ($depthInt === 1) {
                    $nameValue = $player['name'] ?? null;
                    $pidValue = $player['pid'] ?? null;
                    $starters[$position]['name'] = is_string($nameValue) ? $nameValue : null;
                    $starters[$position]['pid'] = is_int($pidValue) ? $pidValue : null;
                }
            }
        }

        /** @var StartersData $starters */
        return $starters;
    }

    /**
     * Render the appropriate table HTML based on display type
     *
     * @param list<PlayerRow>|list<array<string, mixed>> $result
     * @param list<int> $starterPids
     */
    private function renderTableForDisplay(string $display, array $result, \Team $team, ?string $yr, \Season $season, \Shared $sharedFunctions, array $starterPids = []): string
    {
        $yrStr = $yr ?? '';
        switch ($display) {
            case 'total_s':
                return \UI::seasonTotals($this->db, $result, $team, $yrStr, $starterPids);
            case 'avg_s':
                return \UI::seasonAverages($this->db, $result, $team, $yrStr, $starterPids);
            case 'per36mins':
                return \UI::per36Minutes($this->db, $result, $team, $yrStr, $starterPids);
            case 'chunk':
                return \UI::periodAverages($this->db, $team, $season, null, null, $starterPids);
            case 'playoffs':
                return \UI::periodAverages($this->db, $team, $season, $season->playoffsStartDate, $season->playoffsEndDate, $starterPids);
            case 'contracts':
                return \UI::contracts($this->db, $result, $team, $sharedFunctions, $starterPids);
            default:
                return \UI::ratings($this->db, $result, $team, $yrStr, $season, '', $starterPids);
        }
    }

    /**
     * Render team information right sidebar
     *
     * Absorbed from TeamUIService::renderTeamInfoRight()
     *
     * @return SidebarData
     */
    private function renderTeamInfoRight(\Team $team): array
    {
        $color1 = \UI\TableStyles::sanitizeColor($team->color1);
        $color2 = \UI\TableStyles::sanitizeColor($team->color2);
        $teamColorStyle = "--team-color-primary: #$color1; --team-color-secondary: #$color2;";

        $teamModules = new TeamComponentsView($this->repository);
        $rafters = $teamModules->championshipBanners($team);

        // Current Season card
        $currentSeason = $teamModules->currentSeason($team);
        $currentSeasonCard = "<div class=\"team-card\" style=\"$teamColorStyle\">"
            . '<div class="team-card__header"><h3 class="team-card__title">Current Season</h3></div>'
            . "<div class=\"team-card__body\">$currentSeason</div>"
            . '</div>';

        // Awards card — combines GM History and Team Accomplishments
        $awardsCard = '';
        $gmHistory = $teamModules->gmHistory($team);
        $teamAccomplishments = $teamModules->teamAccomplishments($team);
        if ($gmHistory !== '' || $teamAccomplishments !== '') {
            $awardsCard = "<div class=\"team-card\" style=\"$teamColorStyle\">"
                . '<div class="team-card__header"><h3 class="team-card__title">Awards</h3></div>';
            if ($gmHistory !== '') {
                $awardsCard .= "<div class=\"team-card__body\" style=\"padding-bottom: 0;\">"
                    . "<strong style=\"font-weight: 700; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500);\">GM History</strong>"
                    . "</div><div class=\"team-card__body\">$gmHistory</div>";
            }
            if ($teamAccomplishments !== '') {
                $awardsCard .= "<div class=\"team-card__body\" style=\"padding-bottom: 0; border-top: 1px solid var(--gray-100);\">"
                    . "<strong style=\"font-weight: 700; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500);\">Team Accomplishments</strong>"
                    . "</div><div class=\"team-card__body\">$teamAccomplishments</div>";
            }
            $awardsCard .= '</div>';
        }

        // Franchise History card — consolidates Regular Season, HEAT, and Playoffs
        $regularSeason = $teamModules->resultsRegularSeason($team);
        $heatHistory = $teamModules->resultsHEAT($team);
        $playoffResults = $teamModules->resultsPlayoffs($team);

        $franchiseHistoryCard = "<div class=\"team-card\" style=\"$teamColorStyle\">"
            . '<div class="team-card__header"><h3 class="team-card__title">Franchise History</h3></div>'
            . '<div class="franchise-history-columns">'
            . '<div class="franchise-history-column">'
            . '<h4 class="franchise-history-column__title">H.E.A.T.</h4>'
            . $heatHistory
            . '</div>'
            . '<div class="franchise-history-column">'
            . '<h4 class="franchise-history-column__title">Regular Season</h4>'
            . $regularSeason
            . '</div>'
            . '<div class="franchise-history-column">'
            . '<h4 class="franchise-history-column__title">Playoffs</h4>'
            . $playoffResults
            . '</div>'
            . '</div>'
            . '</div>';

        return [
            'currentSeasonCard' => $currentSeasonCard,
            'awardsCard' => $awardsCard,
            'franchiseHistoryCard' => $franchiseHistoryCard,
            'rafters' => $rafters,
        ];
    }

}
