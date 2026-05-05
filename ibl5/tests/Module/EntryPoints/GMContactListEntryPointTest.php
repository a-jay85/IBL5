<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class GMContactListEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersAllTeamGmContacts(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('GMContactList');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }
}
