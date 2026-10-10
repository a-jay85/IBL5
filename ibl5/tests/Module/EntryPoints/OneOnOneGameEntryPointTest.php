<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Characterization test for modules/OneOnOneGame/index.php.
 *
 * The module declares a global function oneonone(), so each test runs in a
 * separate process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class OneOnOneGameEntryPointTest extends ModuleEntryPointTestCase
{
    public function testOneOnOneGameRendersForAnonymousGet(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('OneOnOneGame', [], [], $this->dbGlobals());

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('One-on-One Match', $output);
        $this->assertQueryExecuted('ibl_plr');
    }
}
