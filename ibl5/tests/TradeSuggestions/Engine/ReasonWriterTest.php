<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\ProfileDelta;
use TradeSuggestions\Engine\ReasonWriter;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\SideView;

final class ReasonWriterTest extends TestCase
{
    private const array WIN_NOW_WEIGHTS = [0.55, 0.25, 0.12, 0.08];
    private const array REBUILD_WEIGHTS = [0.1, 0.2, 0.3, 0.4];

    private static ?DealView $quiet = null;

    public function testQuotesStarterRankBeforeAndAfter(): void
    {
        $side = self::side(['starter_slots' => 3.0], delta: self::delta(starters: ['PG' => [19, 12]]));

        self::assertStringContainsString('PG starter rank 19 to 12', self::reason($side));
    }

    public function testDimensionClauseQuotesBiggestImprovementNotBiggestMove(): void
    {
        // Rebounds falls 17 places, scoring rises 5: the reason must name scoring.
        $side = self::side(['profile_dimensions' => 2.0], delta: self::delta(dimensions: ['reb' => [11, 28], 'pts' => [15, 10]]));
        $reason = self::reason($side);

        self::assertStringContainsString('scoring rank 15 to 10', $reason);
        self::assertStringNotContainsString('rebounds', $reason);
    }

    public function testDimensionClauseSkippedWhenNothingImproved(): void
    {
        $side = self::side(['profile_dimensions' => 2.0], delta: self::delta(dimensions: ['reb' => [11, 28]]));

        self::assertNull(ReasonWriter::forSide(self::quiet(), $side, []));
    }

    public function testGainOnlyInMiddleSeasonStillGetsAReason(): void
    {
        // Win-now: heaviest season is this one. The only gain is in 2 seasons.
        $side = self::side([], weights: self::WIN_NOW_WEIGHTS, byOffset: [2 => ['balance' => 1.5]]);
        $reason = self::reason($side);

        self::assertStringContainsString('offense and defense rank gap', $reason);
        self::assertStringEndsWith('in 2 seasons', $reason);
    }

    public function testFallbackNotUsedWhenHeaviestSeasonHasAGain(): void
    {
        $side = self::side(['balance' => 1.0], weights: self::WIN_NOW_WEIGHTS, byOffset: [2 => ['roster_spots' => 5.0]]);
        $reason = self::reason($side);

        self::assertStringNotContainsString('roster spot', $reason);
        self::assertStringNotContainsString('season', explode(ReasonWriter::LEAD_SEPARATOR, $reason, 2)[1]);
    }

    public function testQuotesPayrollFigures(): void
    {
        $side = self::side(['payroll' => 2.0], delta: self::delta(payroll: [6900, 6500]));
        $reason = self::reason($side);

        self::assertStringContainsString('6900', $reason);
        self::assertStringContainsString('6500', $reason);
    }

    public function testQuotesPickYearRoundAndSlot(): void
    {
        $side = self::side(['picks' => 2.0], receivedPicks: [new PickAsset(99, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2028, 2, 2)]);

        self::assertStringContainsString('2028 round 2', self::reason($side));
    }

    public function testTopTwoComponentsOnly(): void
    {
        $side = self::side(
            ['starter_slots' => 4.0, 'payroll' => 3.0, 'roster_spots' => 2.0, 'cap_room' => 1.0],
            delta: self::delta(starters: ['PG' => [19, 12]], payroll: [6900, 6500]),
        );
        $body = explode(ReasonWriter::LEAD_SEPARATOR, self::reason($side), 2)[1];

        self::assertCount(2, explode(ReasonWriter::CLAUSE_SEPARATOR, $body));
    }

    public function testNoPositiveComponentReturnsNull(): void
    {
        $side = self::side(['starter_slots' => 0.0, 'payroll' => -1.0]);

        self::assertNull(ReasonWriter::forSide(self::quiet(), $side, []));
    }

    public function testEachSideGetsItsOwnNumbers(): void
    {
        $quiet = self::quiet();
        $user = self::side(['starter_slots' => 3.0], delta: self::delta(starters: ['PG' => [19, 12]]));
        $partner = self::side(['starter_slots' => 3.0], base: $quiet->partner, delta: self::delta(starters: ['C' => [25, 4]]));
        $deal = new DealView($user, $partner, $quiet->candidate, $quiet->shapeId);

        $userReason = (string) ReasonWriter::forSide($deal, $user, []);
        $partnerReason = (string) ReasonWriter::forSide($deal, $partner, []);

        self::assertStringContainsString('PG starter rank 19 to 12', $userReason);
        self::assertStringContainsString('C starter rank 25 to 4', $partnerReason);
        self::assertStringNotContainsString('19 to 12', $partnerReason);
    }

    public function testInjuredReceiverIsNamedInReason(): void
    {
        $base = self::quiet()->user;
        $pid = $base->received[0]->pid;
        $side = self::side(['roster_spots' => 1.0], injury: [$pid => InjuryStatus::OutForYear]);

        self::assertStringContainsString('out for the year', self::reason($side));
    }

    public function testLengthCapCutsAtClauseBoundary(): void
    {
        $long = str_repeat('Longname ', 12);
        $side = self::side(
            ['starter_slots' => 3.0, 'picks' => 2.0],
            delta: self::delta(starters: ['PG' => [19, 12]]),
            receivedPicks: [new PickAsset(99, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2028, 2, 2)],
        );
        $reason = (string) ReasonWriter::forSide(self::quiet(), $side, [VetoDealFactory::PARTNER_TID => $long]);

        self::assertLessThanOrEqual(ReasonWriter::MAX_LENGTH, mb_strlen($reason));
        self::assertStringEndsNotWith(ReasonWriter::CLAUSE_SEPARATOR, $reason);
        self::assertStringEndsWith('PG starter rank 19 to 12', $reason);
    }

    public function testNoBannedWordsInTemplates(): void
    {
        // phpunit-hygiene-allow: the property is the template text itself, which no seam exposes as a list
        $source = (string) file_get_contents(__DIR__ . '/../../../classes/TradeSuggestions/Engine/ReasonWriter.php');

        self::assertDoesNotMatchRegularExpression('/\bovr\b|\boverall\b|\bquality\b/i', $source);
    }

    public function testHeaviestSeasonFollowsMode(): void
    {
        // A gain in 3 seasons is the rebuilder's main season, so it needs no label.
        // For a win-now team it is a later season, so the clause says when.
        $components = [3 => ['balance' => 2.0]];
        $rebuild = self::side([], mode: TeamMode::Rebuild, weights: self::REBUILD_WEIGHTS, byOffset: $components);
        $winNow = self::side([], mode: TeamMode::WinNow, weights: self::WIN_NOW_WEIGHTS, byOffset: $components);

        $rebuildReason = self::reason($rebuild);
        self::assertStringContainsString('rank gap', $rebuildReason);
        self::assertStringNotContainsString('season', explode(ReasonWriter::LEAD_SEPARATOR, $rebuildReason, 2)[1]);
        self::assertStringEndsWith('in 3 seasons', self::reason($winNow));
    }

    public function testQuotesBestBenchRankFromTeamState(): void
    {
        $base = self::quiet()->user;
        $side = self::side(
            ['bench_slots' => 2.0],
            before: [0 => self::withBench($base->before[0], 22)],
            after: [0 => self::withBench($base->after[0], 9)],
        );

        self::assertStringContainsString('SF best bench rank 22 to 9', self::reason($side));
    }

    public function testQuotesContractSurplusForContractsComponent(): void
    {
        $pid = self::quiet()->user->received[0]->pid;
        $side = self::side(['contracts' => 1.5], surplus: [$pid => [0 => 300, 1 => 0, 2 => 0, 3 => 0]]);

        self::assertStringContainsString('300 under fair salary', self::reason($side));
    }

    public function testQuotesShedContractForDumpSide(): void
    {
        $base = self::quiet()->user;
        $surplus = [$base->received[0]->pid => [0 => 0, 1 => 0, 2 => 0, 3 => 0], $base->sent[0]->pid => [0 => -450, 1 => 0, 2 => 0, 3 => 0]];
        $side = self::side(['contracts' => 1.5], surplus: $surplus);
        $reason = self::reason($side);

        self::assertStringContainsString('sheds ' . $base->sent[0]->name, $reason);
        self::assertStringContainsString('450 over fair salary', $reason);
    }

    public function testEveryComponentKeyHasAClause(): void
    {
        $base = self::quiet()->user;
        $surplus = [$base->received[0]->pid => [0 => 120, 1 => 0, 2 => 0, 3 => 0]];
        $delta = self::delta(starters: ['PG' => [19, 12]], payroll: [6900, 6500], dimensions: ['pts' => [20, 10]]);
        $picks = [new PickAsset(99, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2028, 2, 2)];

        foreach (SideGain::KEYS as $key) {
            $side = self::side(
                [$key => 1.0],
                delta: $delta,
                receivedPicks: $picks,
                surplus: $surplus,
                before: [0 => self::withBench($base->before[0], 22)],
                after: [0 => self::withBench($base->after[0], 9)],
            );
            self::assertNotNull(ReasonWriter::forSide(self::quiet(), $side, []), $key);
        }
    }

    private static function reason(SideView $side): string
    {
        $reason = ReasonWriter::forSide(self::quiet(), $side, []);
        self::assertNotNull($reason);

        return $reason;
    }

    private static function quiet(): DealView
    {
        return self::$quiet ??= VetoDealFactory::quiet()['deal'];
    }

    /**
     * A copy of the quiet deal's side with scripted gain and any overrides.
     *
     * @param array<string, float> $offsetZero components at offset 0
     * @param list<float> $weights
     * @param array<int, array<string, float>> $byOffset extra components by offset
     * @param list<PickAsset>|null $receivedPicks
     * @param array<int, array<int, int>>|null $surplus
     * @param array<int, InjuryStatus> $injury
     * @param array<int, TeamState>|null $before
     * @param array<int, TeamState>|null $after
     */
    private static function side(
        array $offsetZero,
        ?SideView $base = null,
        ?ProfileDelta $delta = null,
        TeamMode $mode = TeamMode::Middle,
        array $weights = [0.4, 0.3, 0.2, 0.1],
        array $byOffset = [],
        ?array $receivedPicks = null,
        ?array $surplus = null,
        array $injury = [],
        ?array $before = null,
        ?array $after = null,
    ): SideView {
        $base ??= self::quiet()->user;
        $components = $byOffset + [0 => $offsetZero];
        foreach ([0, 1, 2, 3] as $o) {
            $components[$o] ??= [];
        }
        ksort($components);

        return new SideView(
            $base->tid,
            $mode,
            $base->sent,
            $base->received,
            $base->sentPicks,
            $receivedPicks ?? $base->receivedPicks,
            array_replace($base->before, $before ?? []),
            array_replace($base->after, $after ?? []),
            new SideGain($base->tid, $mode, $components, $weights, 1.0),
            $base->situation,
            $surplus ?? $base->surplusByPid,
            $delta === null ? $base->delta : array_replace($base->delta, [0 => $delta]),
            $receivedPicks === null ? $base->pickWorthById : array_fill_keys(array_map(static fn (PickAsset $p): int => $p->pickId, $receivedPicks), 1.0),
            $base->availabilityByPid,
            $injury,
        );
    }

    /**
     * @param array<string, array{?int, ?int}> $starters slot => [before, after]
     * @param array{int, int} $payroll
     * @param array<string, array{int, int}> $dimensions
     */
    private static function delta(array $starters = [], array $payroll = [7000, 7000], array $dimensions = []): ProfileDelta
    {
        $ranks = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $pair = $starters[$slot] ?? [10, 10];
            $ranks[$slot] = ['before' => $pair[0], 'after' => $pair[1]];
        }
        $dims = [];
        foreach ($dimensions as $key => [$b, $a]) {
            $dims[$key] = ['before' => $b, 'after' => $a];
        }

        return new ProfileDelta(VetoDealFactory::USER_TID, 0, $dims, $ranks, ['before' => 10, 'after' => 10], ['before' => $payroll[0], 'after' => $payroll[1]]);
    }

    /**
     * The state with SF's best bench rank set and every other slot's unchanged.
     */
    private static function withBench(TeamState $state, int $sfRank): TeamState
    {
        $bench = $state->bestBenchRank;
        foreach (SlotMapper::SLOTS as $slot) {
            $bench[$slot] = $slot === 'SF' ? $sfRank : 10;
        }

        return new TeamState(
            $state->tid,
            $state->offset,
            $state->lineup,
            $state->starterRank,
            $bench,
            $state->profile,
            $state->positionStrength,
            $state->payroll,
            $state->payrollRank,
            $state->rosterCount,
        );
    }
}
