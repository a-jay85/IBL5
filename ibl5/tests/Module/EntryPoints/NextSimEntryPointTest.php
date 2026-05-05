<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * NextSim/index.php uses is_user() with a static cache and loginbox() → die()
 * for unauthenticated access. Each test runs in a separate process.
 *
 * Unauthenticated path calls loginbox() → die() and is skipped here.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class NextSimEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersNextSimSchedule(): void
    {
        $this->authenticateAs('testgm');
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('NextSim', [], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }
}
