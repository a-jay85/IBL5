<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\PickToNonBurdenTaker;
use TradeSuggestions\Veto\VetoSet;

final class PickToNonBurdenTakerTest extends TestCase
{
    public function testFiresWhenPickGoesToLighterBurden(): void
    {
        $deal = self::swapWithPick(700, 300);

        self::assertLessThan(self::burden($deal, false), self::burden($deal, true));
        self::assertTrue((new PickToNonBurdenTaker())->fires($deal));
    }

    public function testEqualBurdenFires(): void
    {
        $deal = VetoDealFactory::pickToNonBurdenTaker()['deal'];

        self::assertSame(self::burden($deal, false), self::burden($deal, true));
        self::assertTrue((new PickToNonBurdenTaker())->fires($deal));
    }

    public function testPickToHeavierBurdenDoesNotFire(): void
    {
        $deal = self::swapWithPick(300, 700);

        self::assertGreaterThan(self::burden($deal, false), self::burden($deal, true));
        self::assertFalse((new PickToNonBurdenTaker())->fires($deal));
    }

    public function testNoPickMovedNeverFires(): void
    {
        self::assertFalse((new PickToNonBurdenTaker())->fires(VetoDealFactory::quiet()['deal']));
    }

    public function testOtherVetoesQuietOnFixture(): void
    {
        $report = VetoSet::standard()->evaluate(VetoDealFactory::pickToNonBurdenTaker()['deal']);

        self::assertSame(['PICK_TO_NON_BURDEN_TAKER'], $report->firedIds());
    }

    /**
     * The user swaps its backup SF for the partner's and takes the partner's pick.
     */
    private static function swapWithPick(int $userSalary, int $partnerSalary): DealView
    {
        return VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [7 => ['salary' => [1 => $userSalary, 2 => $userSalary]]],
            [7 => ['salary' => [1 => $partnerSalary, 2 => $partnerSalary]]],
            [VetoDealFactory::uid(7)],
            [],
            [VetoDealFactory::pid(2, 7)],
            [VetoDealFactory::PARTNER_PICK_ID],
        )['deal'];
    }

    private static function burden(DealView $deal, bool $user): int
    {
        $side = $user ? $deal->user : $deal->partner;

        return $side->negativeSurplusAbsorbed() + $side->capRoomConsumed();
    }
}
