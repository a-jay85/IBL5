<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * NextSim/index.php uses is_user() with a static cache and loginbox() → die()
 * for unauthenticated access. Each test runs in a separate process.
 *
 * Unauthenticated path calls loginbox() → die(); the sentinel auth stub
 * (authenticateWithSentinel) covers it by throwing from loginbox()'s auth check.
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

        $this->assertNotSame('', $output);
    }

    public function testUnauthenticatedReachesLoginBoxBeforeSchedule(): void
    {
        // Call 1 is the gate's is_user() (answers false); call 2 is loginbox()'s own check.
        $this->authenticateWithSentinel('', false, 'isAuthenticated', 2);
        $this->assertSentinelThrown('isAuthenticated', function (): void {
            $this->runModule('NextSim', [], [], ['user' => $GLOBALS['user']]);
        });
    }
}
