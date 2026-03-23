<?php

declare(strict_types=1);

namespace Updater;

use League\LeagueContext;
use Utilities\RecordParser;
use Utilities\SeasonPhaseHelper;
use Utilities\StandingsGrouper;
use Season\Season;

/**
 * Computes league standings from game results in ibl_schedule
 * and conference/division assignments from ibl_league_config,
 * then populates the ibl_standings table.
 *
 * @phpstan-type TeamStanding array{
 *     tid: int,
 *     teamName: string,
 *     conference: string,
 *     division: string,
 *     wins: int,
 *     losses: int,
 *     homeWins: int,
 *     homeLosses: int,
 *     awayWins: int,
 *     awayLosses: int,
 *     confWins: int,
 *     confLosses: int,
 *     divWins: int,
 *     divLosses: int
 * }
 * @phpstan-type TeamMapping array{conference: string, division: string, teamName: string}
 */
class StandingsUpdater extends \BaseMysqliRepository {
    /** @var array<string, string> */
    private const REGION_AWARD_MAP = [
        'Atlantic' => 'Atlantic Division Champions',
        'Central'  => 'Central Division Champions',
        'Midwest'  => 'Midwest Division Champions',
        'Pacific'  => 'Pacific Division Champions',
        'Eastern'  => 'Eastern Conference Champions',
        'Western'  => 'Western Conference Champions',
    ];

    private Season $season;

    public function __construct(\mysqli $db, Season $season, ?LeagueContext $leagueContext = null) {
        parent::__construct($db, $leagueContext);
        $this->season = $season;
    }

    private function upsertTeamAward(string $teamName, string $awardName): void
    {
        if ($this->leagueContext !== null) {
            return;
        }

        $this->execute(
            "INSERT INTO ibl_team_awards (year, name, Award)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name)",
            "iss",
            $this->season->endingYear,
            $teamName,
            $awardName
        );
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
        return [$groupings['grouping'], $groupings['groupingGB'], $groupings['groupingMagicNumber']];
    }

    public function update(): void {
        $standingsTable = $this->resolveTable('ibl_standings');

        echo "<p>Updating the {$standingsTable} database table...<p>";

        $this->computeAndInsertStandings();

        $this->updateMagicNumbers('Eastern');
        $this->updateMagicNumbers('Western');
        $this->updateMagicNumbers('Atlantic');
        $this->updateMagicNumbers('Central');
        $this->updateMagicNumbers('Midwest');
        $this->updateMagicNumbers('Pacific');

        $this->checkIfLeagueClinched();

        echo '<p>Magic numbers for all teams have been updated.<p>';
        echo "<p>The {$standingsTable} table has been updated.<p>";
    }

    /**
     * Compute standings from ibl_schedule game results and insert into ibl_standings
     */
    protected function computeAndInsertStandings(): void {
        echo '<p>Computing standings from game results...<p>';

        $teamMap = $this->fetchTeamMap();
        if ($teamMap === []) {
            echo '<p>Error: No league config found for season ending year ' . $this->season->endingYear . '</p>';
            return;
        }

        $games = $this->fetchPlayedGames();

        $standings = $this->initializeStandings($teamMap);
        $standings = $this->tallyGameResults($games, $standings, $teamMap);

        $this->computeAndInsertAll($standings, $teamMap);
    }

    /**
     * Fetch conference/division mapping from league config table for the current season
     *
     * @return array<int, TeamMapping> Map of tid → {conference, division, teamName}
     */
    protected function fetchTeamMap(): array
    {
        $leagueConfigTable = $this->resolveTable('ibl_league_config');

        /** @var list<array{team_slot: int, team_name: string, conference: string, division: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT team_slot, team_name, conference, division
            FROM {$leagueConfigTable}
            WHERE season_ending_year = ?",
            "i",
            $this->season->endingYear
        );

        /** @var array<int, TeamMapping> $map */
        $map = [];
        foreach ($rows as $row) {
            $map[$row['team_slot']] = [
                'conference' => $row['conference'],
                'division' => $row['division'],
                'teamName' => $row['team_name'],
            ];
        }

        return $map;
    }

    /**
     * Fetch all played games from schedule table for the current season
     *
     * @return list<array{Visitor: int, VScore: int, Home: int, HScore: int}>
     */
    protected function fetchPlayedGames(): array
    {
        $scheduleTable = $this->resolveTable('ibl_schedule');
        $month = SeasonPhaseHelper::getMonthForPhase($this->season->phase);
        $startDate = $this->season->beginningYear . "-{$month}-01";
        $endDate = $this->season->endingYear . "-05-30";

        /** @var list<array{Visitor: int, VScore: int, Home: int, HScore: int}> */
        return $this->fetchAll(
            "SELECT Visitor, VScore, Home, HScore
            FROM {$scheduleTable}
            WHERE VScore > 0 AND HScore > 0
            AND Date BETWEEN ? AND ?
            ORDER BY Date ASC",
            "ss",
            $startDate,
            $endDate
        );
    }

    /**
     * Initialize per-team standings counters to zero
     *
     * @param array<int, TeamMapping> $teamMap
     * @return array<int, TeamStanding>
     */
    private function initializeStandings(array $teamMap): array
    {
        /** @var array<int, TeamStanding> $standings */
        $standings = [];

        foreach ($teamMap as $tid => $info) {
            $standings[$tid] = [
                'tid' => $tid,
                'teamName' => $info['teamName'],
                'conference' => $info['conference'],
                'division' => $info['division'],
                'wins' => 0,
                'losses' => 0,
                'homeWins' => 0,
                'homeLosses' => 0,
                'awayWins' => 0,
                'awayLosses' => 0,
                'confWins' => 0,
                'confLosses' => 0,
                'divWins' => 0,
                'divLosses' => 0,
            ];
        }

        return $standings;
    }

    /**
     * Tally all game results into per-team standings
     *
     * @param list<array{Visitor: int, VScore: int, Home: int, HScore: int}> $games
     * @param array<int, TeamStanding> $standings
     * @param array<int, TeamMapping> $teamMap
     * @return array<int, TeamStanding>
     */
    private function tallyGameResults(array $games, array $standings, array $teamMap): array
    {
        foreach ($games as $game) {
            $visitorTid = $game['Visitor'];
            $homeTid = $game['Home'];

            if (!isset($standings[$visitorTid]) || !isset($standings[$homeTid])) {
                continue;
            }

            /** @var TeamStanding $visitor */
            $visitor = $standings[$visitorTid];
            /** @var TeamStanding $home */
            $home = $standings[$homeTid];

            $visitorWon = $game['VScore'] > $game['HScore'];

            if ($visitorWon) {
                $visitor['wins']++;
                $visitor['awayWins']++;
                $home['losses']++;
                $home['homeLosses']++;
            } else {
                $home['wins']++;
                $home['homeWins']++;
                $visitor['losses']++;
                $visitor['awayLosses']++;
            }

            $sameConference = isset($teamMap[$visitorTid], $teamMap[$homeTid])
                && $teamMap[$visitorTid]['conference'] === $teamMap[$homeTid]['conference'];

            if ($sameConference) {
                if ($visitorWon) {
                    $visitor['confWins']++;
                    $home['confLosses']++;
                } else {
                    $home['confWins']++;
                    $visitor['confLosses']++;
                }
            }

            $sameDivision = $sameConference
                && $teamMap[$visitorTid]['division'] === $teamMap[$homeTid]['division'];

            if ($sameDivision) {
                if ($visitorWon) {
                    $visitor['divWins']++;
                    $home['divLosses']++;
                } else {
                    $home['divWins']++;
                    $visitor['divLosses']++;
                }
            }

            $standings[$visitorTid] = $visitor;
            $standings[$homeTid] = $home;
        }

        return $standings;
    }

    /**
     * Compute derived fields (pct, GB, records) and insert all teams into ibl_standings
     *
     * @param array<int, TeamStanding> $standings
     * @param array<int, TeamMapping> $teamMap
     */
    private function computeAndInsertAll(array $standings, array $teamMap): void
    {
        // Group teams by conference to compute conference GB
        /** @var array<string, list<TeamStanding>> $byConference */
        $byConference = [];
        /** @var array<string, list<TeamStanding>> $byDivision */
        $byDivision = [];

        foreach ($standings as $team) {
            $byConference[$team['conference']][] = $team;
            $byDivision[$team['division']][] = $team;
        }

        // Sort each group by pct DESC to find leaders
        $pctSorter = static function (array $a, array $b): int {
            /** @var int $winsA */
            $winsA = $a['wins'];
            /** @var int $lossesA */
            $lossesA = $a['losses'];
            /** @var int $winsB */
            $winsB = $b['wins'];
            /** @var int $lossesB */
            $lossesB = $b['losses'];
            $totalA = $winsA + $lossesA;
            $totalB = $winsB + $lossesB;
            $pctA = $totalA > 0 ? $winsA / $totalA : 0.0;
            $pctB = $totalB > 0 ? $winsB / $totalB : 0.0;
            return $pctB <=> $pctA;
        };

        /** @var array<string, float> $confLeaderGB */
        $confLeaderGB = [];
        foreach ($byConference as $conf => $teams) {
            usort($teams, $pctSorter);
            $leader = $teams[0];
            $confLeaderGB[$conf] = ($leader['wins'] - $leader['losses']) / 2.0;
        }

        /** @var array<string, float> $divLeaderGB */
        $divLeaderGB = [];
        foreach ($byDivision as $div => $teams) {
            usort($teams, $pctSorter);
            $leader = $teams[0];
            $divLeaderGB[$div] = ($leader['wins'] - $leader['losses']) / 2.0;
        }

        $standingsTable = $this->resolveTable('ibl_standings');
        $log = '';

        foreach ($standings as $team) {
            $totalGames = $team['wins'] + $team['losses'];
            $pct = $totalGames > 0 ? round($team['wins'] / $totalGames, 3) : 0.000;
            $gamesUnplayed = 82 - $team['homeWins'] - $team['homeLosses'] - $team['awayWins'] - $team['awayLosses'];

            $leagueRecord = $team['wins'] . '-' . $team['losses'];
            $confRecord = $team['confWins'] . '-' . $team['confLosses'];
            $divRecord = $team['divWins'] . '-' . $team['divLosses'];
            $homeRecord = $team['homeWins'] . '-' . $team['homeLosses'];
            $awayRecord = $team['awayWins'] . '-' . $team['awayLosses'];

            $teamGB = ($team['wins'] - $team['losses']) / 2.0;
            $confGB = $confLeaderGB[$team['conference']] - $teamGB;
            $divGB = $divLeaderGB[$team['division']] - $teamGB;

            $this->execute(
                "INSERT INTO {$standingsTable} (
                    tid, team_name, leagueRecord, wins, losses, pct, gamesUnplayed,
                    conference, confGB, confRecord,
                    division, divGB, divRecord,
                    homeRecord, awayRecord,
                    confWins, confLosses, divWins, divLosses,
                    homeWins, homeLosses, awayWins, awayLosses
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    team_name = VALUES(team_name),
                    leagueRecord = VALUES(leagueRecord),
                    wins = VALUES(wins),
                    losses = VALUES(losses),
                    pct = VALUES(pct),
                    gamesUnplayed = VALUES(gamesUnplayed),
                    conference = VALUES(conference),
                    confGB = VALUES(confGB),
                    confRecord = VALUES(confRecord),
                    division = VALUES(division),
                    divGB = VALUES(divGB),
                    divRecord = VALUES(divRecord),
                    homeRecord = VALUES(homeRecord),
                    awayRecord = VALUES(awayRecord),
                    confWins = VALUES(confWins),
                    confLosses = VALUES(confLosses),
                    divWins = VALUES(divWins),
                    divLosses = VALUES(divLosses),
                    homeWins = VALUES(homeWins),
                    homeLosses = VALUES(homeLosses),
                    awayWins = VALUES(awayWins),
                    awayLosses = VALUES(awayLosses),
                    confMagicNumber = NULL,
                    divMagicNumber = NULL,
                    clinchedConference = NULL,
                    clinchedDivision = NULL,
                    clinchedPlayoffs = NULL,
                    clinchedLeague = NULL",
                "issiidisdssdsssiiiiiiii",
                $team['tid'],
                $team['teamName'],
                $leagueRecord,
                $team['wins'],
                $team['losses'],
                $pct,
                $gamesUnplayed,
                $team['conference'],
                $confGB,
                $confRecord,
                $team['division'],
                $divGB,
                $divRecord,
                $homeRecord,
                $awayRecord,
                $team['confWins'],
                $team['confLosses'],
                $team['divWins'],
                $team['divLosses'],
                $team['homeWins'],
                $team['homeLosses'],
                $team['awayWins'],
                $team['awayLosses']
            );

            $log .= "Inserted standings for team: {$team['teamName']}<br>";
        }

        \UI\DebugOutput::display($log, 'Computed Standings');
    }

    private function updateMagicNumbers(string $region): void {
        $standingsTable = $this->resolveTable('ibl_standings');

        echo "<p>Updating the magic numbers for the {$region}...<br>";
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);

        $teams = $this->fetchAll(
            "SELECT tid, team_name, homeWins, homeLosses, awayWins, awayLosses
            FROM {$standingsTable}
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

        \UI\DebugOutput::display($log, "{$region} Magic Number Update Log");

        $this->checkIfRegionIsClinched($region);
        if ($grouping === 'conference') {
            $this->checkIfPlayoffsClinched($region);
        }
    }

    private function updateTeamMagicNumber(int $teamID, string $teamName, int $magicNumber, string $groupingMagicNumber): string {
        $standingsTable = $this->resolveTable('ibl_standings');
        $log = '';

        $this->execute(
            "UPDATE {$standingsTable} SET {$groupingMagicNumber} = ? WHERE tid = ?",
            "ii",
            $magicNumber,
            $teamID
        );

        $log .= "Updated {$groupingMagicNumber} for {$teamName} to {$magicNumber}<br>";

        return $log;
    }

    private function checkIfRegionIsClinched(string $region): void {
        $standingsTable = $this->resolveTable('ibl_standings');
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);
        echo "<p>Checking if the {$region} {$grouping} has been clinched...<br>";

        /** @var list<array{tid: int, team_name: string, wins: int}> $topTeams */
        $topTeams = $this->fetchAll(
            "SELECT tid, team_name, homeWins + awayWins AS wins
            FROM {$standingsTable}
            WHERE {$grouping} = ?
            ORDER BY wins DESC
            LIMIT 2",
            "s",
            $region
        );

        if (count($topTeams) < 2) {
            return;
        }

        $first = $topTeams[0];
        $second = $topTeams[1];

        // Head-to-head tiebreaker when tied in wins
        if ($first['wins'] === $second['wins']) {
            $winnerId = $this->getHeadToHeadWinner($first['tid'], $second['tid']);
            if ($winnerId === $second['tid']) {
                [$first, $second] = [$second, $first];
            }
        }

        /** @var string $winningestTeamName */
        $winningestTeamName = $first['team_name'];
        /** @var int $winningestTeamWins */
        $winningestTeamWins = $first['wins'];

        $leastLosingestTeam = $this->fetchOne(
            "SELECT homeLosses + awayLosses AS losses
            FROM {$standingsTable}
            WHERE {$grouping} = ?
                AND team_name <> ?
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

        // When tied at the top and season is over, H2H winner has clinched
        if ($magicNumber > 0 && $first['wins'] === $second['wins']) {
            if ($this->isRegionSeasonOver($grouping, $region)) {
                $magicNumber = 0;
            }
        }

        if ($magicNumber <= 0) {
            $this->execute(
                "UPDATE {$standingsTable} SET clinched" . ucfirst($grouping) . " = 1 WHERE team_name = ?",
                "s",
                $winningestTeamName
            );
            echo "The {$winningestTeamName} have clinched the {$region} {$grouping}!";

            $awardName = self::REGION_AWARD_MAP[$region] ?? null;
            if ($awardName !== null) {
                $this->upsertTeamAward($winningestTeamName, $awardName);
            }
        }
    }

    private function checkIfLeagueClinched(): void {
        $standingsTable = $this->resolveTable('ibl_standings');
        echo '<p>Checking if any team has clinched the best league record...<br>';

        /** @var list<array{tid: int, team_name: string, wins: int}> $topTeams */
        $topTeams = $this->fetchAll(
            "SELECT tid, team_name, homeWins + awayWins AS wins
            FROM {$standingsTable}
            ORDER BY wins DESC
            LIMIT 2",
            ""
        );

        if (count($topTeams) < 2) {
            return;
        }

        $first = $topTeams[0];
        $second = $topTeams[1];

        // Head-to-head tiebreaker when tied in wins
        if ($first['wins'] === $second['wins']) {
            $winnerId = $this->getHeadToHeadWinner($first['tid'], $second['tid']);
            if ($winnerId === $second['tid']) {
                [$first, $second] = [$second, $first];
            }
        }

        /** @var string $winningestTeamName */
        $winningestTeamName = $first['team_name'];
        /** @var int $winningestTeamWins */
        $winningestTeamWins = $first['wins'];

        $leastLosingestTeam = $this->fetchOne(
            "SELECT homeLosses + awayLosses AS losses
            FROM {$standingsTable}
            WHERE team_name <> ?
            ORDER BY losses ASC
            LIMIT 1",
            "s",
            $winningestTeamName
        );

        if ($leastLosingestTeam === null) {
            return;
        }

        /** @var int $leastLosingestTeamLosses */
        $leastLosingestTeamLosses = $leastLosingestTeam['losses'];

        $magicNumber = 82 + 1 - $winningestTeamWins - $leastLosingestTeamLosses;

        // When tied at the top and season is over, H2H winner has clinched
        if ($magicNumber > 0 && $first['wins'] === $second['wins']) {
            if ($this->isRegionSeasonOver('', '')) {
                $magicNumber = 0;
            }
        }

        if ($magicNumber <= 0) {
            $this->execute(
                "UPDATE {$standingsTable} SET clinchedLeague = 1 WHERE team_name = ?",
                "s",
                $winningestTeamName
            );
            echo "The {$winningestTeamName} have clinched the best record in the league!";
        }
    }

    /**
     * Check if all teams in a region (or league-wide) have finished the season
     *
     * @param string $grouping Column name ('conference', 'division', or '' for league-wide)
     * @param string $region Region value (e.g. 'Eastern', 'Atlantic', or '' for league-wide)
     */
    private function isRegionSeasonOver(string $grouping, string $region): bool {
        $standingsTable = $this->resolveTable('ibl_standings');
        if ($grouping !== '' && $region !== '') {
            $result = $this->fetchOne(
                "SELECT MAX(gamesUnplayed) AS maxLeft FROM {$standingsTable} WHERE {$grouping} = ?",
                "s",
                $region
            );
        } else {
            $result = $this->fetchOne(
                "SELECT MAX(gamesUnplayed) AS maxLeft FROM {$standingsTable}",
                ""
            );
        }

        return $result !== null && $result['maxLeft'] === 0;
    }

    /**
     * Determine the head-to-head winner between two teams for the current season
     *
     * Counts wins from games played between the two teams. If the head-to-head
     * record is also tied, defaults to tid1 (higher seed by database ordering).
     *
     * @param int $tid1 First team ID
     * @param int $tid2 Second team ID
     * @return int The tid of the team that won the head-to-head series
     */
    private function getHeadToHeadWinner(int $tid1, int $tid2): int {
        $scheduleTable = $this->resolveTable('ibl_schedule');
        $month = SeasonPhaseHelper::getMonthForPhase($this->season->phase);
        $startDate = $this->season->beginningYear . "-{$month}-01";
        $endDate = $this->season->endingYear . '-05-30';

        $result = $this->fetchOne(
            "SELECT
                COUNT(*) AS total_games,
                SUM(CASE
                    WHEN (Visitor = ? AND VScore > HScore) OR (Home = ? AND HScore > VScore) THEN 1
                    ELSE 0
                END) AS team1_wins
            FROM {$scheduleTable}
            WHERE VScore > 0 AND HScore > 0
            AND Date BETWEEN ? AND ?
            AND ((Visitor = ? AND Home = ?) OR (Visitor = ? AND Home = ?))",
            "iissiiii",
            $tid1, $tid1,
            $startDate, $endDate,
            $tid1, $tid2, $tid2, $tid1
        );

        if ($result === null) {
            return $tid1;
        }

        /** @var int $totalGames */
        $totalGames = $result['total_games'];
        /** @var int $team1Wins */
        $team1Wins = $result['team1_wins'];
        $team2Wins = $totalGames - $team1Wins;

        return $team1Wins >= $team2Wins ? $tid1 : $tid2;
    }

    private function checkIfPlayoffsClinched(string $conference): void {
        $standingsTable = $this->resolveTable('ibl_standings');

        echo "<p>Checking if any teams have clinched playoff spots in the {$conference} Conference...<br>";

        $eightWinningestTeams = $this->fetchAll(
            "SELECT team_name, homeWins + awayWins AS wins
            FROM {$standingsTable}
            WHERE conference = ?
            ORDER BY wins DESC
            LIMIT 8",
            "s",
            $conference
        );

        $sixLosingestTeams = $this->fetchAll(
            "SELECT homeLosses + awayLosses AS losses
            FROM {$standingsTable}
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
                    "UPDATE {$standingsTable} SET clinchedPlayoffs = 1 WHERE team_name = ?",
                    "s",
                    $contendingTeamName
                );
                echo "The {$contendingTeamName} have clinched a playoff spot!<br>";
            }
        }
    }
}
