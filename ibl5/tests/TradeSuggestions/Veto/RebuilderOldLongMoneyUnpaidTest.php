<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\Engine\MoneyPlaces;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Valuation\ContractValue;
use TradeSuggestions\Veto\RebuilderOldLongMoneyUnpaid;
use TradeSuggestions\Veto\VetoSet;

final class RebuilderOldLongMoneyUnpaidTest extends TestCase
{
    public function testFiresOnOldLongMoneyWithNoPicks(): void
    {
        self::assertTrue((new RebuilderOldLongMoneyUnpaid())->fires(VetoDealFactory::rebuilderOldLongMoney()['deal']));
    }

    public function testAgeTwentyEightDoesNotFire(): void
    {
        self::assertFalse((new RebuilderOldLongMoneyUnpaid())->fires(VetoDealFactory::rebuilderOldLongMoney(age: 28)['deal']));
    }

    public function testAgeTwentyNineFires(): void
    {
        self::assertTrue((new RebuilderOldLongMoneyUnpaid())->fires(VetoDealFactory::rebuilderOldLongMoney(age: 29)['deal']));
    }

    public function testOneFutureSeasonDoesNotFire(): void
    {
        $deal = VetoDealFactory::rebuilderOldLongMoney(salary: [1 => 1500, 2 => 1500])['deal'];

        self::assertFalse((new RebuilderOldLongMoneyUnpaid())->fires($deal));
    }

    public function testTwoFutureSeasonsFire(): void
    {
        $deal = VetoDealFactory::rebuilderOldLongMoney(salary: [1 => 1500, 2 => 1500, 3 => 1500])['deal'];

        self::assertTrue((new RebuilderOldLongMoneyUnpaid())->fires($deal));
    }

    public function testPicksWorthExactlyTheBurdenDoNotFire(): void
    {
        $deal = VetoDealFactory::rebuilderOldLongMoney(partnerPick: true)['deal'];
        $pid = VetoDealFactory::pid(2, 7);
        $burden = ContractValue::negativeSurplusBeyondCurrent($deal->user->surplusByPid[$pid]);
        self::assertGreaterThan(0, $burden);

        $veto = new RebuilderOldLongMoneyUnpaid();
        $paid = VetoDealFactory::withPickWorth($deal, $deal->user, VetoDealFactory::PARTNER_PICK_ID, MoneyPlaces::fromSalary($burden));
        $short = VetoDealFactory::withPickWorth($deal, $deal->user, VetoDealFactory::PARTNER_PICK_ID, MoneyPlaces::fromSalary($burden - 1));

        self::assertFalse($veto->fires($paid));
        self::assertTrue($veto->fires($short));
    }

    public function testWinNowSideNeverFires(): void
    {
        $deal = VetoDealFactory::rebuilderOldLongMoney(userMode: TeamMode::WinNow)['deal'];

        self::assertFalse((new RebuilderOldLongMoneyUnpaid())->fires($deal));
    }

    public function testQuietFixtureDoesNotFire(): void
    {
        self::assertFalse((new RebuilderOldLongMoneyUnpaid())->fires(VetoDealFactory::quiet(TeamMode::Rebuild)['deal']));
    }

    public function testOtherVetoesQuietOnFixture(): void
    {
        $report = VetoSet::standard()->evaluate(VetoDealFactory::rebuilderOldLongMoney()['deal']);

        self::assertSame(['REBUILDER_OLD_LONG_MONEY_UNPAID'], $report->firedIds());
    }
}
