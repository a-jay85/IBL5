<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\LeagueContext;
use Module\ModuleAccessControl;
use PHPUnit\Framework\Attributes\Group;
use Season\Season;

/**
 * Proves the real ibl_settings 'Trivia Mode' row reaches ModuleAccessControl's
 * access decision. ModuleAccessControlTest stubs the database, so only this test
 * catches a reader that selects or indexes the wrong settings column.
 */
#[Group('database')]
class ModuleAccessControlSettingsTest extends DatabaseTestCase
{
    public function testTriviaModeOnInSettingsHidesPlayerModule(): void
    {
        $this->db->query(
            "REPLACE INTO ibl_settings (setting_key, setting_value, league)"
            . " VALUES ('Trivia Mode', 'On', 'ibl')"
        );

        $control = $this->createAccessControl();

        self::assertFalse($control->isModuleAccessible('Player'));
    }

    public function testMissingTriviaModeSettingLeavesPlayerModuleAccessible(): void
    {
        $this->db->query(
            "DELETE FROM ibl_settings WHERE setting_key = 'Trivia Mode' AND league = 'ibl'"
        );

        $control = $this->createAccessControl();

        self::assertTrue($control->isModuleAccessible('Player'));
    }

    private function createAccessControl(): ModuleAccessControl
    {
        $season = self::createStub(Season::class);
        $season->phase = 'Regular Season';

        $leagueContext = self::createStub(LeagueContext::class);
        $leagueContext->method('isModuleEnabled')->willReturn(true);

        return new ModuleAccessControl($season, $leagueContext, $this->db);
    }
}
