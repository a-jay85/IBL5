<?php

declare(strict_types=1);

namespace Updater;

use Season\Season;
use Standings\Contracts\StandingsRepositoryInterface;

/**
 * Computes league standings from game results in ibl_schedule
 * and conference/division assignments from `ibl_league_config`,
 * then populates the ibl_standings table.
 *
 * @phpstan-type TeamStanding array{
 *     teamid: int,
 *     teamName: string,
 *     conference: string,
 *     division: string,
 *     wins: int,
 *     losses: int,
 *     home_wins: int,
 *     home_losses: int,
 *     away_wins: int,
 *     away_losses: int,
 *     conf_wins: int,
 *     conf_losses: int,
 *     div_wins: int,
 *     div_losses: int
 * }
 * @phpstan-import-type TeamMapping from StandingsRepositoryInterface
 */
class StandingsUpdater {
    /** @var array<string, string> */
    public const REGION_AWARD_MAP = [
        'Atlantic' => 'Atlantic Division Champions',
        'Central'  => 'Central Division Champions',
        'Midwest'  => 'Midwest Division Champions',
        'Pacific'  => 'Pacific Division Champions',
        'Eastern'  => 'Eastern Conference Champions',
        'Western'  => 'Western Conference Champions',
    ];

    private Season $season;
    private StandingsRepositoryInterface $repository;
    private bool $isOlympics;

    public function __construct(StandingsRepositoryInterface $repository, Season $season, bool $isOlympics = false) {
        $this->repository = $repository;
        $this->season = $season;
        $this->isOlympics = $isOlympics;
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
        echo "<p>Updating the standings database table...<p>";

        $this->computeAndInsertStandings();

        $this->updateMagicNumbers('Eastern');
        $this->updateMagicNumbers('Western');
        $this->updateMagicNumbers('Atlantic');
        $this->updateMagicNumbers('Central');
        $this->updateMagicNumbers('Midwest');
        $this->updateMagicNumbers('Pacific');

        $this->checkClinched(null, null);

        echo '<p>Magic numbers for all teams have been updated.<p>';
        echo "<p>The standings table has been updated.<p>";
    }

    /**
     * Compute standings from `ibl_schedule` game results and insert into `ibl_standings`
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
     * @return array<int, TeamMapping>
     */
    protected function fetchTeamMap(): array
    {
        return $this->repository->fetchTeamMapForSeason($this->season->endingYear);
    }

    /**
     * @return list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}>
     */
    protected function fetchPlayedGames(): array
    {
        $month = SeasonPhaseHelper::getMonthForPhase($this->season->phase);
        $startDate = $this->season->beginningYear . "-{$month}-01";
        $endDate = $this->season->endingYear . "-05-30";
        return $this->repository->fetchPlayedGamesForSeason($startDate, $endDate);
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

        foreach ($teamMap as $teamid => $info) {
            $standings[$teamid] = [
                'teamid' => $teamid,
                'teamName' => $info['teamName'],
                'conference' => $info['conference'],
                'division' => $info['division'],
                'wins' => 0,
                'losses' => 0,
                'home_wins' => 0,
                'home_losses' => 0,
                'away_wins' => 0,
                'away_losses' => 0,
                'conf_wins' => 0,
                'conf_losses' => 0,
                'div_wins' => 0,
                'div_losses' => 0,
            ];
        }

        return $standings;
    }

    /**
     * Tally all game results into per-team standings
     *
     * @param list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}> $games
     * @param array<int, TeamStanding> $standings
     * @param array<int, TeamMapping> $teamMap
     * @return array<int, TeamStanding>
     */
    private function tallyGameResults(array $games, array $standings, array $teamMap): array
    {
        foreach ($games as $game) {
            $visitorTid = $game['visitor_teamid'];
            $homeTid = $game['home_teamid'];

            if (!isset($standings[$visitorTid]) || !isset($standings[$homeTid])) {
                continue;
            }

            /** @var TeamStanding $visitor */
            $visitor = $standings[$visitorTid];
            /** @var TeamStanding $home */
            $home = $standings[$homeTid];

            $visitorWon = $game['visitor_score'] > $game['home_score'];

            if ($visitorWon) {
                $visitor['wins']++;
                $visitor['away_wins']++;
                $home['losses']++;
                $home['home_losses']++;
            } else {
                $home['wins']++;
                $home['home_wins']++;
                $visitor['losses']++;
                $visitor['away_losses']++;
            }

            $sameConference = isset($teamMap[$visitorTid], $teamMap[$homeTid])
                && $teamMap[$visitorTid]['conference'] === $teamMap[$homeTid]['conference'];

            if ($sameConference) {
                if ($visitorWon) {
                    $visitor['conf_wins']++;
                    $home['conf_losses']++;
                } else {
                    $home['conf_wins']++;
                    $visitor['conf_losses']++;
                }
            }

            $sameDivision = $sameConference
                && $teamMap[$visitorTid]['division'] === $teamMap[$homeTid]['division'];

            if ($sameDivision) {
                if ($visitorWon) {
                    $visitor['div_wins']++;
                    $home['div_losses']++;
                } else {
                    $home['div_wins']++;
                    $visitor['div_losses']++;
                }
            }

            $standings[$visitorTid] = $visitor;
            $standings[$homeTid] = $home;
        }

        return $standings;
    }

    /**
     * Compute derived fields (pct, GB, records) and insert all teams into `ibl_standings`
     *
     * @param array<int, TeamStanding> $standings
     * @param array<int, TeamMapping> $teamMap
     */
    private function computeAndInsertAll(array $standings, array $teamMap): void
    {
        /** @var array<string, list<TeamStanding>> $byConference */
        $byConference = [];
        /** @var array<string, list<TeamStanding>> $byDivision */
        $byDivision = [];

        foreach ($standings as $team) {
            $byConference[$team['conference']][] = $team;
            $byDivision[$team['division']][] = $team;
        }

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

        $log = '';

        foreach ($standings as $team) {
            $totalGames = $team['wins'] + $team['losses'];
            $pct = $totalGames > 0 ? round($team['wins'] / $totalGames, 3) : 0.000;
            $gamesUnplayed = 82 - $team['home_wins'] - $team['home_losses'] - $team['away_wins'] - $team['away_losses'];

            $leagueRecord = $team['wins'] . '-' . $team['losses'];
            $confRecord = $team['conf_wins'] . '-' . $team['conf_losses'];
            $divRecord = $team['div_wins'] . '-' . $team['div_losses'];
            $homeRecord = $team['home_wins'] . '-' . $team['home_losses'];
            $awayRecord = $team['away_wins'] . '-' . $team['away_losses'];

            $teamGB = ($team['wins'] - $team['losses']) / 2.0;
            $confGb = $confLeaderGB[$team['conference']] - $teamGB;
            $divGb = $divLeaderGB[$team['division']] - $teamGB;

            $this->repository->upsertStandings([
                'teamid' => $team['teamid'],
                'teamName' => $team['teamName'],
                'leagueRecord' => $leagueRecord,
                'wins' => $team['wins'],
                'losses' => $team['losses'],
                'pct' => $pct,
                'gamesUnplayed' => $gamesUnplayed,
                'conference' => $team['conference'],
                'confGb' => $confGb,
                'confRecord' => $confRecord,
                'division' => $team['division'],
                'divGb' => $divGb,
                'divRecord' => $divRecord,
                'homeRecord' => $homeRecord,
                'awayRecord' => $awayRecord,
                'confWins' => $team['conf_wins'],
                'confLosses' => $team['conf_losses'],
                'divWins' => $team['div_wins'],
                'divLosses' => $team['div_losses'],
                'homeWins' => $team['home_wins'],
                'homeLosses' => $team['home_losses'],
                'awayWins' => $team['away_wins'],
                'awayLosses' => $team['away_losses'],
            ]);

            $log .= "Inserted standings for team: {$team['teamName']}<br>";
        }

        \UI\DebugOutput::display($log, 'Computed Standings');
    }

    private function updateMagicNumbers(string $region): void {
        echo "<p>Updating the magic numbers for the {$region}...<br>";
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);

        $teams = $this->repository->fetchTeamsByRegion($grouping, $region);

        $log = '';
        $numTeams = count($teams);

        for ($i = 0; $i < $numTeams; $i++) {
            /** @var array{teamid: int, team_name: string, home_wins: int, home_losses: int, away_wins: int, away_losses: int} $teamRow */
            $teamRow = $teams[$i];
            $teamid = $teamRow['teamid'];
            $teamName = $teamRow['team_name'];
            $teamTotalWins = $teamRow['home_wins'] + $teamRow['away_wins'];

            if ($i + 1 !== $numTeams) {
                /** @var array{teamid: int, team_name: string, home_wins: int, home_losses: int, away_wins: int, away_losses: int} $belowTeamRow */
                $belowTeamRow = $teams[$i + 1];
                $belowTeamTotalLosses = $belowTeamRow['home_losses'] + $belowTeamRow['away_losses'];
            } else {
                $belowTeamTotalLosses = 0;
            }

            $magicNumber = 82 + 1 - $teamTotalWins - $belowTeamTotalLosses;

            $this->repository->updateMagicNumber($teamid, $magicNumber, $groupingMagicNumber);
            $log .= "Updated {$groupingMagicNumber} for {$teamName} to {$magicNumber}<br>";
        }

        \UI\DebugOutput::display($log, "{$region} Magic Number Update Log");

        $this->checkClinched($grouping, $region);
        if ($grouping === 'conference') {
            $this->checkIfPlayoffsClinched($region);
        }
    }

    /**
     * Check if a region (or the league) has been clinched by a team.
     *
     * @param string|null $grouping Column name ('conference', 'division'), or null for league-wide
     * @param string|null $region Region value (e.g. 'Eastern'), or null for league-wide
     */
    private function checkClinched(?string $grouping, ?string $region): void {
        $label = $grouping !== null && $region !== null
            ? "{$region} {$grouping}"
            : 'best league record';
        echo "<p>Checking if the {$label} has been clinched...<br>";

        $topTeams = $this->repository->fetchTopTeamsByWins($grouping, $region);

        if (count($topTeams) < 2) {
            return;
        }

        $first = $topTeams[0];
        $second = $topTeams[1];

        if ($first['wins'] === $second['wins']) {
            $month = SeasonPhaseHelper::getMonthForPhase($this->season->phase);
            $startDate = $this->season->beginningYear . "-{$month}-01";
            $endDate = $this->season->endingYear . '-05-30';
            $winnerId = $this->repository->getHeadToHeadWinner($first['teamid'], $second['teamid'], $startDate, $endDate);
            if ($winnerId === $second['teamid']) {
                [$first, $second] = [$second, $first];
            }
        }

        /** @var string $winningestTeamName */
        $winningestTeamName = $first['team_name'];
        /** @var int $winningestTeamWins */
        $winningestTeamWins = $first['wins'];

        $leastLosingestTeam = $this->repository->fetchLeastLosingTeam($winningestTeamName, $grouping, $region);

        if ($leastLosingestTeam === null) {
            return;
        }

        /** @var int $leastLosingestTeamLosses */
        $leastLosingestTeamLosses = $leastLosingestTeam['losses'];

        $magicNumber = 82 + 1 - $winningestTeamWins - $leastLosingestTeamLosses;

        if ($magicNumber > 0 && $first['wins'] === $second['wins']) {
            if ($this->repository->isRegionSeasonOver($grouping, $region)) {
                $magicNumber = 0;
            }
        }

        if ($magicNumber <= 0) {
            $clinchedColumn = $grouping !== null
                ? "clinched_{$grouping}"
                : 'clinched_league';
            $this->repository->updateClinchedFlag($winningestTeamName, $clinchedColumn);

            if ($grouping !== null && $region !== null) {
                echo "The {$winningestTeamName} have clinched the {$region} {$grouping}!";

                $awardName = self::REGION_AWARD_MAP[$region] ?? null;
                if ($awardName !== null && !$this->isOlympics) {
                    $this->repository->upsertTeamAward($this->season->endingYear, $winningestTeamName, $awardName);
                }
            } else {
                echo "The {$winningestTeamName} have clinched the best record in the league!";
            }
        }
    }

    private function checkIfPlayoffsClinched(string $conference): void {
        echo "<p>Checking if any teams have clinched playoff spots in the {$conference} Conference...<br>";

        $eightWinningestTeams = $this->repository->fetchWinningestTeams($conference);
        $sixLosingestTeams = $this->repository->fetchMostLosingTeams($conference);

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
                $this->repository->updateClinchedFlag($contendingTeamName, 'clinched_playoffs');
                echo "The {$contendingTeamName} have clinched a playoff spot!<br>";
            }
        }
    }
}
