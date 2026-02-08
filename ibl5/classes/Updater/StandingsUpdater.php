<?php

declare(strict_types=1);

namespace Updater;

use Utilities\RecordParser;
use Utilities\StandingsGrouper;

class StandingsUpdater extends \BaseMysqliRepository {
    private \Services\CommonMysqliRepository $commonRepository;

    /** @var array<string, int> Team name to ID lookup map */
    private array $teamNameToIdMap = [];

    public function __construct(object $db, \Services\CommonMysqliRepository $commonRepository) {
        parent::__construct($db);
        $this->commonRepository = $commonRepository;
    }

    /**
     * Pre-fetch all team name→ID mappings to avoid per-team lookups
     */
    private function preloadTeamNameMap(): void
    {
        /** @var list<array{team_name: string, teamid: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT team_name, teamid FROM ibl_team_info",
            ""
        );

        foreach ($rows as $row) {
            $this->teamNameToIdMap[$row['team_name']] = $row['teamid'];
        }
    }

    /**
     * Look up team ID by name, using pre-loaded map with fallback
     */
    private function resolveTeamId(string $teamName): ?int
    {
        return $this->teamNameToIdMap[$teamName] ?? $this->commonRepository->getTidFromTeamname($teamName);
    }

    protected function extractWins(string $record): int {
        return RecordParser::extractWins($record);
    }

    protected function extractLosses(string $record): int {
        return RecordParser::extractLosses($record);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function assignGroupingsFor(string $region): array {
        $groupings = StandingsGrouper::getGroupingsFor($region);
        // Return as indexed array for backwards compatibility with list() destructuring
        return [$groupings['grouping'], $groupings['groupingGB'], $groupings['groupingMagicNumber']];
    }

    public function update(): void {
        echo '<p>Updating the ibl_standings database table...<p>';
        $this->execute('TRUNCATE TABLE ibl_standings', '');
        echo 'TRUNCATE TABLE ibl_standings<p>';

        $this->preloadTeamNameMap();

        $this->extractStandingsValues();

        $this->updateMagicNumbers('Eastern');
        $this->updateMagicNumbers('Western');
        $this->updateMagicNumbers('Atlantic');
        $this->updateMagicNumbers('Central');
        $this->updateMagicNumbers('Midwest');
        $this->updateMagicNumbers('Pacific');

        echo '<p>Magic numbers for all teams have been updated.<p>';
        echo '<p>The ibl_standings table has been updated.<p>';
    }

    protected function extractStandingsValues(): void {
        echo '<p>Updating the conference standings for all teams...<p>';

        /** @var string $documentRoot */
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $standingsFilePath = $documentRoot . '/ibl5/ibl/IBL/Standings.htm';
        $standings = new \DOMDocument();
        $standings->loadHTMLFile($standingsFilePath);
        $standings->preserveWhiteSpace = false;

        $getRows = $standings->getElementsByTagName('tr');

        // Safely navigate the DOM tree with null checks
        $firstRow = $getRows->item(0);
        if ($firstRow === null) {
            echo '<p>Error: Unable to find standings table rows</p>';
            return;
        }

        $conferenceContainer = $firstRow->childNodes->item(0);
        if ($conferenceContainer === null) {
            echo '<p>Error: Unable to find conference container in standings</p>';
            return;
        }

        $conferenceContent = $conferenceContainer->childNodes->item(0);
        if ($conferenceContent === null) {
            echo '<p>Error: Unable to find conference content in standings</p>';
            return;
        }

        $rowsByConference = $conferenceContent->childNodes;

        $divisionContainer = $firstRow->childNodes->item(1);
        if ($divisionContainer === null) {
            echo '<p>Error: Unable to find division container in standings</p>';
            return;
        }

        $divisionContent = $divisionContainer->childNodes->item(0);
        if ($divisionContent === null) {
            echo '<p>Error: Unable to find division content in standings</p>';
            return;
        }

        $rowsByDivision = $divisionContent->childNodes;

        \UI::displayDebugOutput($this->processConferenceRows($rowsByConference), 'Conference Standings');
        \UI::displayDebugOutput($this->processDivisionRows($rowsByDivision), 'Division Standings');
    }

    /**
     * @param \DOMNodeList<\DOMNode> $rowsByConference
     */
    private function processConferenceRows(\DOMNodeList $rowsByConference): string {
        $log = '';
        $conference = '';

        foreach ($rowsByConference as $row) {
            $firstChild = $row->childNodes->item(0);
            $teamName = ($firstChild !== null) ? ($firstChild->nodeValue ?? '') : '';
            if (in_array($teamName, ['Eastern', 'Western'], true)) {
                $conference = $teamName;
            }
            if (!in_array($teamName, ['Eastern', 'Western', 'team', ''], true)) {
                $log .= $this->processTeamStandings($row, $conference);
            }
        }

        return $log;
    }

    private function processTeamStandings(\DOMNode $row, string $conference): string {
        $log = '';

        $nodeValue0 = $row->childNodes->item(0) !== null ? ($row->childNodes->item(0)->nodeValue ?? '') : '';
        $nodeValue1 = $row->childNodes->item(1) !== null ? ($row->childNodes->item(1)->nodeValue ?? '') : '';
        $nodeValue2 = $row->childNodes->item(2) !== null ? ($row->childNodes->item(2)->nodeValue ?? '') : '';
        $nodeValue3 = $row->childNodes->item(3) !== null ? ($row->childNodes->item(3)->nodeValue ?? '') : '';
        $nodeValue4 = $row->childNodes->item(4) !== null ? ($row->childNodes->item(4)->nodeValue ?? '') : '';
        $nodeValue5 = $row->childNodes->item(5) !== null ? ($row->childNodes->item(5)->nodeValue ?? '') : '';
        $nodeValue6 = $row->childNodes->item(6) !== null ? ($row->childNodes->item(6)->nodeValue ?? '') : '';
        $nodeValue7 = $row->childNodes->item(7) !== null ? ($row->childNodes->item(7)->nodeValue ?? '') : '';

        $teamID = $this->resolveTeamId($nodeValue0);
        $leagueRecord = $nodeValue1;
        $pct = $nodeValue2;
        $confGB = $nodeValue3;
        $confRecord = $nodeValue4;
        $divRecord = $nodeValue5;
        $homeRecord = $nodeValue6;
        $awayRecord = $nodeValue7;

        $confWins = $this->extractWins($confRecord);
        $confLosses = $this->extractLosses($confRecord);
        $divWins = $this->extractWins($divRecord);
        $divLosses = $this->extractLosses($divRecord);
        $homeWins = $this->extractWins($homeRecord);
        $homeLosses = $this->extractLosses($homeRecord);
        $awayWins = $this->extractWins($awayRecord);
        $awayLosses = $this->extractLosses($awayRecord);

        $gamesUnplayed = 82 - $homeWins - $homeLosses - $awayWins - $awayLosses;

        $teamNameTrimmed = rtrim($nodeValue0);

        $this->execute(
            "INSERT INTO ibl_standings (
                tid,
                team_name,
                leagueRecord,
                pct,
                gamesUnplayed,
                conference,
                confGB,
                confRecord,
                divRecord,
                homeRecord,
                awayRecord,
                confWins,
                confLosses,
                divWins,
                divLosses,
                homeWins,
                homeLosses,
                awayWins,
                awayLosses
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "isssissssssiiiiiiii",
            $teamID,
            $teamNameTrimmed,
            $leagueRecord,
            $pct,
            $gamesUnplayed,
            $conference,
            $confGB,
            $confRecord,
            $divRecord,
            $homeRecord,
            $awayRecord,
            $confWins,
            $confLosses,
            $divWins,
            $divLosses,
            $homeWins,
            $homeLosses,
            $awayWins,
            $awayLosses
        );

        $log .= "Inserted standings for team: {$teamNameTrimmed}<br>";
        return $log;
    }

    /**
     * @param \DOMNodeList<\DOMNode> $rowsByDivision
     */
    private function processDivisionRows(\DOMNodeList $rowsByDivision): string {
        $log = '';
        $division = '';

        foreach ($rowsByDivision as $row) {
            $firstChild = $row->childNodes->item(0);
            $teamName = ($firstChild !== null) ? ($firstChild->nodeValue ?? '') : '';

            if (in_array($teamName, ['Atlantic', 'Central', 'Midwest', 'Pacific'], true)) {
                $division = $teamName;
            }
            if (!in_array($teamName, ['Atlantic', 'Central', 'Midwest', 'Pacific', 'team', ''], true)) {
                $log .= $this->updateTeamDivision($row, $division);
            }
        }

        return $log;
    }

    private function updateTeamDivision(\DOMNode $row, string $division): string {
        $log = '';

        $nodeValue0 = $row->childNodes->item(0) !== null ? ($row->childNodes->item(0)->nodeValue ?? '') : '';
        $teamName = $nodeValue0;
        $teamID = $this->resolveTeamId($teamName);

        $nodeValue3 = $row->childNodes->item(3) !== null ? ($row->childNodes->item(3)->nodeValue ?? '') : '';
        $divGB = $nodeValue3;

        $this->execute(
            "UPDATE ibl_standings SET division = ?, divGB = ? WHERE tid = ?",
            "ssi",
            $division,
            $divGB,
            $teamID
        );

        $log .= "Updated division for team: {$teamName}<br>";
        return $log;
    }

    private function updateMagicNumbers(string $region): void {
        echo "<p>Updating the magic numbers for the {$region}...<br>";
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);

        $teams = $this->fetchAll(
            "SELECT tid, team_name, homeWins, homeLosses, awayWins, awayLosses
            FROM ibl_standings
            WHERE {$grouping} = ?
            ORDER BY pct DESC",
            "s",
            $region
        );

        $log = '';
        $numTeams = count($teams);

        for ($i = 0; $i < $numTeams; $i++) {
            /** @var array{tid: int, team_name: string, homeWins: int, homeLosses: int, awayWins: int, awayLosses: int} $teamRow */
            $teamRow = $teams[$i];
            $teamID = $teamRow['tid'];
            $teamName = $teamRow['team_name'];
            $teamTotalWins = $teamRow['homeWins'] + $teamRow['awayWins'];

            if ($i + 1 !== $numTeams) {
                /** @var array{tid: int, team_name: string, homeWins: int, homeLosses: int, awayWins: int, awayLosses: int} $belowTeamRow */
                $belowTeamRow = $teams[$i + 1];
                $belowTeamTotalLosses = $belowTeamRow['homeLosses'] + $belowTeamRow['awayLosses'];
            } else {
                $belowTeamTotalLosses = 0;
            }

            $magicNumber = 82 + 1 - $teamTotalWins - $belowTeamTotalLosses;

            $log .= $this->updateTeamMagicNumber($teamID, $teamName, $magicNumber, $groupingMagicNumber);
        }

        \UI::displayDebugOutput($log, "{$region} Magic Number Update Log");

        $this->checkIfRegionIsClinched($region);
        if ($grouping === 'conference') {
            $this->checkIfPlayoffsClinched($region);
        }
    }

    private function updateTeamMagicNumber(int $teamID, string $teamName, int $magicNumber, string $groupingMagicNumber): string {
        $log = '';

        $this->execute(
            "UPDATE ibl_standings SET {$groupingMagicNumber} = ? WHERE tid = ?",
            "ii",
            $magicNumber,
            $teamID
        );

        $log .= "Updated {$groupingMagicNumber} for {$teamName} to {$magicNumber}<br>";

        return $log;
    }

    private function checkIfRegionIsClinched(string $region): void {
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);
        echo "<p>Checking if the {$region} {$grouping} has been clinched...<br>";

        $winningestTeam = $this->fetchOne(
            "SELECT team_name, homeWins + awayWins AS wins
            FROM ibl_standings
            WHERE {$grouping} = ?
            ORDER BY wins DESC
            LIMIT 1",
            "s",
            $region
        );

        if ($winningestTeam === null) {
            return;
        }

        /** @var string $winningestTeamName */
        $winningestTeamName = $winningestTeam['team_name'];
        /** @var int $winningestTeamWins */
        $winningestTeamWins = $winningestTeam['wins'];

        $leastLosingestTeam = $this->fetchOne(
            "SELECT homeLosses + awayLosses AS losses
            FROM ibl_standings
            WHERE {$grouping} = ?
                AND team_name != ?
            ORDER BY losses ASC
            LIMIT 1",
            "ss",
            $region,
            $winningestTeamName
        );

        if ($leastLosingestTeam === null) {
            return;
        }

        /** @var int $leastLosingestTeamLosses */
        $leastLosingestTeamLosses = $leastLosingestTeam['losses'];

        $magicNumber = 82 + 1 - $winningestTeamWins - $leastLosingestTeamLosses;

        if ($magicNumber <= 0) {
            $this->execute(
                "UPDATE ibl_standings SET clinched" . ucfirst($grouping) . " = 1 WHERE team_name = ?",
                "s",
                $winningestTeamName
            );
            echo "The {$winningestTeamName} have clinched the {$region} {$grouping}!";
        }
    }

    private function checkIfPlayoffsClinched(string $conference): void {
        echo "<p>Checking if any teams have clinched playoff spots in the {$conference} Conference...<br>";

        $eightWinningestTeams = $this->fetchAll(
            "SELECT team_name, homeWins + awayWins AS wins
            FROM ibl_standings
            WHERE conference = ?
            ORDER BY wins DESC
            LIMIT 8",
            "s",
            $conference
        );

        $sixLosingestTeams = $this->fetchAll(
            "SELECT homeLosses + awayLosses AS losses
            FROM ibl_standings
            WHERE conference = ?
            ORDER BY losses DESC
            LIMIT 6",
            "s",
            $conference
        );

        for ($i = 0; $i < 8; $i++) {
            if (!isset($eightWinningestTeams[$i])) {
                continue;
            }

            /** @var string $contendingTeamName */
            $contendingTeamName = $eightWinningestTeams[$i]['team_name'];
            /** @var int $contendingTeamWins */
            $contendingTeamWins = $eightWinningestTeams[$i]['wins'];
            $teamsEliminated = 0;

            for ($j = 0; $j < 6; $j++) {
                if (!isset($sixLosingestTeams[$j])) {
                    continue;
                }

                /** @var int $bottomTeamLosses */
                $bottomTeamLosses = $sixLosingestTeams[$j]['losses'];
                $magicNumber = 82 + 1 - $contendingTeamWins - $bottomTeamLosses;

                if ($magicNumber <= 0) {
                    $teamsEliminated++;
                }
            }

            if ($teamsEliminated === 6) {
                $this->execute(
                    "UPDATE ibl_standings SET clinchedPlayoffs = 1 WHERE team_name = ?",
                    "s",
                    $contendingTeamName
                );
                echo "The {$contendingTeamName} have clinched a playoff spot!<br>";
            }
        }
    }
}
