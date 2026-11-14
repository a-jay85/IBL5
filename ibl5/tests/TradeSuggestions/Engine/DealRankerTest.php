<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\Engine\DealRanker;
use TradeSuggestions\Engine\ExposureFilter;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\RankGate;
use TradeSuggestions\Engine\ScoredDeal;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\VetoSet;

final class DealRankerTest extends TestCase
{
    private const int USER = 1;
    private const int PARTNER = 2;

    private static ?LeagueSnapshot $league = null;

    public function testRanksByWeakerSideGainDescending(): void
    {
        $s = self::league();
        $result = (new DealRanker())->select($s, self::simKey($s, self::USER, self::PARTNER), self::USER, [
            self::scored(self::oneForOne(), 5.0, 9.0),
            self::scored(self::oneForOne(), 7.0, 8.0),
            self::scored(self::oneForOne(), 2.0, 20.0),
        ], new VetoSet([]), self::passAll());

        self::assertSame([7.0, 5.0], array_map(static fn ($r): float => $r->deal->user->total(), $result->selected));
        self::assertSame(1, $result->diagnostics->lopsided);
        self::assertSame([1, 2], array_map(static fn ($r): int => $r->rank, $result->selected));
    }

    public function testExactlyThreeTimesPasses(): void
    {
        $result = self::rank([self::scored(self::oneForOne(), 3.0, 9.0)]);

        self::assertCount(1, $result->selected);
        self::assertSame(0, $result->diagnostics->lopsided);
    }

    public function testOverThreeTimesIsLopsided(): void
    {
        $result = self::rank([self::scored(self::oneForOne(), 3.0, 9.01)]);

        self::assertSame([], $result->selected);
        self::assertSame(1, $result->diagnostics->lopsided);
    }

    public function testZeroWeakerSideIsNotBothPositive(): void
    {
        $result = self::rank([self::scored(self::oneForOne(), 0.0, 4.0)]);

        self::assertSame([], $result->selected);
        self::assertSame(1, $result->diagnostics->notBothPositive);
        self::assertSame(0, $result->diagnostics->lopsided);
    }

    public function testNinthGmTargetingOnePartnerIsRefused(): void
    {
        $s = self::league();
        $simKey = 'sim-exposure';
        $order = self::targetingOrder($s, $simKey, self::PARTNER);
        $eighth = $order[7];
        $ninth = $order[8];
        $ranker = new DealRanker();

        $refused = $ranker->select($s, $simKey, $ninth, [self::scored(self::deal($ninth, self::PARTNER), 5.0, 5.0)], new VetoSet([]), self::passAll());
        $allowed = $ranker->select($s, $simKey, $eighth, [self::scored(self::deal($eighth, self::PARTNER), 5.0, 5.0)], new VetoSet([]), self::passAll());

        self::assertSame(1, $refused->diagnostics->exposure);
        self::assertSame([], $refused->selected);
        self::assertSame(0, $allowed->diagnostics->exposure);
        self::assertCount(1, $allowed->selected);
    }

    public function testVetoedDealsNeverReachTheCallback(): void
    {
        $fixture = VetoDealFactory::youngerBetterCheaper();
        $s = $fixture['snapshot'];
        $calls = 0;
        $result = (new DealRanker())->select(
            $s,
            self::simKey($s, self::USER, self::PARTNER),
            self::USER,
            [self::scored($fixture['deal']->candidate, 5.0, 5.0)],
            VetoSet::standard(),
            static function () use (&$calls): array {
                $calls++;

                return [RankGate::Pass, '', 'u', 'p'];
            },
        );

        self::assertSame(0, $calls);
        self::assertSame(1, $result->diagnostics->vetoed);
        self::assertSame(1, $result->diagnostics->vetoedById['YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE']);
        self::assertSame([], $result->selected);
    }

    public function testEmptyInputGivesEmptyResultWithZeroDiagnostics(): void
    {
        $result = self::rank([]);

        self::assertSame([], $result->selected);
        self::assertSame([], $result->eligible);
        $d = $result->diagnostics;
        self::assertSame(
            [0, 0, 0, 0, 0, 0, [], 0, 0],
            [$d->generated, $d->notBothPositive, $d->lopsided, $d->exposure, $d->vetoed, $d->validated, $d->validatorRejections, $d->noMotive, $d->selected],
        );
    }

    public function testValidationBudgetStopsAtForty(): void
    {
        $deals = array_fill(0, 41, self::scored(self::oneForOne(), 5.0, 5.0));
        $result = self::rank($deals, static fn (): array => [RankGate::Validator, SuggestionValidationFilter::FAIL_CAP, null, null]);

        self::assertSame(40, $result->diagnostics->validated);
    }

    public function testSelectedCapsAtLimitAndEligibleAtKeepTop(): void
    {
        $deals = array_fill(0, 20, self::scored(self::oneForOne(), 5.0, 5.0));
        $result = self::rank($deals);

        self::assertCount(3, $result->selected);
        self::assertCount(12, $result->eligible);
        self::assertSame(12, $result->diagnostics->validated);
        self::assertSame(3, $result->diagnostics->selected);
        self::assertSame(range(1, 12), array_map(static fn ($r): int => $r->rank, $result->eligible));
    }

    public function testValidatorRejectionsCountedByDetail(): void
    {
        $deals = array_fill(0, 2, self::scored(self::oneForOne(), 5.0, 5.0));
        $result = self::rank($deals, static fn (): array => [RankGate::Validator, SuggestionValidationFilter::FAIL_CAP, null, null]);

        self::assertSame([SuggestionValidationFilter::FAIL_CAP => 2], $result->diagnostics->validatorRejections);
        self::assertSame(0, $result->diagnostics->noMotive);
    }

    public function testNoMotiveCountedSeparately(): void
    {
        $deals = array_fill(0, 2, self::scored(self::oneForOne(), 5.0, 5.0));
        $result = self::rank($deals, static fn (): array => [RankGate::NoMotive, 'partner', null, null]);

        self::assertSame(2, $result->diagnostics->noMotive);
        self::assertSame([], $result->diagnostics->validatorRejections);
        self::assertSame([], $result->selected);
    }

    public function testNoGateIsSkippedForLargeGains(): void
    {
        $fixture = VetoDealFactory::youngerBetterCheaper();
        $s = $fixture['snapshot'];
        $result = (new DealRanker())->select(
            $s,
            self::simKey($s, self::USER, self::PARTNER),
            self::USER,
            [self::scored($fixture['deal']->candidate, 100.0, 120.0)],
            VetoSet::standard(),
            self::passAll(),
        );

        self::assertSame(1, $result->diagnostics->vetoed);
        self::assertSame([], $result->selected);
    }

    public function testTieBreaksPreferFewerAssets(): void
    {
        $twoForOne = new TradeCandidate(self::USER, self::PARTNER, [101, 102], [], [201], []);
        $result = self::rank([
            self::scored($twoForOne, 5.0, 5.0),
            self::scored(self::oneForOne(), 5.0, 5.0),
        ]);

        self::assertEquals([self::oneForOne(), $twoForOne], array_map(static fn ($r): TradeCandidate => $r->deal->candidate, $result->selected));
    }

    public function testDiagnosticsCarryEveryVetoIdEvenAtZero(): void
    {
        $s = self::league();
        $result = (new DealRanker())->select($s, 'k', self::USER, [], VetoSet::standard(), self::passAll());

        self::assertSame(array_fill_keys(VetoSet::standard()->ids(), 0), $result->diagnostics->vetoedById);
    }

    /**
     * @param list<ScoredDeal> $deals
     * @param (callable(ScoredDeal, DealView): array{0: RankGate, 1: string, 2: ?string, 3: ?string})|null $passes
     */
    private static function rank(array $deals, ?callable $passes = null): \TradeSuggestions\Engine\RankResult
    {
        $s = self::league();

        return (new DealRanker())->select($s, self::simKey($s, self::USER, self::PARTNER), self::USER, $deals, new VetoSet([]), $passes ?? self::passAll());
    }

    /**
     * @return callable(ScoredDeal, DealView): array{0: RankGate, 1: string, 2: ?string, 3: ?string}
     */
    private static function passAll(): callable
    {
        return static fn (ScoredDeal $d, DealView $v): array => [RankGate::Pass, '', 'user reason', 'partner reason'];
    }

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league(perTeam: 14);
    }

    private static function oneForOne(): TradeCandidate
    {
        return self::deal(self::USER, self::PARTNER);
    }

    private static function deal(int $userTid, int $partnerTid): TradeCandidate
    {
        return new TradeCandidate($userTid, $partnerTid, [$userTid * 100 + 1], [], [$partnerTid * 100 + 1], []);
    }

    private static function scored(TradeCandidate $c, float $user, float $partner): ScoredDeal
    {
        return new ScoredDeal($c, 'test', self::gain($c->userTid, $user), self::gain($c->partnerTid, $partner), [], []);
    }

    private static function gain(int $tid, float $total): SideGain
    {
        return new SideGain($tid, TeamMode::Middle, [0 => ['starter_slots' => $total]], [1.0], 1.0);
    }

    /**
     * The first sim key under which the user may target the partner.
     */
    private static function simKey(LeagueSnapshot $s, int $userTid, int $partnerTid): string
    {
        for ($i = 0; $i < 1000; $i++) {
            $key = 'sim-' . $i;
            if (in_array($partnerTid, ExposureFilter::eligiblePartners($key, $userTid, $s->teamIds), true)) {
                return $key;
            }
        }
        self::fail('no sim key lets the user target the partner');
    }

    /**
     * Every other team in the order `ExposureFilter` lets it target the partner.
     *
     * @return list<int>
     */
    private static function targetingOrder(LeagueSnapshot $s, string $simKey, int $partnerTid): array
    {
        $others = array_values(array_filter($s->teamIds, static fn (int $t): bool => $t !== $partnerTid));
        usort($others, static fn (int $a, int $b): int
            => strcmp(ExposureFilter::rankOf($simKey, $partnerTid, $a), ExposureFilter::rankOf($simKey, $partnerTid, $b)));

        return $others;
    }
}
