<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class RecordHoldersEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);
        // Cold cache → single-flight rebuild: the request must win GET_LOCK to build inline.
        $this->mockDb->onQuery('GET_LOCK', [['got' => 1]]);
    }

    public function testRendersAllThreeRecordCategories(): void
    {
        $output = $this->runModule('RecordHolders');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Regular Season', $output);
        $this->assertStringContainsString('Playoffs', $output);
        $this->assertStringContainsString('H.E.A.T.', $output);
    }

    public function testHandlesEmptyRecords(): void
    {
        $output = $this->runModule('RecordHolders');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }
}
