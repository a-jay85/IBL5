<?php

declare(strict_types=1);

namespace Team;

use League\League;
use Team\Contracts\TeamTableServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use UI\Components\TableViewDropdown;
use UI\Components\TableViewSwitcher;
use Season\Season;

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
    public function getTableOutput(int $teamid, ?string $yr, string $display, ?string $split = null): string
    {
        $season = new Season($this->db);

        $isFreeAgency = $season->isOffseasonPhase();

        if ($teamid === 0) {
            $result = $this->repository->getFreeAgents($isFreeAgency);
        } elseif ($teamid === -1) {
            $result = $this->repository->getEntireLeagueRoster();
        } else {
            if ($yr !== null && $yr !== '') {
                $result = $this->repository->getHistoricalRoster($teamid, $yr);
            } elseif ($isFreeAgency) {
                $result = $this->repository->getFreeAgencyRoster($teamid);
            } else {
                $result = $this->repository->getRosterUnderContract($teamid);
            }
        }

        $insertyear = ($yr !== null && $yr !== '') ? "&yr=$yr" : "";
        $baseUrl = "modules.php?name=Team&op=team&teamid=$teamid" . $insertyear;
        $teamData = $this->repository->getTeam($teamid);
        $teamColor1 = is_string($teamData['color1'] ?? null) ? $teamData['color1'] : '000000';
        $teamColor2 = is_string($teamData['color2'] ?? null) ? $teamData['color2'] : 'FFFFFF';

        $team = Team::initialize($this->db, $teamid);

        /** @var list<int> $starterPids */
        $starterPids = [];
        if ($teamid > 0 && ($yr === null || $yr === '')) {
            $starters = $this->extractStartersData($result);
            foreach ($starters as $data) {
                if ($data['pid'] !== null) {
                    $starterPids[] = $data['pid'];
                }
            }
        }

        $tableHtml = $this->renderTableForDisplay($display, $result, $team, $yr, $season, $starterPids, $split);

        // HTMX API URL for tab/dropdown switching
        $apiUrl = 'modules.php?name=Team&op=api&teamid=' . $teamid . $insertyear;

        // Use dropdown for actual teams in current season; tabs for everything else
        $useDropdown = $teamid > 0 && ($yr === null || $yr === '');

        if ($useDropdown) {
            $dropdownGroups = $this->buildDropdownGroups($season);
            $activeValue = ($display === 'split' && $split !== null) ? 'split:' . $split : $display;
            $dropdown = new TableViewDropdown($dropdownGroups, $activeValue, $baseUrl, $teamColor1, $teamColor2, $apiUrl);
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

        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $teamColor1, $teamColor2, $apiUrl);
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
                $depthField = strtolower($position) . '_depth';
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
    public function getRosterAndStarters(int $teamid): array
    {
        $season = new Season($this->db);
        $isFreeAgency = $season->isOffseasonPhase();

        if ($isFreeAgency) {
            $result = $this->repository->getFreeAgencyRoster($teamid);
        } else {
            $result = $this->repository->getRosterUnderContract($teamid);
        }

        /** @var list<int> $starterPids */
        $starterPids = [];
        if ($teamid > 0) {
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
    public function renderTableForDisplay(string $display, array $result, Team $team, ?string $yr, Season $season, array $starterPids = [], ?string $split = null): string
    {
        $yrStr = $yr ?? '';
        switch ($display) {
            case 'total_s':
                return \UI\Tables\SeasonTotals::render($this->db, $result, $team, $yrStr, $starterPids);
            case 'avg_s':
                return \UI\Tables\SeasonAverages::render($this->db, $result, $team, $yrStr, $starterPids);
            case 'per36mins':
                return \UI\Tables\Per36Minutes::render($this->db, $result, $team, $yrStr, $starterPids);
            case 'chunk':
                return \UI\Tables\PeriodAverages::render($this->db, $team, $season, null, null, $starterPids);
            case 'playoffs':
                return \UI\Tables\PeriodAverages::render($this->db, $team, $season, $season->playoffsStartDate, $season->playoffsEndDate, $starterPids);
            case 'contracts':
                $cashRepo = new \Trading\CashConsiderationRepository($this->db);
                $cashRows = $cashRepo->getTeamCashConsiderations($team->teamid ?? 0);
                foreach ($cashRows as $cashRow) {
                    $result[] = self::cashConsiderationToRosterRow($cashRow);
                }
                return \UI\Tables\Contracts::render($this->db, $result, $team, $season, $starterPids);
            case 'split':
                return $this->renderSplitStats($team, $season, $split ?? 'home', $starterPids);
            default:
                return \UI\Tables\Ratings::render($this->db, $result, $team, $yrStr, $season, '', $starterPids);
        }
    }

    /**
     * Build the optgroup structure for the dropdown view selector
     *
     * @return array<string, array<string, string>>
     */
    public function buildDropdownGroups(Season $season): array
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
        foreach (League::DIVISION_NAMES as $division) {
            $vsDivision['split:div_' . strtolower($division)] = 'vs. ' . $division;
        }
        $groups['vs. Division'] = $vsDivision;

        // vs. Conference
        $vsConference = [];
        foreach (League::CONFERENCE_NAMES as $conference) {
            $vsConference['split:conf_' . strtolower($conference)] = 'vs. ' . $conference;
        }
        $groups['vs. Conference'] = $vsConference;

        // vs. Team
        $allTeams = $this->repository->getAllTeams();
        $vsTeam = [];
        foreach ($allTeams as $teamRow) {
            $teamid = $teamRow['teamid'];
            $teamName = $teamRow['team_name'];
            $vsTeam['split:vs_' . $teamid] = 'vs. ' . $teamName;
        }
        $groups['vs. Team'] = $vsTeam;

        return $groups;
    }

    /**
     * Render split stats table for a given split key
     *
     * @param list<int> $starterPids
     */
    private function renderSplitStats(Team $team, Season $season, string $splitKey, array $starterPids): string
    {
        $splitRepo = new SplitStatsRepository($this->db);
        $teamid = $team->teamid;
        $rows = $splitRepo->getSplitStats($teamid, $season->endingYear, $splitKey);
        $splitLabel = $splitRepo->getSplitLabel($splitKey);

        return \UI\Tables\SplitStats::render($rows, $team, $splitLabel, $starterPids);
    }

    /**
     * Convert a cash consideration row to the PlayerRow-compatible format
     * expected by Contracts::render().
     *
     * @param array<string, mixed> $cashRow Row from ibl_cash_considerations
     * @return array<string, mixed>
     */
    public static function cashConsiderationToRosterRow(array $cashRow): array
    {
        return [
            'pid' => 0,
            'name' => '| ' . (is_string($cashRow['label'] ?? null) ? $cashRow['label'] : ''),
            'nickname' => '',
            'ordinal' => 100000,
            'teamid' => $cashRow['teamid'] ?? 0,
            'pos' => '',
            'age' => null,
            'peak' => null,
            'color1' => null,
            'color2' => null,
            'oo' => 0, 'od' => 0, 'r_drive_off' => 0, 'dd' => 0,
            'po' => 0, 'pd' => 0, 'r_trans_off' => 0, 'td' => 0,
            'clutch' => null, 'consistency' => null,
            'talent' => 0, 'skill' => 0, 'intangibles' => 0,
            'loyalty' => null, 'playing_time' => null, 'winner' => null,
            'tradition' => null, 'security' => null,
            'exp' => 1, 'bird' => null,
            'cy' => $cashRow['cy'] ?? 1,
            'cyt' => $cashRow['cyt'] ?? 1,
            'cy1' => $cashRow['cy1'] ?? 0,
            'cy2' => $cashRow['cy2'] ?? 0,
            'cy3' => $cashRow['cy3'] ?? 0,
            'cy4' => $cashRow['cy4'] ?? 0,
            'cy5' => $cashRow['cy5'] ?? 0,
            'cy6' => $cashRow['cy6'] ?? 0,
            'retired' => 0,
            'droptime' => 0,
            'injured' => null,
            'htft' => 0, 'htin' => 0, 'wt' => 0,
            'draftyear' => 0, 'draftround' => 0, 'draftpickno' => 0,
            'draftedby' => '', 'draftedbycurrentname' => '', 'college' => '',
            'r_fga' => 0, 'r_fgp' => 0, 'r_fta' => 0, 'r_ftp' => 0,
            'r_3ga' => 0, 'r_3gp' => 0, 'r_orb' => 0, 'r_drb' => 0,
            'r_ast' => 0, 'r_stl' => 0, 'r_tvr' => 0, 'r_blk' => 0, 'r_foul' => 0,
            'isCashRow' => true,
        ];
    }
}
