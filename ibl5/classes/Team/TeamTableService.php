<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamTableServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use UI\Components\TableViewDropdown;
use UI\Components\TableViewSwitcher;

/**
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type StartersData from Contracts\TeamServiceInterface
 *
 * @see TeamTableServiceInterface
 */
class TeamTableService implements TeamTableServiceInterface
{
    private \mysqli $db;
    private TeamRepositoryInterface $repository;

    public function __construct(\mysqli $db, TeamRepositoryInterface $repository)
    {
        $this->db = $db;
        $this->repository = $repository;
    }

    /**
     * @see TeamTableServiceInterface::getTableOutput()
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
     * @see TeamTableServiceInterface::extractStartersData()
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
     * @see TeamTableServiceInterface::getRosterAndStarters()
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
     * @see TeamTableServiceInterface::renderTableForDisplay()
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
}
