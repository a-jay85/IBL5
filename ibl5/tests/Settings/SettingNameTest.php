<?php

declare(strict_types=1);

namespace Tests\Settings;

use PHPUnit\Framework\TestCase;
use Settings\SettingName;

class SettingNameTest extends TestCase
{
    public function testYesNoSettings(): void
    {
        $this->assertTrue(SettingName::AllowTrades->usesYesNo());
        $this->assertTrue(SettingName::AllowWaiverMoves->usesYesNo());
        $this->assertTrue(SettingName::ASGVoting->usesYesNo());
        $this->assertTrue(SettingName::EOYVoting->usesYesNo());
        $this->assertTrue(SettingName::DraftOrderFinalized->usesYesNo());
    }

    public function testOnOffSettings(): void
    {
        $this->assertFalse(SettingName::ShowDraftLink->usesYesNo());
        $this->assertFalse(SettingName::TriviaMode->usesYesNo());
        $this->assertFalse(SettingName::FreeAgencyNotifications->usesYesNo());
    }

    public function testEnabledValues(): void
    {
        $this->assertSame('Yes', SettingName::AllowTrades->enabledValue());
        $this->assertSame('On', SettingName::ShowDraftLink->enabledValue());
        $this->assertSame('On', SettingName::TriviaMode->enabledValue());
    }

    public function testDisabledValues(): void
    {
        $this->assertSame('No', SettingName::AllowTrades->disabledValue());
        $this->assertSame('Off', SettingName::ShowDraftLink->disabledValue());
        $this->assertSame('Off', SettingName::FreeAgencyNotifications->disabledValue());
    }

    public function testSettingNamesMatchDatabaseValues(): void
    {
        $this->assertSame('Allow Trades', SettingName::AllowTrades->value);
        $this->assertSame('Allow Waiver Moves', SettingName::AllowWaiverMoves->value);
        $this->assertSame('Show Draft Link', SettingName::ShowDraftLink->value);
        $this->assertSame('Trivia Mode', SettingName::TriviaMode->value);
        $this->assertSame('Free Agency Notifications', SettingName::FreeAgencyNotifications->value);
        $this->assertSame('Sim Length in Days', SettingName::SimLengthInDays->value);
    }
}
