<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Characterization test for modules/LeagueControlPanel/index.php.
 *
 * Only the admin path is characterized. The anonymous path ends in
 * HtmxHelper::redirect and the non-admin path in a 403 plus exit, which would
 * kill the PHPUnit process; LeagueControlPanelAdminGateTest pins those.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class LeagueControlPanelEntryPointTest extends ModuleEntryPointTestCase
{
    public function testLeagueControlPanelRendersForAdmin(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->authenticateAsAdmin('testadmin');
        $this->mockDb->onQuery('ibl_settings', [
            ['setting_key' => 'Current Season Phase', 'setting_value' => 'Regular Season'],
            ['setting_key' => 'Current Season Ending Year', 'setting_value' => '2026'],
        ]);

        $output = $this->runModule('LeagueControlPanel', [], [], $this->dbGlobals());

        $this->assertNotSame('', $output);
        $this->assertStringContainsString('League Control Panel', $output);
        $this->assertQueryExecuted('ibl_settings');
    }
}
