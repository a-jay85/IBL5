<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Rivalries/index.php resolves the logged-in GM's team and stored GM name so
 * the rank tables can highlight their rows. Anonymous visitors skip the lookups.
 *
 * Requires separate processes because is_user() has a static cache.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class RivalriesEntryPointTest extends ModuleEntryPointTestCase
{
    public function testLoggedInGmResolvesTeamAndStoredGmName(): void
    {
        $this->authenticateAs('testgm');
        $this->mockDb->onQuery('SELECT team_name FROM ibl_team_info WHERE gm_username', [['team_name' => 'Metros']]);
        $this->mockDb->onQuery('SELECT teamid FROM ibl_team_info WHERE team_name', [['teamid' => 1]]);
        $this->mockDb->onQuery('SELECT owner_name FROM ibl_team_info WHERE teamid', [['owner_name' => 'gm testuser']]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Rivalries', [], [], array_merge($this->dbGlobals(), ['user' => $GLOBALS['user']]));

        $this->assertStringContainsString('Top franchise rivalries', $output);
        $this->assertQueryExecuted("gm_username = 'testgm'");
        $this->assertQueryExecuted("team_name = 'Metros'");
        // The owner name from the GM's team feeds the stored-spelling lookup.
        $this->assertQueryExecuted("ibl_gm_tenures WHERE gm_display_name = 'gm testuser'");
    }

    public function testAnonymousVisitorSkipsUserLookups(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Rivalries', [], [], array_merge($this->dbGlobals(), ['user' => $GLOBALS['user']]));

        $this->assertStringContainsString('Top franchise rivalries', $output);
        $this->assertQueryNotExecuted('WHERE gm_username');
        $this->assertQueryNotExecuted('SELECT owner_name');
        $this->assertQueryNotExecuted('SELECT gm_display_name FROM ibl_gm_tenures WHERE');
    }
}
