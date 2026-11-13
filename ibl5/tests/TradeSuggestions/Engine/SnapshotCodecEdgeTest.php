<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\SnapshotCodec;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectedSeason;
use TradeSuggestions\Projection\Rating;

/**
 * Edge cases of the codec's assemble, lineup and decode helpers that the
 * round-trip tests leave unpinned. Every expected value is a literal.
 */
final class SnapshotCodecEdgeTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
    {
        return SnapshotCodec::encode(LeagueSnapshotBuilder::build(SnapshotFactory::inputs(2, 8)));
    }

    /**
     * @param array<mixed> $arr
     * @return array<mixed>
     */
    private static function sub(array $arr, string|int $key): array
    {
        $value = $arr[$key] ?? null;
        self::assertIsArray($value);

        return $value;
    }

    private static function season(int $offset, bool $onRoster): ProjectedSeason
    {
        return new ProjectedSeason(
            offset: $offset,
            age: 25,
            salary: $onRoster ? 100 : 0,
            onRoster: $onRoster,
            ratings: ProjectionFactory::ratingSet(),
            per36: [],
        );
    }

    public function testDecodeRejectsMissingTalliesWithTheOffsetInTheMessage(): void
    {
        $payload = self::payload();
        $tallies = self::sub($payload, 'tallies');
        unset($tallies[2]);
        $payload['tallies'] = $tallies;

        try {
            SnapshotCodec::decode($payload);
            self::fail('decode accepted a payload with no tallies at offset 2');
        } catch (\UnexpectedValueException $e) {
            self::assertSame('No tallies at offset 2', $e->getMessage());
        }
    }

    public function testDecodeKeepsEveryStoredRatingOfAPlayerAndOfAProjectedSeason(): void
    {
        $payload = self::payload();
        $players = self::sub($payload, 'players');
        $pid = array_key_first($players);
        self::assertIsInt($pid);

        $stored = [];
        $expected = [];
        foreach (Rating::ALL as $i => $key) {
            $stored[$key] = $i + 10;
            $expected[$key] = (float) ($i + 10);
        }
        $row = self::sub($players, $pid);
        $row['ratings'] = $stored;
        $players[$pid] = $row;
        $payload['players'] = $players;

        $projections = self::sub($payload, 'projections');
        $seasons = self::sub($projections, $pid);
        $season = self::sub($seasons, 0);
        $season['ratings'] = $stored;
        $seasons[0] = $season;
        $projections[$pid] = $seasons;
        $payload['projections'] = $projections;

        $decoded = SnapshotCodec::decode($payload);

        self::assertGreaterThan(1, count(Rating::ALL));
        self::assertSame($expected, $decoded->players[$pid]->ratings->toArray());
        self::assertSame($expected, $decoded->projections[$pid][0]->ratings->toArray());
    }

    public function testRatingsAtKeepsOnlyPlayersOnTheRosterAtThatOffset(): void
    {
        $onRoster = self::season(1, true);
        $offRoster = self::season(1, false);

        $out = SnapshotCodec::ratingsAt([
            1 => [1 => $onRoster],
            2 => [1 => $offRoster],
            3 => [0 => self::season(0, true)],
        ], 1);

        self::assertSame([1 => $onRoster->ratings], $out);
    }

    public function testLineupsForIgnoresTeamsMissingFromTeamIds(): void
    {
        $a = ProjectionFactory::player(pid: 10, tid: 1);
        $b = ProjectionFactory::player(pid: 12, tid: 2);

        $lineups = SnapshotCodec::lineupsFor(
            [1],
            [10 => $a, 12 => $b],
            [10 => 1, 12 => 2],
            [10 => [0 => self::season(0, true)], 12 => [0 => self::season(0, true)]],
        );

        self::assertSame([1], array_keys($lineups[0]));
        self::assertSame([10], array_keys($lineups[0][1]->players));
    }

    public function testPayrollIsZeroWithoutCashAndWithoutRosterSalaries(): void
    {
        $a = ProjectionFactory::player(pid: 11, tid: 1, salaryByYear: [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]);
        $b = ProjectionFactory::player(pid: 12, tid: 1, salaryByYear: [1 => 200, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]);
        $base = SnapshotFactory::inputs(2, 5, [1 => [$a, $b], 2 => []]);
        $in = new \TradeSuggestions\Engine\LeagueSnapshotInputs(
            seasonYear: $base->seasonYear,
            advancesContractYears: $base->advancesContractYears,
            regularSeason: $base->regularSeason,
            playersByTid: $base->playersByTid,
            scale: $base->scale,
            standings: $base->standings,
            streaks: $base->streaks,
            teamFactors: $base->teamFactors,
            cashByTeamAndOffset: [],
            pickRows: $base->pickRows,
            draftOrder: $base->draftOrder,
            modes: $base->modes,
            teamNames: $base->teamNames,
        );

        $s = LeagueSnapshotBuilder::build($in);

        self::assertSame([1 => 300, 2 => 0], $s->payrollByOffset[0]);
        self::assertSame([1 => 0, 2 => 0], $s->payrollByOffset[1]);
        self::assertSame([1 => 0, 2 => 0], $s->payrollByOffset[3]);
    }

    public function testRosterPlayerWithoutASalaryEntryCountsAsZeroPayroll(): void
    {
        $payload = self::payload();
        $players = self::sub($payload, 'players');
        foreach (array_keys($players) as $pid) {
            $row = self::sub($players, $pid);
            $row['pid'] = 5000 + (int) $pid;
            $players[$pid] = $row;
        }
        $payload['players'] = $players;

        $decoded = SnapshotCodec::decode($payload);

        foreach ([0, 1, 2, 3] as $offset) {
            self::assertSame([1 => 0, 2 => 0], $decoded->payrollByOffset[$offset], "offset {$offset}");
        }
    }

    public function testOffRosterSeasonSalaryNeverFeedsTheFairSalaryTable(): void
    {
        $pg = ProjectionFactory::player(pid: 11, tid: 1, pos: 'PG', salaryByYear: [1 => 100, 2 => 100, 3 => 100, 4 => 0, 5 => 0, 6 => 0], dcMinutes: 32);
        $c = ProjectionFactory::player(pid: 12, tid: 1, pos: 'C', salaryByYear: [1 => 500, 2 => 500, 3 => 500, 4 => 0, 5 => 0, 6 => 0], dcMinutes: 32);
        $payload = SnapshotCodec::encode(LeagueSnapshotBuilder::build(SnapshotFactory::inputs(2, 5, [1 => [$pg, $c], 2 => []])));
        $projections = self::sub($payload, 'projections');
        $seasons = self::sub($projections, 12);
        $season = self::sub($seasons, 0);
        $season['onRoster'] = false;
        $seasons[0] = $season;
        $projections[12] = $seasons;
        $payload['projections'] = $projections;

        $decoded = SnapshotCodec::decode($payload);

        self::assertSame([1 => 100], $decoded->fairSalary[0]->tiers('PG'));
        self::assertSame([], $decoded->fairSalary[0]->tiers('C'));
    }

    public function testOpenRosterSpotsCountTheOffsetZeroLineup(): void
    {
        $long = ProjectionFactory::player(pid: 11, tid: 1, salaryByYear: [1 => 100, 2 => 100, 3 => 100, 4 => 0, 5 => 0, 6 => 0]);
        $short1 = ProjectionFactory::player(pid: 12, tid: 1, salaryByYear: [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]);
        $short2 = ProjectionFactory::player(pid: 13, tid: 1, salaryByYear: [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]);

        $s = LeagueSnapshotBuilder::build(SnapshotFactory::inputs(2, 5, [1 => [$long, $short1, $short2]]));

        self::assertSame(12, $s->situations[1]->openRosterSpots);
    }

    public function testUnassignedPlayerFallsBackToTeamZeroSituation(): void
    {
        $payload = self::payload();
        $players = self::sub($payload, 'players');
        $pid = array_key_first($players);
        self::assertIsInt($pid);
        $row = self::sub($players, $pid);
        $row['injuredDays'] = 5;
        $players[$pid] = $row;
        $payload['players'] = $players;

        $teamOfPid = self::sub($payload, 'teamOfPid');
        unset($teamOfPid[$pid]);
        $payload['teamOfPid'] = $teamOfPid;

        $standings = self::sub($payload, 'standings');
        $team1 = self::sub($standings, 1);
        $team0 = $team1;
        $team0['games_unplayed'] = 22;
        $team1['games_unplayed'] = 0;
        $standings[0] = $team0;
        $standings[1] = $team1;
        $payload['standings'] = $standings;

        $payload['teamIds'] = [0, 1, 2];

        $decoded = SnapshotCodec::decode($payload);

        self::assertSame(InjuryStatus::BackSoon, $decoded->injury[$pid]);
    }
}
