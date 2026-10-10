<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Characterization test for modules/HeadToHeadRecords/index.php.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class HeadToHeadRecordsEntryPointTest extends ModuleEntryPointTestCase
{
    public function testHeadToHeadRecordsRendersForAnonymousGet(): void
    {
        $this->mockDb->setMockData([]);
        $_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir();

        $output = $this->runModule('HeadToHeadRecords', [], [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Head-to-Head Records', $output);
    }
}
