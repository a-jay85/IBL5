<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use League\League;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\RosterFactory as F;
use TradeSuggestions\LeagueStarterRanks;
use TradeSuggestions\Need;
use TradeSuggestions\NeedKind;
use TradeSuggestions\NeedPairMatcher;
use TradeSuggestions\NeedsCard;
use TradeSuggestions\PairedCandidate;
use TradeSuggestions\Pairing;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamDepth;
use TradeSuggestions\TeamMode;

final class NeedPairMatcherTest extends TestCase
{
    public function testSalaryDumpPairsOverCapTeamWithAbsorber(): void
    {
        $results = self::match(
            self::dumperSide(withSecondCenter: true),
            self::absorberSide(),
        );

        self::assertNotSame([], $results);
        self::assertSame(Pairing::SalaryDump, $results[0]->pairing);
        self::assertSame([10], $results[0]->candidate->userPids);
        self::assertSame([], $results[0]->candidate->userPickIds);
        self::assertSame([], $results[0]->candidate->partnerPids);
    }

    public function testVeteranForFuturePairsWinNowWithRebuild(): void
    {
        $vet = F::player(30, 'SF', age: 30, salary: 900, dcMinutes: 34);
        $partner = self::side(2, [$vet, F::player(31, 'SF', dcMinutes: 20)], [new Need(NeedKind::Rebuild, 1)], payroll: 6000, starters: ['SF' => $vet]);
        $user = self::side(1, [F::player(11, 'SF', dcMinutes: 30)], [new Need(NeedKind::WinNow, 1)], payroll: 5000, picks: [self::pick(101, 1)]);

        $results = self::match($user, $partner);

        self::assertNotSame([], $results);
        self::assertSame(Pairing::VeteranForFuture, $results[0]->pairing);
        self::assertSame([30], $results[0]->candidate->partnerPids);
        self::assertSame([101], $results[0]->candidate->userPickIds);
    }

    public function testSurplusForHoleSendsLogjamPlayerToWeakSlot(): void
    {
        $userStarter = F::player(11, 'C', dcMinutes: 30);
        $user = self::side(1, [$userStarter, F::player(12, 'C', dcMinutes: 16), F::player(13, 'C', dcMinutes: 20)], [
            new Need(NeedKind::Logjam, 1, 'C', [12]),
            new Need(NeedKind::WinNow, 1),
        ], starters: ['C' => $userStarter]);
        $weak = F::player(21, 'C', dcMinutes: 30);
        $partner = self::side(2, [$weak, F::player(22, 'PG', dcMinutes: 30)], [
            new Need(NeedKind::WeakStarter, 2, 'C', [21]),
            new Need(NeedKind::WinNow, 1),
        ], picks: [self::pick(201, 2)], starters: ['C' => $weak]);

        $results = self::match($user, $partner);

        self::assertNotSame([], $results);
        self::assertSame(Pairing::SurplusForHole, $results[0]->pairing);
        self::assertSame([12], $results[0]->candidate->userPids);
        self::assertSame([201], $results[0]->candidate->partnerPickIds);
    }

    public function testConsolidationSendsTwoForOne(): void
    {
        $userStarter = F::player(11, 'SF', dcMinutes: 30);
        $user = self::side(1, [$userStarter, F::player(14, 'SF', dcMinutes: 25), F::player(15, 'SF', dcMinutes: 22), F::player(16, 'SF', dcMinutes: 18)], [
            new Need(NeedKind::Consolidate, 1),
            new Need(NeedKind::WinNow, 1),
        ], starters: ['SF' => $userStarter]);
        $star = F::player(40, 'PG', dcMinutes: 36, points: 600);
        $partner = self::side(2, [$star, F::player(41, 'PG', dcMinutes: 20)], [new Need(NeedKind::Rebuild, 1)], starters: ['PG' => $star]);

        $results = self::match($user, $partner, F::ranks('PG', $star, [100]));

        self::assertNotSame([], $results);
        self::assertSame(Pairing::Consolidation, $results[0]->pairing);
        self::assertSame([14, 15], $results[0]->candidate->userPids);
        self::assertSame([40], $results[0]->candidate->partnerPids);
    }

    public function testLengthForExpiringSwapsLongForExpiring(): void
    {
        $user = self::side(1, [F::player(11, 'PF', dcMinutes: 30), F::player(16, 'PF', salary: 1000, years: 3, dcMinutes: 12)], [
            new Need(NeedKind::CappedOut, 50),
            new Need(NeedKind::WinNow, 1),
        ], payroll: 6900);
        $partner = self::side(2, [F::player(50, 'PF', salary: 900, years: 1, dcMinutes: 20), F::player(51, 'PF', dcMinutes: 20)], [
            new Need(NeedKind::RoomToAbsorb, 3000),
            new Need(NeedKind::Rebuild, 1),
        ], payroll: 4000);

        $results = self::match($user, $partner);

        $length = array_values(array_filter($results, static fn (PairedCandidate $c): bool => $c->pairing === Pairing::LengthForExpiring));
        self::assertNotSame([], $length);
        self::assertSame([16], $length[0]->candidate->userPids);
        self::assertSame([50], $length[0]->candidate->partnerPids);
    }

    public function testTwoDumpersProduceNoCandidate(): void
    {
        $user = self::side(1, [F::player(10, 'C', salary: 800, dcMinutes: 0), F::player(11, 'C', dcMinutes: 30)], [
            new Need(NeedKind::DeadWeight, 800, null, [10]),
            new Need(NeedKind::WinNow, 1),
        ]);
        $partner = self::side(2, [F::player(20, 'PG', salary: 700, dcMinutes: 0), F::player(21, 'PG', dcMinutes: 30)], [
            new Need(NeedKind::DeadWeight, 700, null, [20]),
            new Need(NeedKind::RoomToAbsorb, 3000),
            new Need(NeedKind::Rebuild, 1),
        ], payroll: 4000);

        self::assertSame([], self::match($user, $partner));
    }

    public function testTwoRebuildSellersProduceNoCandidate(): void
    {
        $user = self::side(1, [F::player(11, 'SF', age: 30, dcMinutes: 30)], [new Need(NeedKind::Rebuild, 1)]);
        $star = F::player(21, 'SF', age: 30, dcMinutes: 30, points: 600);
        $partner = self::side(2, [$star, F::player(22, 'SF', dcMinutes: 20)], [
            new Need(NeedKind::ExpiringStarOnLoser, 1000, 'SF', [21]),
            new Need(NeedKind::Rebuild, 1),
        ], starters: ['SF' => $star]);

        self::assertSame([], self::match($user, $partner));
    }

    public function testUserCanBeEitherSideOfPairing(): void
    {
        $results = self::match(
            self::absorberSide(tid: 1),
            self::dumperSide(withSecondCenter: true, tid: 2),
        );

        self::assertNotSame([], $results);
        self::assertSame(Pairing::SalaryDump, $results[0]->pairing);
        self::assertSame([], $results[0]->candidate->userPids);
        self::assertSame([10], $results[0]->candidate->partnerPids);
        self::assertSame(NeedKind::RoomToAbsorb, $results[0]->userNeed->kind);
        self::assertSame(NeedKind::DeadWeight, $results[0]->partnerNeed->kind);
    }

    public function testNoCandidateLeavesSlotWithoutEligiblePlayer(): void
    {
        self::assertSame([], self::match(self::dumperSide(withSecondCenter: false), self::absorberSide()));
    }

    public function testNoCandidateExceedsRosterMax(): void
    {
        $starter = F::player(12, 'C', dcMinutes: 30);
        $user = self::side(1, [F::player(10, 'C', salary: 200), F::player(11, 'C', salary: 200), $starter], [
            new Need(NeedKind::OverHardCap, 300),
            new Need(NeedKind::WinNow, 1),
        ], payroll: 7300, starters: ['C' => $starter]);
        $partnerTeam = new TeamAssets(2, [F::player(20, 'PG', dcMinutes: 30)], [], 14, League::HARD_CAP_MAX - 4000);
        $partner = self::sideFor($partnerTeam, [new Need(NeedKind::RoomToAbsorb, 3000), new Need(NeedKind::Rebuild, 1)], []);

        self::assertSame([], self::match($user, $partner));
    }

    public function testCandidatesPerPartnerCapped(): void
    {
        $vets = [];
        $starters = [];
        foreach (['PG', 'SG', 'SF', 'PF', 'C'] as $i => $slot) {
            $vets[] = $starters[$slot] = F::player(30 + $i, $slot, age: 30, salary: 300, dcMinutes: 30);
            $vets[] = F::player(40 + $i, $slot, dcMinutes: 20);
        }
        $partner = self::side(2, $vets, [new Need(NeedKind::Rebuild, 1)], payroll: 5000, starters: $starters);
        $user = self::side(1, [F::player(11, 'SF', dcMinutes: 30), F::player(12, 'SF', age: 22)], [new Need(NeedKind::WinNow, 1)], payroll: 4000, picks: [
            self::pick(101, 1), self::pick(102, 2), self::pick(103, 2),
        ]);

        self::assertCount(4, self::match($user, $partner));
        self::assertCount(2, self::match($user, $partner, maxPerPartner: 2));
    }

    public function testNoCandidateCarriesMoreThanTwoPicksPerSide(): void
    {
        $vet = F::player(30, 'SF', age: 30, salary: 300, dcMinutes: 34);
        $partner = self::side(2, [$vet, F::player(31, 'SF', dcMinutes: 20)], [new Need(NeedKind::Rebuild, 1)], starters: ['SF' => $vet]);
        $user = self::side(1, [F::player(11, 'SF', dcMinutes: 30)], [new Need(NeedKind::WinNow, 1)], payroll: 4000, picks: [
            self::pick(101, 1), self::pick(102, 1), self::pick(103, 2), self::pick(104, 2),
        ]);

        $results = self::match($user, $partner);

        self::assertNotSame([], $results);
        foreach ($results as $result) {
            self::assertLessThanOrEqual(2, count($result->candidate->userPickIds));
            self::assertLessThanOrEqual(2, count($result->candidate->partnerPickIds));
        }
    }

    /**
     * @return array{NeedsCard, TeamAssets, TeamDepth}
     */
    private static function dumperSide(bool $withSecondCenter, int $tid = 1): array
    {
        $players = [F::player(10, 'C', salary: 800, years: 1, dcMinutes: 0)];
        $starters = [];
        if ($withSecondCenter) {
            $players[] = $starters['C'] = F::player(11, 'C', dcMinutes: 30);
        }

        return self::side($tid, $players, [
            new Need(NeedKind::DeadWeight, 800, null, [10]),
            new Need(NeedKind::WinNow, 1),
        ], payroll: 6000, picks: [self::pick($tid * 100 + 1, 1), self::pick($tid * 100 + 2, 2)], starters: $starters);
    }

    /**
     * @return array{NeedsCard, TeamAssets, TeamDepth}
     */
    private static function absorberSide(int $tid = 2): array
    {
        return self::side($tid, [F::player($tid * 10 + 100, 'PG', dcMinutes: 30)], [
            new Need(NeedKind::RoomToAbsorb, 3000),
            new Need(NeedKind::Rebuild, 1),
        ], payroll: 4000);
    }

    /**
     * @param list<SuggestionPlayer> $players
     * @param list<Need> $needs
     * @param list<SuggestionPick> $picks
     * @param array<string, SuggestionPlayer> $starters
     * @return array{NeedsCard, TeamAssets, TeamDepth}
     */
    private static function side(int $tid, array $players, array $needs, int $payroll = 6000, array $picks = [], array $starters = []): array
    {
        return self::sideFor(new TeamAssets($tid, $players, $picks, null, League::HARD_CAP_MAX - $payroll), $needs, $starters);
    }

    /**
     * @param list<Need> $needs
     * @param array<string, SuggestionPlayer> $starters
     * @return array{NeedsCard, TeamAssets, TeamDepth}
     */
    private static function sideFor(TeamAssets $team, array $needs, array $starters): array
    {
        $mode = in_array(NeedKind::WinNow, array_map(static fn (Need $n): NeedKind => $n->kind, $needs), true) ? TeamMode::WinNow : TeamMode::Rebuild;
        $card = new NeedsCard($team->tid, $needs, League::HARD_CAP_MAX - ($team->capRoom ?? 0), [], $mode, 0.5);

        return [$card, $team, F::depth($starters)];
    }

    private static function pick(int $pickId, int $round): SuggestionPick
    {
        return new SuggestionPick($pickId, 1, 2090, $round, 0.0);
    }

    /**
     * @param array{NeedsCard, TeamAssets, TeamDepth} $user
     * @param array{NeedsCard, TeamAssets, TeamDepth} $partner
     * @return list<PairedCandidate>
     */
    private static function match(array $user, array $partner, ?LeagueStarterRanks $ranks = null, int $maxPerPartner = 12): array
    {
        $tid = $partner[1]->tid;
        $generator = (new NeedPairMatcher($maxPerPartner))->generate(
            $user[0],
            $user[1],
            [$tid => $partner[0]],
            [$tid => $partner[1]],
            $user[2],
            [$tid => $partner[2]],
            $ranks ?? F::emptyRanks(),
        );

        return array_values(iterator_to_array($generator, false));
    }
}
