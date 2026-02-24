<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use UI\Components\TableViewDropdown;
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
    public function getTeamPageData(int $teamID, ?string $yr, string $display, string $userTeamName = '', ?string $split = null): array
    {
        global $leagueContext;
        /** @var \League\LeagueContext $leagueContext */

        $leagueConfig = $leagueContext->getConfig();
        /** @var string $imagesPath */
        $imagesPath = $leagueConfig['images_path'];

        $team = \Team::initialize($this->db, $teamID);

        $insertyear = ($yr !== null && $yr !== '') ? "&yr=$yr" : "";

        $tableOutput = $this->getTableOutput($teamID, $yr, $display, $split);

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
            'userTeamName' => $userTeamName,
            'isOwnTeam' => ($userTeamName !== '' && $userTeamName === $team->name),
        ];
    }

    /**
     * @see TeamServiceInterface::getTableOutput()
     */
    public function getTableOutput(int $teamID, ?string $yr, string $display, ?string $split = null): string
    {
        $season = new \Season($this->db);

        $isFreeAgency = $season->isFreeAgencyPhase();

        if ($teamID === 0) {
            $result = $this->repository->getFreeAgents($isFreeAgency);
        } elseif ($teamID === -1) {
            $result = $this->repository->getEntireLeagueRoster();
        } else {
            if ($yr !== null && $yr !== '') {
                $result = $this->repository->getHistoricalRoster($teamID, $yr);
            } elseif ($isFreeAgency) {
                $result = $this->repository->getFreeAgencyRoster($teamID);
            } else {
                $result = $this->repository->getRosterUnderContract($teamID);
            }
        }

        $insertyear = ($yr !== null && $yr !== '') ? "&yr=$yr" : "";
        $baseUrl = "modules.php?name=Team&op=team&teamID=$teamID" . $insertyear;
        $teamData = $this->repository->getTeam($teamID);
        $teamColor1 = is_string($teamData['color1'] ?? null) ? $teamData['color1'] : '000000';
        $teamColor2 = is_string($teamData['color2'] ?? null) ? $teamData['color2'] : 'FFFFFF';

        $team = \Team::initialize($this->db, $teamID);

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

        $tableHtml = $this->renderTableForDisplay($display, $result, $team, $yr, $season, $starterPids, $split);

        // Use dropdown for actual teams in current season; tabs for everything else
        $useDropdown = $teamID > 0 && ($yr === null || $yr === '');

        if ($useDropdown) {
            $dropdownGroups = $this->buildDropdownGroups($season);
            $activeValue = ($display === 'split' && $split !== null) ? 'split:' . $split : $display;
            $dropdown = new TableViewDropdown($dropdownGroups, $activeValue, $baseUrl, $teamColor1, $teamColor2);
            return $dropdown->wrap($tableHtml);
        }

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

        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $teamColor1, $teamColor2);
        return $switcher->wrap($tableHtml);
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
     * @see TeamServiceInterface::getRosterAndStarters()
     * @return array{roster: list<array<string, mixed>>, starterPids: list<int>}
     */
    public function getRosterAndStarters(int $teamID): array
    {
        $season = new \Season($this->db);
        $isFreeAgency = $season->isFreeAgencyPhase();

        if ($isFreeAgency) {
            $result = $this->repository->getFreeAgencyRoster($teamID);
        } else {
            $result = $this->repository->getRosterUnderContract($teamID);
        }

        /** @var list<int> $starterPids */
        $starterPids = [];
        if ($teamID > 0) {
            $starters = $this->extractStartersData($result);
            foreach ($starters as $data) {
                if ($data['pid'] !== null) {
                    $starterPids[] = $data['pid'];
                }
            }
        }

        return ['roster' => $result, 'starterPids' => $starterPids];
    }

    /**
     * @see TeamServiceInterface::renderTableForDisplay()
     *
     * @param list<PlayerRow>|list<array<string, mixed>> $result
     * @param list<int> $starterPids
     */
    public function renderTableForDisplay(string $display, array $result, \Team $team, ?string $yr, \Season $season, array $starterPids = [], ?string $split = null): string
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
                return \UI::contracts($this->db, $result, $team, $season, $starterPids);
            case 'split':
                return $this->renderSplitStats($team, $season, $split ?? 'home', $starterPids);
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

    /**
     * Render split stats table for a given split key
     *
     * @param list<int> $starterPids
     */
    private function renderSplitStats(\Team $team, \Season $season, string $splitKey, array $starterPids): string
    {
        $splitRepo = new SplitStatsRepository($this->db);
        $teamID = $team->teamID;
        $rows = $splitRepo->getSplitStats($teamID, $season->endingYear, $splitKey);
        $splitLabel = $splitRepo->getSplitLabel($splitKey);

        return \UI\Tables\SplitStats::render($rows, $team, $splitLabel, $starterPids);
    }

    /**
     * Build the optgroup structure for the dropdown view selector
     *
     * @return array<string, array<string, string>>
     */
    public function buildDropdownGroups(\Season $season): array
    {
        $groups = [];

        // Views group
        $views = [
            'ratings' => 'Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
            'per36mins' => 'Per 36 Minutes',
            'chunk' => 'Sim Averages',
        ];

        if (in_array($season->phase, ["Playoffs", "Draft", "Free Agency"], true)) {
            $views['playoffs'] = 'Playoffs Averages';
        }

        $views['contracts'] = 'Contracts';
        $groups['Views'] = $views;

        // Location
        $groups['Location'] = [
            'split:home' => 'Home',
            'split:road' => 'Road',
        ];

        // Result
        $groups['Result'] = [
            'split:wins' => 'Wins',
            'split:losses' => 'Losses',
        ];

        // Season Half
        $groups['Season Half'] = [
            'split:pre_allstar' => 'Pre All-Star',
            'split:post_allstar' => 'Post All-Star',
        ];

        // By Month
        $months = [
            11 => 'November',
            12 => 'December',
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
        ];
        $byMonth = [];
        foreach ($months as $num => $name) {
            $byMonth['split:month_' . $num] = $name;
        }
        $groups['By Month'] = $byMonth;

        // vs. Division
        $vsDivision = [];
        foreach (\League::DIVISION_NAMES as $division) {
            $vsDivision['split:div_' . strtolower($division)] = 'vs. ' . $division;
        }
        $groups['vs. Division'] = $vsDivision;

        // vs. Conference
        $vsConference = [];
        foreach (\League::CONFERENCE_NAMES as $conference) {
            $vsConference['split:conf_' . strtolower($conference)] = 'vs. ' . $conference;
        }
        $groups['vs. Conference'] = $vsConference;

        // vs. Team
        $allTeams = $this->repository->getAllTeams();
        $vsTeam = [];
        foreach ($allTeams as $teamRow) {
            $tid = $teamRow['teamid'];
            $teamName = $teamRow['team_name'];
            $vsTeam['split:vs_' . $tid] = 'vs. ' . $teamName;
        }
        $groups['vs. Team'] = $vsTeam;

        return $groups;
    }
}
