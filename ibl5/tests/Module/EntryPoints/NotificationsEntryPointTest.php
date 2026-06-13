<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * Notifications/index.php defines global functions (main, mark_read, …) that
 * cannot be redeclared, so each test runs in a separate process.
 *
 * The unauthenticated path is not tested here because loginbox() calls die(),
 * which terminates the test process (same limitation documented in
 * WaiversEntryPointTest); it is covered by the E2E gating flows instead.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class NotificationsEntryPointTest extends ModuleEntryPointTestCase
{
    public function testLoggedInNonOwnerSeesNoTeamNoticeAndNoList(): void
    {
        $this->authenticateAs('owner');
        // resolveTeamId finds no team for this user → teamId null.
        $this->mockDb->onQuery('ibl_team_info', []);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Notifications', [], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertStringContainsString('own a team', strtolower($output));
        $this->assertStringNotContainsString('notification-list', $output);
    }

    public function testLoggedInOwnerSeesNotificationList(): void
    {
        $this->authenticateAs('owner');
        $this->mockDb->onQuery('ibl_team_info', [['teamid' => 1]]);
        $this->mockDb->onQuery('gm_notifications', [
            [
                'id' => 1,
                'team_id' => 1,
                'type' => 'TRADE_OFFER_RECEIVED',
                'message' => 'Stars sent you a trade offer.',
                'link' => 'modules.php?name=Trading&op=reviewtrade',
                'read_at' => null,
                'created_at' => '2026-06-13 12:00:00',
            ],
        ]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Notifications', [], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertStringContainsString('notification-list', $output);
        $this->assertStringContainsString('Stars sent you a trade offer.', $output);
    }
}
