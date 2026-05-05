<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class ContractListEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersAllContracts(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('ContractList');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_plr');
    }
}
