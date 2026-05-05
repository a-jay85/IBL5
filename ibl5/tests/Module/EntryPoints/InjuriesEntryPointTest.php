<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Tests\WideUnit\Mocks\TestDataFactory;

class InjuriesEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersInjuredPlayersList(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([
            TestDataFactory::createPlayer(['injured' => 3, 'injdays' => 3]),
        ]);
        $output = $this->runModule('Injuries');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testRendersWithNoInjuredPlayers(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Injuries');

        $this->assertNotEmpty($output);
    }
}
