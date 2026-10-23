<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\RosterFactory as F;
use TradeSuggestions\LeagueStarterRanks;
use TradeSuggestions\Need;
use TradeSuggestions\NeedKind;
use TradeSuggestions\NeedsCard;
use TradeSuggestions\NeedsCardBuilder;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamDepth;
use TradeSuggestions\TeamMode;

final class NeedsCardBuilderTest extends TestCase
{
    public function testOverHardCapFiresAboveHardCap(): void
    {
        $card = self::build(F::team(1, [], payroll: 7001));

        self::assertSame(1, self::need($card, NeedKind::OverHardCap)?->severity);
    }

    public function testOverHardCapSilentAtExactlyHardCap(): void
    {
        $card = self::build(F::team(1, [], payroll: 7000));

        self::assertFalse($card->has(NeedKind::OverHardCap));
    }

    public function testCappedOutFiresWithinMargin(): void
    {
        $card = self::build(F::team(1, [], payroll: 6850));

        self::assertSame(0, self::need($card, NeedKind::CappedOut)?->severity);
    }

    public function testCappedOutSilentBelowMargin(): void
    {
        $card = self::build(F::team(1, [], payroll: 6849));

        self::assertFalse($card->has(NeedKind::CappedOut));
    }

    public function testRoomToAbsorbFiresForNonContenderWithRoom(): void
    {
        $card = self::build(F::team(1, [], payroll: 5500), mode: TeamMode::Rebuild);

        self::assertSame(1500, self::need($card, NeedKind::RoomToAbsorb)?->severity);
    }

    public function testRoomToAbsorbSilentForWinNowTeam(): void
    {
        $card = self::build(F::team(1, [], payroll: 5500), mode: TeamMode::WinNow);

        self::assertFalse($card->has(NeedKind::RoomToAbsorb));
    }

    public function testBadContractFiresForOldLongWeakStarter(): void
    {
        $vet = F::player(1, 'C', age: 31, salary: 800, years: 3, dcMinutes: 30, points: 200, futureSalary: 1600);
        $card = self::build(F::team(1, [$vet]), F::depth(['C' => $vet]), F::ranks('C', $vet, [400, 500, 600]));

        $need = self::requireNeed($card, NeedKind::BadContract);
        self::assertSame([1], $need->pids);
        self::assertSame(1600, $need->severity);
        self::assertSame('C', $need->slot);
    }

    public function testBadContractSilentWithTwoYearsLeft(): void
    {
        $vet = F::player(1, 'C', age: 31, salary: 800, years: 2, dcMinutes: 30, points: 200, futureSalary: 800);
        $card = self::build(F::team(1, [$vet]), F::depth(['C' => $vet]), F::ranks('C', $vet, [400, 500, 600]));

        self::assertFalse($card->has(NeedKind::BadContract));
    }

    public function testBadContractSilentForStarterEarningHisDeal(): void
    {
        $vet = F::player(1, 'C', age: 31, salary: 800, years: 3, dcMinutes: 30, points: 450, futureSalary: 1600);
        $card = self::build(F::team(1, [$vet]), F::depth(['C' => $vet]), F::ranks('C', $vet, [400, 500, 600]));

        self::assertFalse($card->has(NeedKind::BadContract));
    }

    public function testWeakStarterFiresInBottomFour(): void
    {
        $starter = F::player(1, 'PG', dcMinutes: 30, points: 100);
        $card = self::build(F::team(1, [$starter]), F::depth(['PG' => $starter]), F::ranks('PG', $starter, [200, 210, 220, 230, 240, 250, 260]));

        $need = self::requireNeed($card, NeedKind::WeakStarter);
        self::assertSame(4, $need->severity);
        self::assertSame('PG', $need->slot);
    }

    public function testWeakStarterSilentForUnrankedStarter(): void
    {
        $starter = F::player(1, 'PG', dcMinutes: 30);
        $card = self::build(F::team(1, [$starter]), F::depth(['PG' => $starter]), F::ranks('PG', $starter, [200, 210, 220, 230, 240, 250, 260, 270]));

        self::assertFalse($card->has(NeedKind::WeakStarter));
    }

    public function testWeakStarterSilentInSmallField(): void
    {
        $starter = F::player(1, 'PG', dcMinutes: 30, points: 100);
        $card = self::build(F::team(1, [$starter]), F::depth(['PG' => $starter]), F::ranks('PG', $starter, [200, 210, 220, 230, 240, 250]));

        self::assertFalse($card->has(NeedKind::WeakStarter));
    }

    public function testLogjamFiresAtThreeRealMinutePlayers(): void
    {
        $starter = F::player(1, 'C', dcMinutes: 30);
        $players = [$starter, F::player(2, 'C', dcMinutes: 15), F::player(3, 'C', dcMinutes: 20)];
        $card = self::build(F::team(1, $players), F::depth(['C' => $starter]));

        $need = self::requireNeed($card, NeedKind::Logjam);
        self::assertSame([2], $need->pids);
        self::assertSame(1, $need->severity);
        self::assertSame('C', $need->slot);
    }

    public function testLogjamSilentAtTwo(): void
    {
        $starter = F::player(1, 'C', dcMinutes: 30);
        $players = [$starter, F::player(2, 'C', dcMinutes: 15), F::player(3, 'C', dcMinutes: 14)];
        $card = self::build(F::team(1, $players), F::depth(['C' => $starter]));

        self::assertFalse($card->has(NeedKind::Logjam));
    }

    public function testDeadWeightFiresForPaidUnusedPlayer(): void
    {
        $card = self::build(F::team(1, [F::player(1, salary: 600, years: 2, dcMinutes: 9)]));

        $need = self::requireNeed($card, NeedKind::DeadWeight);
        self::assertSame([1], $need->pids);
        self::assertSame(1200, $need->severity);
    }

    public function testDeadWeightSilentAtUnusedBoundary(): void
    {
        $card = self::build(F::team(1, [F::player(1, salary: 600, years: 2, dcMinutes: 10)]));

        self::assertFalse($card->has(NeedKind::DeadWeight));
    }

    public function testBadContractPlayerIsNotAlsoDeadWeight(): void
    {
        $card = self::build(F::team(1, [F::player(1, age: 31, salary: 800, years: 3, futureSalary: 1600)]));

        self::assertTrue($card->has(NeedKind::BadContract));
        self::assertFalse($card->has(NeedKind::DeadWeight));
    }

    public function testExpiringStarOnLoserFires(): void
    {
        $star = F::player(1, 'SF', salary: 1200, years: 1, dcMinutes: 36, points: 600);
        $card = self::build(F::team(1, [$star]), F::depth(['SF' => $star]), F::ranks('SF', $star, [300, 400]), recordPct: 0.40);

        $need = self::requireNeed($card, NeedKind::ExpiringStarOnLoser);
        self::assertSame([1], $need->pids);
        self::assertSame(1200, $need->severity);
    }

    public function testExpiringStarSilentOnWinningTeam(): void
    {
        $star = F::player(1, 'SF', salary: 1200, years: 1, dcMinutes: 36, points: 600);
        $card = self::build(F::team(1, [$star]), F::depth(['SF' => $star]), F::ranks('SF', $star, [300, 400]), recordPct: 0.45);

        self::assertFalse($card->has(NeedKind::ExpiringStarOnLoser));
    }

    public function testConsolidateFiresForDeepTeamWithoutTopStarter(): void
    {
        $card = self::build(F::team(1, self::deepRoster()));

        self::assertSame(1, self::need($card, NeedKind::Consolidate)?->severity);
    }

    public function testConsolidateSilentWithTopFiveStarter(): void
    {
        $players = self::deepRoster();
        $star = $players[0];
        $card = self::build(F::team(1, $players), F::depth(['PG' => $star]), F::ranks('PG', $star, [100, 200]));

        self::assertFalse($card->has(NeedKind::Consolidate));
    }

    public function testDirectionNeedFollowsGmOverride(): void
    {
        $card = self::build(F::team(1, []), mode: TeamMode::Rebuild, recordPct: 0.70);

        self::assertTrue($card->has(NeedKind::Rebuild));
        self::assertFalse($card->has(NeedKind::WinNow));
    }

    public function testFutureYearOnlyCapStrainIsNeverTopNeed(): void
    {
        $starter = F::player(1, 'C', dcMinutes: 30, laterSalaries: [2500]);
        $players = [
            $starter,
            F::player(2, 'C', dcMinutes: 20, laterSalaries: [2500]),
            F::player(3, 'C', dcMinutes: 20, laterSalaries: [2400]),
        ];
        $card = self::build(F::team(1, $players, payroll: 6500), F::depth(['C' => $starter]));

        self::assertSame(NeedKind::Logjam, $card->topNeed()?->kind);
        self::assertSame(140, self::need($card, NeedKind::FutureCapStrain)?->severity);
        self::assertFalse($card->has(NeedKind::OverHardCap));
        self::assertSame(7400, $card->futurePayroll[1]);
    }

    public function testFutureCapStrainDiscountsYearTwoBelowYearOne(): void
    {
        $card = self::build(F::team(1, [F::player(1, dcMinutes: 30, laterSalaries: [0, 7400])]));

        self::assertSame(60, self::need($card, NeedKind::FutureCapStrain)?->severity);
    }

    public function testTopNeedFollowsPriorityOrder(): void
    {
        $starter = F::player(1, 'C', dcMinutes: 30);
        $players = [$starter, F::player(2, 'C', dcMinutes: 20), F::player(3, 'C', dcMinutes: 20)];
        $card = self::build(F::team(1, $players, payroll: 7100), F::depth(['C' => $starter]));

        self::assertSame(NeedKind::OverHardCap, $card->topNeed()?->kind);
        self::assertTrue($card->has(NeedKind::Logjam));
    }

    public function testNeedsSortBySeverityWithinAPriority(): void
    {
        $card = self::build(F::team(1, [
            F::player(1, salary: 600, years: 1, dcMinutes: 0),
            F::player(2, salary: 900, years: 2, dcMinutes: 0),
        ]));

        $dead = array_values(array_filter($card->needs, static fn (Need $n): bool => $n->kind === NeedKind::DeadWeight));
        self::assertSame([[2], [1]], array_map(static fn (Need $n): array => $n->pids, $dead));
    }

    /**
     * Nine 15-minute players with no ranked starter.
     *
     * @return list<\TradeSuggestions\SuggestionPlayer>
     */
    private static function deepRoster(): array
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C', 'PG', 'SG', 'SF', 'PF'];
        $players = [];
        foreach ($positions as $i => $pos) {
            $players[] = F::player($i + 1, $pos, dcMinutes: 15, points: 300);
        }

        return $players;
    }

    private static function build(
        TeamAssets $team,
        ?TeamDepth $depth = null,
        ?LeagueStarterRanks $ranks = null,
        TeamMode $mode = TeamMode::WinNow,
        float $recordPct = 0.5,
    ): NeedsCard {
        return (new NeedsCardBuilder())->build($team, $depth ?? F::depth([]), $ranks ?? F::emptyRanks(), $mode, $recordPct);
    }

    private static function requireNeed(NeedsCard $card, NeedKind $kind): Need
    {
        $need = self::need($card, $kind);
        self::assertNotNull($need, $kind->name . ' did not fire');

        return $need;
    }

    private static function need(NeedsCard $card, NeedKind $kind): ?Need
    {
        foreach ($card->needs as $need) {
            if ($need->kind === $kind) {
                return $need;
            }
        }

        return null;
    }
}
