<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\ProfileDelta;
use TradeSuggestions\Engine\ReasonWriter;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectionPlayer;
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

    public function testQuotesRookieScaleReceiverWhenNoSalaryReasonApplies(): void
    {
        $rookie = ProjectionFactory::player(pid: 903, tid: VetoDealFactory::PARTNER_TID, name: 'Rae', exp: 1, draftRound: 1);
        $side = self::side(['contracts' => 1.5], surplus: [], received: [self::player(901, 'Ann'), $rookie]);

        self::assertSame('Rae on a rookie-scale contract', self::body($side));
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

    public function testFullReasonIsLeadThenClauses(): void
    {
        $side = self::side(['starter_slots' => 3.0], delta: self::delta(starters: ['PG' => [19, 12]]), received: [self::player(901, 'Ann')]);

        self::assertSame('Ann, 26, 1 season left: PG starter rank 19 to 12', self::reason($side));
    }

    /**
     * @param array<string, array{?int, ?int}> $starters
     */
    #[DataProvider('starterProvider')]
    public function testStarterClauseQuotesTheBiggestMove(array $starters, string $expected): void
    {
        $side = self::side(['starter_slots' => 3.0], delta: self::delta(starters: $starters));

        self::assertSame($expected, self::body($side));
    }

    /**
     * @return array<string, array{array<string, array{?int, ?int}>, string}>
     */
    public static function starterProvider(): array
    {
        return [
            'a tie keeps the first slot' => [['PG' => [19, 12], 'C' => [11, 4]], 'PG starter rank 19 to 12'],
            'an empty after slot is skipped' => [['PG' => [5, null], 'C' => [11, 4]], 'C starter rank 11 to 4'],
            'the move counts from before' => [['PG' => [12, 10], 'SG' => [8, 1]], 'SG starter rank 8 to 1'],
            'an empty before slot' => [['PG' => [null, 5]], 'no PG starter to rank 5'],
        ];
    }

    public function testStarterClauseSkippedWhenNoSlotMoved(): void
    {
        $side = self::side(['starter_slots' => 3.0], delta: self::delta());

        self::assertNull(ReasonWriter::forSide(self::quiet(), $side, []));
    }

    /**
     * @param array<string, ?int> $before
     * @param array<string, ?int> $after
     */
    #[DataProvider('benchProvider')]
    public function testBenchClauseQuotesTheBiggestMove(array $before, array $after, string $expected): void
    {
        $base = self::quiet()->user;
        $side = self::side(
            ['bench_slots' => 2.0],
            before: [0 => self::withBenchRanks($base->before[0], $before)],
            after: [0 => self::withBenchRanks($base->after[0], $after)],
        );

        self::assertSame($expected, self::body($side));
    }

    /**
     * @return array<string, array{array<string, ?int>, array<string, ?int>, string}>
     */
    public static function benchProvider(): array
    {
        return [
            'an empty after slot is skipped' => [['PG' => 10, 'SF' => 22], ['PG' => null, 'SF' => 9], 'SF best bench rank 22 to 9'],
            'the move counts from before' => [['PG' => 12, 'SF' => 22], ['PG' => 8, 'SF' => 9], 'SF best bench rank 22 to 9'],
            'the move subtracts after' => [['PG' => 20, 'SF' => 15], ['PG' => 18, 'SF' => 5], 'SF best bench rank 15 to 5'],
            'a tie keeps the first slot' => [['PG' => 12, 'SF' => 12], ['PG' => 10, 'SF' => 10], 'PG best bench rank 12 to 10'],
            'an empty before slot' => [['PG' => null], ['PG' => 9], 'no PG bench to best bench rank 9'],
        ];
    }

    public function testBenchClauseSkippedWhenNoSlotMoved(): void
    {
        $base = self::quiet()->user;
        $side = self::side(
            ['bench_slots' => 2.0],
            before: [0 => self::withBenchRanks($base->before[0], [])],
            after: [0 => self::withBenchRanks($base->after[0], [])],
        );

        self::assertNull(ReasonWriter::forSide(self::quiet(), $side, []));
    }

    #[DataProvider('stateClauseKeyProvider')]
    public function testStateClauseSkippedWhenTheAfterStateIsMissing(string $key): void
    {
        $side = self::side([$key => 2.0], dropAfter: [0]);

        self::assertNull(ReasonWriter::forSide(self::quiet(), $side, []));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function stateClauseKeyProvider(): array
    {
        return ['bench' => ['bench_slots'], 'balance' => ['balance']];
    }

    /**
     * @param array<string, array{int, int}> $dimensions
     */
    #[DataProvider('dimensionProvider')]
    public function testDimensionClauseQuotesTheBiggestGain(array $dimensions, ?string $expected): void
    {
        $side = self::side(['profile_dimensions' => 2.0], delta: self::delta(dimensions: $dimensions));
        $reason = ReasonWriter::forSide(self::quiet(), $side, []);

        self::assertSame($expected, $reason === null ? null : explode(ReasonWriter::LEAD_SEPARATOR, $reason, 2)[1]);
    }

    /**
     * @return array<string, array{array<string, array{int, int}>, ?string}>
     */
    public static function dimensionProvider(): array
    {
        return [
            'a gain under one place still counts' => [['pts' => [15, 14]], 'scoring rank 15 to 14'],
            'an unchanged rank is no gain' => [['reb' => [11, 11]], null],
            'a tie keeps the first dimension' => [['pts' => [15, 10], 'reb' => [15, 10]], 'scoring rank 15 to 10'],
        ];
    }

    public function testBalanceClauseQuotesBothGaps(): void
    {
        $base = self::quiet()->user;
        $expected = 'offense and defense rank gap ' . abs($base->before[0]->profile->balance()) . ' to ' . abs($base->after[0]->profile->balance());

        self::assertSame($expected, self::body(self::side(['balance' => 1.0])));
    }

    #[DataProvider('payrollSeasonProvider')]
    public function testPayrollClauseNamesItsSeason(int $offset, string $expected): void
    {
        $components = [$offset => ['payroll' => 2.0]];
        $side = self::side([], byOffset: $components, deltas: [$offset => self::delta(payroll: [6900, 6500])]);

        self::assertSame($expected, self::body($side));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function payrollSeasonProvider(): array
    {
        return [
            'this season' => [0, 'payroll 6900 to 6500 this season'],
            'next season' => [1, 'payroll 6900 to 6500 next season'],
            'a later season' => [2, 'payroll 6900 to 6500 in 2 seasons'],
        ];
    }

    public function testCapRoomClauseQuotesRoomUnderTheHardCap(): void
    {
        $side = self::side(['cap_room' => 2.0], delta: self::delta(payroll: [6900, 6500]));

        self::assertSame('500 under the hard cap this season', self::body($side));
    }

    /**
     * @param list<int> $order pick ids in received order
     */
    #[DataProvider('pickProvider')]
    public function testPickClauseQuotesTheMostValuablePick(array $order, string $expected): void
    {
        $picks = [
            1 => new PickAsset(1, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2027, 1, 1),
            2 => new PickAsset(2, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2028, 1, 2),
            3 => new PickAsset(3, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2029, 1, 3),
            4 => new PickAsset(4, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2030, 1, 4),
        ];
        $received = array_map(static fn (int $id): PickAsset => $picks[$id], $order);
        $side = self::side(['picks' => 2.0], receivedPicks: $received, pickWorth: [1 => 0.5, 2 => 2.0, 3 => 2.0]);

        self::assertSame($expected, self::body($side));
    }

    /**
     * @return array<string, array{list<int>, string}>
     */
    public static function pickProvider(): array
    {
        return [
            'a later, better pick wins' => [[1, 2], '2028 round 1 pick'],
            'an earlier, better pick stays' => [[2, 1], '2028 round 1 pick'],
            'a tie keeps the first pick' => [[2, 3], '2028 round 1 pick'],
            'a later pick with no worth loses' => [[1, 4], '2027 round 1 pick'],
            'an earlier pick with no worth loses' => [[4, 1], '2027 round 1 pick'],
        ];
    }

    public function testPickClauseNamesTheOriginalTeam(): void
    {
        $pick = new PickAsset(1, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2027, 2, 1);
        $side = self::side(['picks' => 2.0], receivedPicks: [$pick]);

        self::assertSame('2027 round 2 pick (Heat)', self::body($side, [VetoDealFactory::PARTNER_TID => 'Heat']));
    }

    /**
     * @param array<int, int> $surplus pid => surplus this season
     */
    #[DataProvider('contractProvider')]
    public function testContractClauseQuotesTheBiggestSurplus(array $surplus, ?string $expected): void
    {
        $side = self::side(
            ['contracts' => 1.5],
            surplus: array_map(static fn (int $s): array => [0 => $s], $surplus),
            received: [self::player(901, 'Ann'), self::player(902, 'Bo')],
            sent: [self::player(911, 'Cy'), self::player(912, 'Di')],
        );
        $reason = ReasonWriter::forSide(self::quiet(), $side, []);

        self::assertSame($expected, $reason === null ? null : explode(ReasonWriter::LEAD_SEPARATOR, $reason, 2)[1]);
    }

    /**
     * @return array<string, array{array<int, int>, ?string}>
     */
    public static function contractProvider(): array
    {
        return [
            'the first received is cheaper' => [[901 => 300, 902 => 100], 'Ann 300 under fair salary'],
            'the second received is cheaper' => [[901 => 100, 902 => 300], 'Bo 300 under fair salary'],
            'a received tie keeps the first' => [[901 => 200, 902 => 200], 'Ann 200 under fair salary'],
            'the first sent is dearer' => [[911 => -450, 912 => -100], 'sheds Cy, 450 over fair salary'],
            'the second sent is dearer' => [[911 => -100, 912 => -450], 'sheds Di, 450 over fair salary'],
            'a sent tie keeps the first' => [[911 => -200, 912 => -200], 'sheds Cy, 200 over fair salary'],
            'fair contracts give no clause' => [[901 => 0, 902 => 0, 911 => 0, 912 => 0], null],
            'players with no entry give no clause' => [[], null],
        ];
    }

    /**
     * @param array<int, int> $salaryByYear
     * @param array<int, InjuryStatus> $injury
     */
    #[DataProvider('leadProvider')]
    public function testLeadNamesTheFirstReceivedPlayer(array $salaryByYear, array $injury, string $expected): void
    {
        $side = self::side(['roster_spots' => 1.0], received: [self::player(901, 'Ann', $salaryByYear), self::player(902, 'Bo')], injury: $injury);

        self::assertSame($expected . ReasonWriter::LEAD_SEPARATOR . 'opens a roster spot', self::reason($side));
    }

    /**
     * @return array<string, array{array<int, int>, array<int, InjuryStatus>, string}>
     */
    public static function leadProvider(): array
    {
        return [
            'one season left' => [[1 => 500], [], 'Ann, 26, 1 season left'],
            'two seasons left' => [[1 => 500, 2 => 500], [], 'Ann, 26, 2 seasons left'],
            'six seasons left' => [array_fill(1, 6, 500), [], 'Ann, 26, 6 seasons left'],
            'injured' => [[1 => 500, 2 => 500, 3 => 500], [901 => InjuryStatus::OutForYear], 'Ann (out for the year), 26, 3 seasons left'],
        ];
    }

    public function testNoReceivedPlayerMeansNoLead(): void
    {
        self::assertSame('opens a roster spot', self::reason(self::side(['roster_spots' => 1.0], received: [])));
    }

    public function testComponentsAreOrderedByValue(): void
    {
        $side = self::side(['starter_slots' => 1.0, 'roster_spots' => 5.0], delta: self::delta(starters: ['PG' => [19, 12]]));

        self::assertSame('opens a roster spot; PG starter rank 19 to 12', self::body($side));
    }

    public function testThisSeasonIsReadWhenAnotherSeasonIsHeaviest(): void
    {
        $base = self::quiet()->user;
        $side = self::side(['balance' => 1.0], mode: TeamMode::Rebuild, weights: self::REBUILD_WEIGHTS);
        $expected = 'offense and defense rank gap ' . abs($base->before[0]->profile->balance()) . ' to ' . abs($base->after[0]->profile->balance());

        self::assertSame($expected, self::body($side));
    }

    /**
     * @param list<float> $weights
     * @param array<int, array<string, float>> $byOffset
     */
    #[DataProvider('fallbackProvider')]
    public function testFallbackReadsTheHeaviestOtherSeason(array $weights, array $byOffset, int $offset, string $season): void
    {
        $base = self::quiet()->user;
        $side = self::side([], weights: $weights, byOffset: $byOffset);
        $expected = 'offense and defense rank gap ' . abs($base->before[$offset]->profile->balance()) . ' to ' . abs($base->after[$offset]->profile->balance()) . ' ' . $season;

        self::assertSame($expected, self::body($side));
    }

    /**
     * @return array<string, array{list<float>, array<int, array<string, float>>, int, string}>
     */
    public static function fallbackProvider(): array
    {
        $tie = [1 => ['balance' => 1.0], 3 => ['balance' => 1.0]];

        return [
            'equal gains go to the heavier season' => [[0.4, 0.1, 0.2, 0.3], $tie, 3, 'in 3 seasons'],
            'equal weights go to the earlier season' => [[0.4, 0.2, 0.2, 0.2], $tie, 1, 'next season'],
            'the bigger gain wins over weight' => [[0.4, 0.3, 0.2, 0.1], [1 => ['balance' => 1.0], 3 => ['balance' => 2.0]], 3, 'in 3 seasons'],
        ];
    }

    public function testSingleLongClauseDropsTheLead(): void
    {
        $team = str_repeat('x', 140);
        $pick = new PickAsset(1, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2027, 1, 1);
        $side = self::side(['picks' => 2.0], receivedPicks: [$pick], received: [self::player(901, 'Ann')]);
        $reason = ReasonWriter::forSide(self::quiet(), $side, [VetoDealFactory::PARTNER_TID => $team]);

        self::assertSame('2027 round 1 pick (' . $team . ')', $reason);
    }

    public function testTextOfExactlyMaxLengthKeepsTheLead(): void
    {
        $lead = 'Ann, 26, 1 season left';
        $fixed = mb_strlen($lead . ReasonWriter::LEAD_SEPARATOR . '2027 round 1 pick ()');
        $team = str_repeat('é', ReasonWriter::MAX_LENGTH - $fixed);
        $pick = new PickAsset(1, VetoDealFactory::USER_TID, VetoDealFactory::PARTNER_TID, 2027, 1, 1);
        $side = self::side(['picks' => 2.0], receivedPicks: [$pick], received: [self::player(901, 'Ann')]);
        $reason = ReasonWriter::forSide(self::quiet(), $side, [VetoDealFactory::PARTNER_TID => $team]);

        self::assertSame($lead . ReasonWriter::LEAD_SEPARATOR . '2027 round 1 pick (' . $team . ')', $reason);
    }

    private static function reason(SideView $side): string
    {
        $reason = ReasonWriter::forSide(self::quiet(), $side, []);
        self::assertNotNull($reason);

        return $reason;
    }

    /**
     * The reason's clauses, without the lead.
     *
     * @param array<int, string> $teamNames
     */
    private static function body(SideView $side, array $teamNames = []): string
    {
        $reason = ReasonWriter::forSide(self::quiet(), $side, $teamNames);
        self::assertNotNull($reason);

        return explode(ReasonWriter::LEAD_SEPARATOR, $reason, 2)[1];
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
     * @param array<int, ProfileDelta> $deltas extra deltas by offset
     * @param list<ProjectionPlayer>|null $received
     * @param list<ProjectionPlayer>|null $sent
     * @param array<int, float>|null $pickWorth pickId => worth; null gives every received pick 1.0
     * @param list<int> $dropAfter offsets to remove from the after states
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
        array $deltas = [],
        ?array $received = null,
        ?array $sent = null,
        ?array $pickWorth = null,
        array $dropAfter = [],
    ): SideView {
        $base ??= self::quiet()->user;
        $components = $byOffset + [0 => $offsetZero];
        foreach ([0, 1, 2, 3] as $o) {
            $components[$o] ??= [];
        }
        ksort($components);
        $afterStates = array_replace($base->after, $after ?? []);
        foreach ($dropAfter as $o) {
            unset($afterStates[$o]);
        }
        $worth = $base->pickWorthById;
        if ($receivedPicks !== null) {
            $worth = $pickWorth ?? array_fill_keys(array_map(static fn (PickAsset $p): int => $p->pickId, $receivedPicks), 1.0);
        }

        return new SideView(
            $base->tid,
            $mode,
            $sent ?? $base->sent,
            $received ?? $base->received,
            $base->sentPicks,
            $receivedPicks ?? $base->receivedPicks,
            array_replace($base->before, $before ?? []),
            $afterStates,
            new SideGain($base->tid, $mode, $components, $weights, 1.0),
            $base->situation,
            $surplus ?? $base->surplusByPid,
            array_replace($base->delta, $delta === null ? [] : [0 => $delta], $deltas),
            $worth,
            $base->availabilityByPid,
            $injury,
        );
    }

    /**
     * @param array<int, int> $salaryByYear
     */
    private static function player(int $pid, string $name, array $salaryByYear = [1 => 500]): ProjectionPlayer
    {
        return ProjectionFactory::player(pid: $pid, tid: VetoDealFactory::PARTNER_TID, salaryByYear: $salaryByYear, name: $name);
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
     * The state with SF's best bench rank set and every other slot at 10.
     */
    private static function withBench(TeamState $state, int $sfRank): TeamState
    {
        return self::withBenchRanks($state, ['SF' => $sfRank]);
    }

    /**
     * The state with the given best bench ranks and every other slot at 10.
     *
     * @param array<string, ?int> $ranks slot => rank
     */
    private static function withBenchRanks(TeamState $state, array $ranks): TeamState
    {
        $bench = $state->bestBenchRank;
        foreach (SlotMapper::SLOTS as $slot) {
            $bench[$slot] = array_key_exists($slot, $ranks) ? $ranks[$slot] : 10;
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
