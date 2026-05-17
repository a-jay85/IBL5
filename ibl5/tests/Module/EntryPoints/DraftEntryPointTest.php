<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * Draft/index.php defines global functions (userinfo, main) that cannot be
 * redeclared, and is_user() has a static cache. Each test runs in a separate
 * process.
 *
 * Unauthenticated path calls loginbox() → die() and is skipped here.
 * Covered by E2E flows.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DraftEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAs('testgm');
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);
        // The ibl_draft_class JOIN query contains 'ibl_team_info', which would otherwise
        // be intercepted by MockDatabase's team-info special handler. Route it explicitly
        // so the renderer receives an empty prospect list rather than stray team rows.
        $this->mockDb->onQuery('ibl_draft_class', []);
    }

    public function testDefaultOpRendersDraftBoard(): void
    {
        $output = $this->runModule('Draft', [], [], [
            'user' => $GLOBALS['user'],
            'op' => '',
        ]);

        $this->assertNotEmpty($output);
    }

    public function testUnknownOpFallsToMain(): void
    {
        $output = $this->runModule('Draft', [], [], [
            'user' => $GLOBALS['user'],
            'op' => 'bogus',
        ]);

        $this->assertNotEmpty($output);
    }
}
