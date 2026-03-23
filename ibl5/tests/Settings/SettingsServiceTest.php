<?php

declare(strict_types=1);

namespace Tests\Settings;

use PHPUnit\Framework\TestCase;
use Settings\SettingsService;

use Season\Season;
class SettingsServiceTest extends TestCase
{
    private function createSeasonStub(
        string $allowTrades = '',
        string $allowWaivers = '',
        string $showDraftLink = 'Off',
        string $freeAgencyNotificationsState = ''
    ): Season {
        $season = $this->createStub(Season::class);
        $season->allowTrades = $allowTrades;
        $season->allowWaivers = $allowWaivers;
        $season->showDraftLink = $showDraftLink;
        $season->freeAgencyNotificationsState = $freeAgencyNotificationsState;

        return $season;
    }

    public function testIsTradesAllowedWhenYes(): void
    {
        $service = new SettingsService($this->createSeasonStub(allowTrades: 'Yes'));
        $this->assertTrue($service->isTradesAllowed());
    }

    public function testIsTradesAllowedWhenNo(): void
    {
        $service = new SettingsService($this->createSeasonStub(allowTrades: 'No'));
        $this->assertFalse($service->isTradesAllowed());
    }

    public function testIsWaiverMovesAllowedWhenYes(): void
    {
        $service = new SettingsService($this->createSeasonStub(allowWaivers: 'Yes'));
        $this->assertTrue($service->isWaiverMovesAllowed());
    }

    public function testIsWaiverMovesAllowedWhenNo(): void
    {
        $service = new SettingsService($this->createSeasonStub(allowWaivers: 'No'));
        $this->assertFalse($service->isWaiverMovesAllowed());
    }

    public function testIsDraftLinkShownWhenOn(): void
    {
        $service = new SettingsService($this->createSeasonStub(showDraftLink: 'On'));
        $this->assertTrue($service->isDraftLinkShown());
    }

    public function testIsDraftLinkShownWhenOff(): void
    {
        $service = new SettingsService($this->createSeasonStub(showDraftLink: 'Off'));
        $this->assertFalse($service->isDraftLinkShown());
    }

    public function testIsFreeAgencyNotificationsWhenOn(): void
    {
        $service = new SettingsService($this->createSeasonStub(freeAgencyNotificationsState: 'On'));
        $this->assertTrue($service->isFreeAgencyNotifications());
    }

    public function testIsFreeAgencyNotificationsWhenOff(): void
    {
        $service = new SettingsService($this->createSeasonStub(freeAgencyNotificationsState: 'Off'));
        $this->assertFalse($service->isFreeAgencyNotifications());
    }

    public function testEmptyStringReturnsFalse(): void
    {
        $service = new SettingsService($this->createSeasonStub());
        $this->assertFalse($service->isTradesAllowed());
        $this->assertFalse($service->isWaiverMovesAllowed());
        $this->assertFalse($service->isDraftLinkShown());
        $this->assertFalse($service->isFreeAgencyNotifications());
    }
}
