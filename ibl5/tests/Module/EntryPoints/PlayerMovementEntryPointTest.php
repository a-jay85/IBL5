<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class PlayerMovementEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersMovementList(): void
    {
        $this->mockDb->setMockData([
            [
                'pid' => 1,
                'name' => 'Moved Player',
                'old_teamid' => 1,
                'old_team' => 'Team A',
                'new_teamid' => 2,
                'new_team' => 'Team B',
                'old_city' => 'City A',
                'old_color1' => '333333',
                'old_color2' => 'FFFFFF',
                'new_city' => 'City B',
                'new_color1' => '000000',
                'new_color2' => 'FF0000',
            ],
        ]);
        $output = $this->runModule('PlayerMovement');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testRendersWithNoMovements(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('PlayerMovement');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_hist');
    }
}
