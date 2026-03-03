<?php

declare(strict_types=1);

namespace Tests\LeagueControlPanel;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use LeagueControlPanel\Contracts\LeagueControlPanelServiceInterface;
use LeagueControlPanel\LeagueControlPanelService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LeagueControlPanel\LeagueControlPanelService
 */
class LeagueControlPanelServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getBulkSettings')->willReturn([]);
        $stub->method('getSimLengthInDays')->willReturn(3);

        $service = new LeagueControlPanelService($stub);

        $this->assertInstanceOf(LeagueControlPanelServiceInterface::class, $service);
    }

    public function testGetPanelDataReturnsCompleteShape(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getBulkSettings')->willReturn([
            'Current Season Phase' => 'Regular Season',
            'Allow Trades' => 'Yes',
            'Allow Waiver Moves' => 'No',
            'Show Draft Link' => 'On',
            'Free Agency Notifications' => 'Off',
            'Trivia Mode' => 'Off',
            'Season Ending Year' => '2026',
        ]);
        $stub->method('getSimLengthInDays')->willReturn(5);

        $service = new LeagueControlPanelService($stub);
        $result = $service->getPanelData();

        $this->assertSame('Regular Season', $result['phase']);
        $this->assertSame('Yes', $result['allowTrades']);
        $this->assertSame('No', $result['allowWaivers']);
        $this->assertSame('On', $result['showDraftLink']);
        $this->assertSame('Off', $result['freeAgencyNotifications']);
        $this->assertSame('Off', $result['triviaMode']);
        $this->assertSame(5, $result['simLengthInDays']);
        $this->assertSame(2026, $result['seasonEndingYear']);
    }

    public function testGetPanelDataDefaultsForMissingSettings(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getBulkSettings')->willReturn([]);
        $stub->method('getSimLengthInDays')->willReturn(3);

        $service = new LeagueControlPanelService($stub);
        $result = $service->getPanelData();

        $this->assertSame('Preseason', $result['phase']);
        $this->assertSame('No', $result['allowTrades']);
        $this->assertSame('No', $result['allowWaivers']);
        $this->assertSame('Off', $result['showDraftLink']);
        $this->assertSame('Off', $result['freeAgencyNotifications']);
        $this->assertSame('Off', $result['triviaMode']);
        $this->assertSame(3, $result['simLengthInDays']);
        $this->assertSame(0, $result['seasonEndingYear']);
    }

    public function testGetPanelDataCastsEndingYearToInt(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getBulkSettings')->willReturn([
            'Season Ending Year' => '2025',
        ]);
        $stub->method('getSimLengthInDays')->willReturn(3);

        $service = new LeagueControlPanelService($stub);
        $result = $service->getPanelData();

        $this->assertSame(2025, $result['seasonEndingYear']);
    }
}
