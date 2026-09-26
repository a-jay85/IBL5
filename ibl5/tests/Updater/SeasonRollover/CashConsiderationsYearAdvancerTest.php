<?php

declare(strict_types=1);

namespace Tests\Updater\SeasonRollover;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;
use Updater\SeasonRollover\CashConsiderationsYearAdvancer;

final class CashConsiderationsYearAdvancerTest extends TestCase
{
    public function testAdvancesWhenMarkerBelowTargetYear(): void
    {
        $ledger = self::createMock(BuyoutLedgerRepositoryInterface::class);
        $ledger->expects($this->once())
            ->method('advanceAllCy')
            ->willReturn(7);

        $settings = self::createMock(LeagueControlPanelRepositoryInterface::class);
        $settings->expects($this->once())
            ->method('getSetting')
            ->with('Cash Considerations Last Advanced Year')
            ->willReturn('2008');
        $settings->expects($this->once())
            ->method('updateSetting')
            ->with('Cash Considerations Last Advanced Year', '2009');

        $advancer = new CashConsiderationsYearAdvancer($ledger, $settings);
        $result = $advancer->advance(2009);

        self::assertSame(7, $result);
    }

    public function testSkipsWhenMarkerEqualsTargetYear(): void
    {
        $ledger = self::createMock(BuyoutLedgerRepositoryInterface::class);
        $ledger->expects($this->never())->method('advanceAllCy');

        $settings = self::createMock(LeagueControlPanelRepositoryInterface::class);
        $settings->expects($this->once())
            ->method('getSetting')
            ->with('Cash Considerations Last Advanced Year')
            ->willReturn('2009');
        $settings->expects($this->never())->method('updateSetting');

        $advancer = new CashConsiderationsYearAdvancer($ledger, $settings);
        $result = $advancer->advance(2009);

        self::assertSame(0, $result);
    }

    public function testSkipsWhenMarkerAboveTargetYear(): void
    {
        $ledger = self::createMock(BuyoutLedgerRepositoryInterface::class);
        $ledger->expects($this->never())->method('advanceAllCy');

        $settings = self::createMock(LeagueControlPanelRepositoryInterface::class);
        $settings->expects($this->once())
            ->method('getSetting')
            ->with('Cash Considerations Last Advanced Year')
            ->willReturn('2010');
        $settings->expects($this->never())->method('updateSetting');

        $advancer = new CashConsiderationsYearAdvancer($ledger, $settings);
        $result = $advancer->advance(2009);

        self::assertSame(0, $result);
    }

    public function testThrowsAndDoesNotAdvanceWhenMarkerIsMissing(): void
    {
        $ledger = self::createMock(BuyoutLedgerRepositoryInterface::class);
        $ledger->expects($this->never())->method('advanceAllCy');

        $settings = self::createStub(LeagueControlPanelRepositoryInterface::class);
        $settings->method('getSetting')->willReturn(null);

        $advancer = new CashConsiderationsYearAdvancer($ledger, $settings);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/migration 188/');
        $advancer->advance(2009);
    }

    public function testThrowsAndDoesNotAdvanceWhenMarkerIsNotNumeric(): void
    {
        $ledger = self::createMock(BuyoutLedgerRepositoryInterface::class);
        $ledger->expects($this->never())->method('advanceAllCy');

        $settings = self::createStub(LeagueControlPanelRepositoryInterface::class);
        $settings->method('getSetting')->willReturn('not-a-year');

        $advancer = new CashConsiderationsYearAdvancer($ledger, $settings);

        $this->expectException(\RuntimeException::class);
        $advancer->advance(2009);
    }

    public function testThrowsWhenTargetYearIsNotPositive(): void
    {
        $ledger = self::createMock(BuyoutLedgerRepositoryInterface::class);
        $ledger->expects($this->never())->method('advanceAllCy');

        $settings = self::createMock(LeagueControlPanelRepositoryInterface::class);
        $settings->expects($this->never())->method('getSetting');

        $advancer = new CashConsiderationsYearAdvancer($ledger, $settings);

        $this->expectException(\RuntimeException::class);
        $advancer->advance(0);
    }
}
