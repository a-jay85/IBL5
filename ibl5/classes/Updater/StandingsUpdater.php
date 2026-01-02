<?php
namespace Updater;

use League\LeagueContext;

class StandingsUpdater extends \BaseMysqliRepository {
    private $commonRepository;
    private ?LeagueContext $leagueContext;

    public function __construct(object $db, $commonRepository, ?LeagueContext $leagueContext = null) {
        parent::__construct($db);
        $this->commonRepository = $commonRepository;
        $this->leagueContext = $leagueContext;
    }

    private function extractWins($var) {
        $var = rtrim(substr($var, 0, 2), '-');
        return $var;
    }

    private function extractLosses($var) {
        $var = ltrim(substr($var, -2, 2), '-');
        return $var;
    }

    private function assignGroupingsFor($region) {
        if (in_array($region, array("Eastern", "Western"))) {
            $grouping = 'conference';
            $groupingGB = 'confGB';
            $groupingMagicNumber = 'confMagicNumber';
        }
        if (in_array($region, array("Atlantic", "Central", "Midwest", "Pacific"))) {
            $grouping = 'division';
            $groupingGB = 'divGB';
            $groupingMagicNumber = 'divMagicNumber';
        }
        return array($grouping, $groupingGB, $groupingMagicNumber);
    }

    public function update() {
        $standingsTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_standings') : 'ibl_standings';
        
        echo '<p>Updating the ' . $standingsTable . ' database table...<p>';
        $this->execute('TRUNCATE TABLE ' . $standingsTable, '');
        echo 'TRUNCATE TABLE ' . $standingsTable . '<p>';

        $this->extractStandingsValues();
        
        $this->updateMagicNumbers('Eastern');
        $this->updateMagicNumbers('Western');
        $this->updateMagicNumbers('Atlantic');
        $this->updateMagicNumbers('Central');
        $this->updateMagicNumbers('Midwest');
        $this->updateMagicNumbers('Pacific');
        
        echo '<p>Magic numbers for all teams have been updated.<p>';
        echo '<p>The ' . $standingsTable . ' table has been updated.<p>';
    }

    protected function extractStandingsValues() {
        echo '<p>Updating the conference standings for all teams...<p>';

        $standingsFilePath = $_SERVER['DOCUMENT_ROOT'] . '/ibl5/ibl/IBL/Standings.htm';
        $standings = new \DOMDocument();
        $standings->loadHTMLFile($standingsFilePath);
        $standings->preserveWhiteSpace = false;

        $getRows = $standings->getElementsByTagName('tr');
        
        // Safely navigate the DOM tree with null checks
        $firstRow = $getRows->item(0);
        if (!$firstRow) {
            echo '<p>Error: Unable to find standings table rows</p>';
            return;
        }
        
        $conferenceContainer = $firstRow->childNodes->item(0);
        if (!$conferenceContainer || !$conferenceContainer->childNodes) {
            echo '<p>Error: Unable to find conference container in standings</p>';
            return;
        }
        
        $conferenceContent = $conferenceContainer->childNodes->item(0);
        if (!$conferenceContent || !$conferenceContent->childNodes) {
            echo '<p>Error: Unable to find conference content in standings</p>';
            return;
        }
        
        $rowsByConference = $conferenceContent->childNodes;
        
        $divisionContainer = $firstRow->childNodes->item(1);
        if (!$divisionContainer || !$divisionContainer->childNodes) {
            echo '<p>Error: Unable to find division container in standings</p>';
            return;
        }
        
        $divisionContent = $divisionContainer->childNodes->item(0);
        if (!$divisionContent || !$divisionContent->childNodes) {
            echo '<p>Error: Unable to find division content in standings</p>';
            return;
        }
        
        $rowsByDivision = $divisionContent->childNodes;

        \UI::displayDebugOutput($this->processConferenceRows($rowsByConference), 'Conference Standings');
        \UI::displayDebugOutput($this->processDivisionRows($rowsByDivision), 'Division Standings');
    }

    private function processConferenceRows($rowsByConference) {
        $log = '';

        foreach ($rowsByConference as $row) {
            if (!is_null($row->childNodes)) {
                $firstChild = $row->childNodes->item(0);
                $teamName = $firstChild ? $firstChild->nodeValue : '';
                if (in_array($teamName, array("Eastern", "Western"))) {
                    $conference = $teamName;
                }
                if (!in_array($teamName, array("Eastern", "Western", "team", ""))) {
                    $log .= $this->processTeamStandings($row, $conference);
                }
            }
        }

        return $log;
    }

    private function processTeamStandings($row, $conference) {
        $log = '';
        $standingsTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_standings') : 'ibl_standings';

        $teamID = $this->commonRepository->getTidFromTeamname($row->childNodes->item(0)->nodeValue); // This function now returns an integer
        $leagueRecord = $row->childNodes->item(1)->nodeValue;
        $pct = $row->childNodes->item(2)->nodeValue;
        $confGB = $row->childNodes->item(3)->nodeValue;
        $confRecord = $row->childNodes->item(4)->nodeValue;
        $divRecord = $row->childNodes->item(5)->nodeValue;
        $homeRecord = $row->childNodes->item(6)->nodeValue;
        $awayRecord = $row->childNodes->item(7)->nodeValue;

        $confWins = $this->extractWins($confRecord);
        $confLosses = $this->extractLosses($confRecord);
        $divWins = $this->extractWins($divRecord);
        $divLosses = $this->extractLosses($divRecord);
        $homeWins = $this->extractWins($homeRecord);
        $homeLosses = $this->extractLosses($homeRecord);
        $awayWins = $this->extractWins($awayRecord);
        $awayLosses = $this->extractLosses($awayRecord);

        $gamesUnplayed = 82 - $homeWins - $homeLosses - $awayWins - $awayLosses;

        $this->execute(
            "INSERT INTO " . $standingsTable . " (
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
            rtrim($row->childNodes->item(0)->nodeValue),
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

        $log .= "Inserted standings for team: " . rtrim($row->childNodes->item(0)->nodeValue) . '<br>';
        return $log;
    }

    private function processDivisionRows($rowsByDivision) {
        $log = '';

        foreach ($rowsByDivision as $row) {
            if (!is_null($row->childNodes)) {
                $firstChild = $row->childNodes->item(0);
                $teamName = $firstChild ? $firstChild->nodeValue : '';
                
                if (in_array($teamName, array("Atlantic", "Central", "Midwest", "Pacific"))) {
                    $division = $teamName;
                }
                if (!in_array($teamName, array("Atlantic", "Central", "Midwest", "Pacific", "team", ""))) {
                    $log .= $this->updateTeamDivision($row, $division);
                }
            }
        }

        return $log;
    }

    private function updateTeamDivision($row, $division) {
        $log = '';
        $standingsTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_standings') : 'ibl_standings';
        
        $teamName = $row->childNodes->item(0)->nodeValue;
        $teamID = $this->commonRepository->getTidFromTeamname($teamName);
        $divGB = $row->childNodes->item(3)->nodeValue;

        $this->execute(
            "UPDATE " . $standingsTable . " SET division = ?, divGB = ? WHERE tid = ?",
            "ssi",
            $division,
            $divGB,
            $teamID
        );

        $log .= "Updated division for team: {$teamName}<br>";
        return $log;
    }

    private function updateMagicNumbers($region) {
        echo "<p>Updating the magic numbers for the $region...<br>";
        $standingsTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_standings') : 'ibl_standings';
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);

        $teams = $this->fetchAll(
            "SELECT tid, team_name, homeWins, homeLosses, awayWins, awayLosses
            FROM " . $standingsTable . "
            WHERE $grouping = ?
            ORDER BY pct DESC",
            "s",
            $region
        );

        $log = '';
        $numTeams = count($teams);

        for ($i = 0; $i < $numTeams; $i++) {
            $teamID = $teams[$i]['tid'];
            $teamName = $teams[$i]['team_name'];
            $teamTotalWins = $teams[$i]['homeWins'] + $teams[$i]['awayWins'];
            
            if ($i + 1 != $numTeams) {
                $belowTeamTotalLosses = $teams[$i + 1]['homeLosses'] + $teams[$i + 1]['awayLosses'];
            } else {
                $belowTeamTotalLosses = 0;
            }
            
            $magicNumber = 82 + 1 - $teamTotalWins - $belowTeamTotalLosses;

            $log .= $this->updateTeamMagicNumber($teamID, $teamName, $magicNumber, $groupingMagicNumber);
        }

        \UI::displayDebugOutput($log, "$region Magic Number Update Log");

        $this->checkIfRegionIsClinched($region);
        if ($grouping == 'conference') {
            $this->checkIfPlayoffsClinched($region);
        }
    }

    private function updateTeamMagicNumber($teamID, $teamName, $magicNumber, $groupingMagicNumber) {
        $log = '';
        $standingsTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_standings') : 'ibl_standings';

        $this->execute(
            "UPDATE " . $standingsTable . " SET $groupingMagicNumber = ? WHERE tid = ?",
            "ii",
            $magicNumber,
            $teamID
        );

        $log .= "Updated {$groupingMagicNumber} for {$teamName} to {$magicNumber}<br>";

        return $log;
    }

    private function checkIfRegionIsClinched($region) {
        $standingsTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_standings') : 'ibl_standings';
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);
        echo "<p>Checking if the $region $grouping has been clinched...<br>";

        $winningestTeam = $this->fetchOne(
            "SELECT team_name, homeWins + awayWins AS wins
            FROM " . $standingsTable . "
            WHERE $grouping = ?
            ORDER BY wins DESC
            LIMIT 1",
            "s",
            $region
        );
        
        if (!$winningestTeam) {
            return;
        }
        
        $winningestTeamName = $winningestTeam['team_name'];
        $winningestTeamWins = $winningestTeam['wins'];

        $leastLosingestTeam = $this->fetchOne(
            "SELECT homeLosses + awayLosses AS losses
            FROM " . $standingsTable . "
            WHERE $grouping = ?
                AND team_name != ?
            ORDER BY losses ASC
            LIMIT 1",
            "ss",
            $region,
            $winningestTeamName
        );
        
        if (!$leastLosingestTeam) {
            return;
        }
        
        $leastLosingestTeamLosses = $leastLosingestTeam['losses'];

        $magicNumber = 82 + 1 - $winningestTeamWins - $leastLosingestTeamLosses;

        if ($magicNumber <= 0) {
            $this->execute(
                "UPDATE " . $standingsTable . " SET clinched" . ucfirst($grouping) . " = 1 WHERE team_name = ?",
                "s",
                $winningestTeamName
            );
            echo "The $winningestTeamName have clinched the $region $grouping!";
        }
    }

    private function checkIfPlayoffsClinched($conference) {
        $standingsTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_standings') : 'ibl_standings';
        echo "<p>Checking if any teams have clinched playoff spots in the $conference Conference...<br>";

        $eightWinningestTeams = $this->fetchAll(
            "SELECT team_name, homeWins + awayWins AS wins
            FROM " . $standingsTable . "
            WHERE conference = ?
            ORDER BY wins DESC
            LIMIT 8",
            "s",
            $conference
        );

        $sixLosingestTeams = $this->fetchAll(
            "SELECT homeLosses + awayLosses AS losses
            FROM " . $standingsTable . "
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
            
            $contendingTeamName = $eightWinningestTeams[$i]['team_name'];
            $contendingTeamWins = $eightWinningestTeams[$i]['wins'];
            $teamsEliminated = 0;

            for ($j = 0; $j < 6; $j++) {
                if (!isset($sixLosingestTeams[$j])) {
                    continue;
                }
                
                $bottomTeamLosses = $sixLosingestTeams[$j]['losses'];
                $magicNumber = 82 + 1 - $contendingTeamWins - $bottomTeamLosses;

                if ($magicNumber <= 0) {
                    $teamsEliminated++;
                }
            }

            if ($teamsEliminated == 6) {
                $this->execute(
                    "UPDATE " . $standingsTable . " SET clinchedPlayoffs = 1 WHERE team_name = ?",
                    "s",
                    $contendingTeamName
                );
                echo "The $contendingTeamName have clinched a playoff spot!<br>";
            }
        }
    }
}
