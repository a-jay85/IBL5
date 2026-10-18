<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Season\Season;
use Standings\Contracts\StandingsRepositoryInterface;
use Updater\StandingsUpdater;

class StandingsUpdaterTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $captured = [];

    /**
     * @param array<int, array{conference:string,division:string,teamName:string}> $teamMap
     * @param list<array{visitor_teamid:int,visitor_score:int,home_teamid:int,home_score:int}> $games
     */
    private function buildUpdater(array $teamMap, array $games): TestableStandingsUpdater
    {
        $repo = self::createStub(StandingsRepositoryInterface::class);
        $repo->method('fetchTeamMapForSeason')->willReturn($teamMap);
        $repo->method('fetchPlayedGamesForSeason')->willReturn($games);
        $repo->method('upsertStandings')->willReturnCallback(
            function (array $p): void { $this->captured[(int) $p['teamid']] = $p; }
        );

        $season = self::createStub(Season::class);
        $season->phase = 'Regular Season';
        $season->beginningYear = 2024;
        $season->endingYear = 2025;

        return new TestableStandingsUpdater($repo, $season);
    }

    private function runCompute(TestableStandingsUpdater $u): string
    {
        $u->exposedComputeAndInsertStandings();
        return $u->takeOutputBuffer();
    }

    /** @return array{visitor_teamid:int,visitor_score:int,home_teamid:int,home_score:int} */
    private function game(int $visitor, int $vScore, int $home, int $hScore): array
    {
        return ['visitor_teamid' => $visitor, 'visitor_score' => $vScore, 'home_teamid' => $home, 'home_score' => $hScore];
    }

    /** @return array{conference:string,division:string,teamName:string} */
    private function team(string $conf, string $div, string $name = 'Team'): array
    {
        return ['conference' => $conf, 'division' => $div, 'teamName' => $name];
    }

    public function testTalliesLeagueWinsAndLossesPerTeam(): void
    {
        $u = $this->buildUpdater(
            [1 => $this->team('Eastern', 'Atlantic', 'Alpha'), 2 => $this->team('Eastern', 'Atlantic', 'Beta')],
            [$this->game(2, 90, 1, 100), $this->game(1, 110, 2, 95)]
        );
        $this->runCompute($u);

        $this->assertSame(2, $this->captured[1]['wins']);
        $this->assertSame(0, $this->captured[1]['losses']);
        $this->assertSame('2-0', $this->captured[1]['leagueRecord']);
        $this->assertSame(0, $this->captured[2]['wins']);
        $this->assertSame(2, $this->captured[2]['losses']);
    }

    public function testTracksHomeAndAwaySplitsSeparately(): void
    {
        $u = $this->buildUpdater(
            [1 => $this->team('Eastern', 'Atlantic', 'Alpha'), 2 => $this->team('Eastern', 'Atlantic', 'Beta')],
            [$this->game(2, 90, 1, 100), $this->game(1, 110, 2, 95)]
        );
        $this->runCompute($u);

        $this->assertSame(1, $this->captured[1]['homeWins']);
        $this->assertSame(1, $this->captured[1]['awayWins']);
        $this->assertSame('1-0', $this->captured[1]['homeRecord']);
        $this->assertSame('1-0', $this->captured[1]['awayRecord']);
    }

    public function testCountsConferenceAndDivisionGamesWhenSameRegion(): void
    {
        $u = $this->buildUpdater(
            [1 => $this->team('Eastern', 'Atlantic', 'Alpha'), 2 => $this->team('Eastern', 'Atlantic', 'Beta')],
            [$this->game(2, 90, 1, 100), $this->game(1, 110, 2, 95)]
        );
        $this->runCompute($u);

        $this->assertSame(2, $this->captured[1]['confWins']);
        $this->assertSame(2, $this->captured[1]['divWins']);
    }

    public function testConferenceGameInDifferentDivisionDoesNotCountAsDivision(): void
    {
        $u = $this->buildUpdater(
            [1 => $this->team('Eastern', 'Atlantic', 'Alpha'), 2 => $this->team('Eastern', 'Central', 'Beta')],
            [$this->game(1, 110, 2, 95)]
        );
        $this->runCompute($u);

        $this->assertSame(1, $this->captured[1]['confWins']);
        $this->assertSame(0, $this->captured[1]['divWins']);
    }

    public function testCrossConferenceGameCountsNeitherConferenceNorDivision(): void
    {
        $u = $this->buildUpdater(
            [1 => $this->team('Eastern', 'Atlantic', 'Alpha'), 3 => $this->team('Western', 'Pacific', 'Gamma')],
            [$this->game(1, 110, 3, 95)]
        );
        $this->runCompute($u);

        $this->assertSame(1, $this->captured[1]['wins']);
        $this->assertSame(0, $this->captured[1]['confWins']);
        $this->assertSame(0, $this->captured[1]['divWins']);
    }

    public function testGameReferencingUnknownTeamIsSkipped(): void
    {
        $u = $this->buildUpdater(
            [1 => $this->team('Eastern', 'Atlantic', 'Alpha'), 2 => $this->team('Eastern', 'Atlantic', 'Beta')],
            [$this->game(2, 90, 1, 100), $this->game(99, 85, 1, 95)]
        );
        $this->runCompute($u);

        $this->assertSame(1, $this->captured[1]['wins']);
        $this->assertArrayNotHasKey(99, $this->captured);
    }

    public function testEmptyTeamMapProducesNoUpsertsAndLogsError(): void
    {
        $u = $this->buildUpdater([], []);
        $output = $this->runCompute($u);

        $this->assertSame([], $this->captured);
        $this->assertStringContainsString('No league config', $output);
    }

    public function testTeamWithNoGamesGetsZeroPctAndZeroRecord(): void
    {
        $u = $this->buildUpdater(
            [1 => $this->team('Eastern', 'Atlantic', 'Alpha'), 2 => $this->team('Eastern', 'Atlantic', 'Beta')],
            []
        );
        $this->runCompute($u);

        $this->assertSame(0.0, $this->captured[1]['pct']);
        $this->assertSame(0, $this->captured[1]['wins']);
        $this->assertSame('0-0', $this->captured[1]['leagueRecord']);
    }

    /**
     * Six-team pin fixture. Neither conference leader (team 3 East, team 5 West) sits first
     * in map order, so a missing or reversed sort changes the leader.
     */
    private function buildPinFixture(): TestableStandingsUpdater
    {
        return $this->buildUpdater(
            [
                1 => $this->team('Eastern', 'Atlantic', 'Alpha'),
                2 => $this->team('Eastern', 'Atlantic', 'Beta'),
                3 => $this->team('Eastern', 'Central', 'Gamma'),
                4 => $this->team('Western', 'Pacific', 'Delta'),
                5 => $this->team('Western', 'Pacific', 'Epsilon'),
                6 => $this->team('Western', 'Midwest', 'Zeta'),
            ],
            [
                $this->game(3, 100, 1, 90),
                $this->game(1, 90, 3, 100),
                $this->game(3, 100, 2, 90),
                $this->game(2, 100, 1, 90),
                $this->game(1, 90, 2, 100),
                $this->game(4, 90, 5, 100),
            ]
        );
    }

    public function testConferenceGamesBehindMeasuredFromBestPctTeamInConference(): void
    {
        $this->runCompute($this->buildPinFixture());

        $this->assertSame(3.5, $this->captured[1]['confGb']);
        $this->assertSame(1.0, $this->captured[2]['confGb']);
        $this->assertSame(0.0, $this->captured[3]['confGb']);
        $this->assertSame(1.0, $this->captured[4]['confGb']);
        $this->assertSame(0.0, $this->captured[5]['confGb']);
        $this->assertSame(0.5, $this->captured[6]['confGb']);
    }

    /**
     * Guards the division grouping: feeding the conference grouping to the division
     * leader lookup leaves $divLeaderGB['Atlantic'] undefined and fails this test.
     */
    public function testDivisionGamesBehindMeasuredFromDivisionLeaderNotConferenceLeader(): void
    {
        $this->runCompute($this->buildPinFixture());

        $this->assertSame(2.5, $this->captured[1]['divGb']);
        $this->assertSame(0.0, $this->captured[2]['divGb']);
        $this->assertSame(0.0, $this->captured[3]['divGb']);
        $this->assertSame(1.0, $this->captured[4]['divGb']);
        $this->assertSame(0.0, $this->captured[5]['divGb']);
        $this->assertSame(0.0, $this->captured[6]['divGb']);
    }

    public function testGamesUnplayedSubtractsEveryHomeAndAwayResultFromSeasonLength(): void
    {
        $this->runCompute($this->buildPinFixture());

        $this->assertSame(78, $this->captured[1]['gamesUnplayed']);
        $this->assertSame(79, $this->captured[2]['gamesUnplayed']);
        $this->assertSame(79, $this->captured[3]['gamesUnplayed']);
        $this->assertSame(81, $this->captured[4]['gamesUnplayed']);
        $this->assertSame(81, $this->captured[5]['gamesUnplayed']);
        $this->assertSame(82, $this->captured[6]['gamesUnplayed']);
    }

    public function testPctRoundsToThreeDecimalPlaces(): void
    {
        $this->runCompute($this->buildPinFixture());

        $this->assertSame(0.0, $this->captured[1]['pct']);
        $this->assertSame(0.667, $this->captured[2]['pct']);
        $this->assertSame(1.0, $this->captured[3]['pct']);
        $this->assertSame(0.0, $this->captured[4]['pct']);
        $this->assertSame(1.0, $this->captured[5]['pct']);
    }

    public function testZeroGameTeamInSortedGroupGetsZeroPctAndTrailsLeader(): void
    {
        $this->runCompute($this->buildPinFixture());

        $this->assertSame(0.0, $this->captured[6]['pct']);
        $this->assertSame('0-0', $this->captured[6]['leagueRecord']);
        $this->assertSame(0.5, $this->captured[6]['confGb']);
        $this->assertSame(0.0, $this->captured[6]['divGb']);
        $this->assertSame(82, $this->captured[6]['gamesUnplayed']);
    }

    public function testStandingsRowPayloadKeysAndValuesForDivisionLeader(): void
    {
        $this->runCompute($this->buildPinFixture());

        $expected = [
            'teamid' => 2,
            'teamName' => 'Beta',
            'leagueRecord' => '2-1',
            'wins' => 2,
            'losses' => 1,
            'pct' => 0.667,
            'gamesUnplayed' => 79,
            'conference' => 'Eastern',
            'confGb' => 1.0,
            'confRecord' => '2-1',
            'division' => 'Atlantic',
            'divGb' => 0.0,
            'divRecord' => '2-0',
            'homeRecord' => '1-1',
            'awayRecord' => '1-0',
            'confWins' => 2,
            'confLosses' => 1,
            'divWins' => 2,
            'divLosses' => 0,
            'homeWins' => 1,
            'homeLosses' => 1,
            'awayWins' => 1,
            'awayLosses' => 0,
        ];

        $this->assertSame(array_keys($expected), array_keys($this->captured[2]));
        $this->assertSame($expected, $this->captured[2]);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_keys($this->captured));
    }
}

class TestableStandingsUpdater extends StandingsUpdater
{
    public function exposedComputeAndInsertStandings(): void
    {
        $this->computeAndInsertStandings();
    }
}
