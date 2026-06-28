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
        ob_start();
        $u->exposedComputeAndInsertStandings();
        return (string) ob_get_clean();
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
}

class TestableStandingsUpdater extends StandingsUpdater
{
    public function exposedComputeAndInsertStandings(): void
    {
        $this->computeAndInsertStandings();
    }
}
