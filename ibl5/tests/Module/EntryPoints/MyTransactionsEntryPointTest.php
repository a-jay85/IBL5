<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * MyTransactions/index.php resolves the team server-side and renders a read-only
 * ledger. It uses is_user() (static-cached), so tests run in separate processes.
 *
 * The unauthenticated path is NOT tested here: the auth gate calls
 * NukeCompat::loginBox() → loginbox() → die(), which terminates the test process
 * (same reason Trading/FreeAgency entry-point tests skip it). Logged-out refusal
 * is covered by the E2E flow; teamless refusal is covered both here
 * (testTeamlessUserSeesNoTeamState) and at the service level.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class MyTransactionsEntryPointTest extends ModuleEntryPointTestCase
{
    /**
     * Authenticate as a user whose username also resolves via getUsername().
     * The base authenticateAs() does not mock getUsername(), which the module
     * relies on for identity, so this overrides the auth stub accordingly.
     */
    private function authenticateWithUsername(string $username): void
    {
        $authStub = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authStub->method('isAuthenticated')->willReturn(true);
        $authStub->method('isAdmin')->willReturn(false);
        $authStub->method('getCookieArray')->willReturn([$username, $username, '']);
        $authStub->method('getUsername')->willReturn($username);
        $GLOBALS['authService'] = $authStub;
        $GLOBALS['user'] = base64_encode("{$username}:{$username}:");
        $GLOBALS['cookie'] = [$username, $username, ''];
    }

    private function seedMetrosIdentity(): void
    {
        // gm_username lookup -> Metros; team_name -> teamid lookup -> 1.
        $this->mockDb->onQuery('gm_username', [['team_name' => 'Metros']]);
        $this->mockDb->onQuery('SELECT teamid FROM ibl_team_info', [['teamid' => 1]]);
        // Empty offer/bid sources keep the test focused on the ledger query.
        $this->mockDb->onQuery('ibl_trade_info', []);
        $this->mockDb->onQuery('ibl_fa_offers', []);
    }

    public function testOwnerSeesOwnTeamLedger(): void
    {
        $this->authenticateWithUsername('testgm');
        $this->seedMetrosIdentity();
        $this->mockDb->onQuery('nuke_stories', [
            ['sid' => '1', 'catid' => '1', 'title' => 'Metros sign Player One', 'time' => '2025-01-15 12:00:00'],
        ]);

        $output = $this->runModule('MyTransactions', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        self::assertStringContainsString('My Team Transactions', $output);
        self::assertStringContainsString('Metros sign Player One', $output);
        $this->assertQueryExecuted('nuke_stories');
    }

    /**
     * Security: a GM passing ?teamid=2 (Stars) must still see only their own
     * (Metros) ledger. The ledger query must bind the resolved team name, never
     * the request parameter. This test FAILS if the module reads $_GET['teamid'].
     */
    public function testIgnoresTeamidParam(): void
    {
        $this->authenticateWithUsername('testgm');
        $this->seedMetrosIdentity();
        $this->mockDb->onQuery('nuke_stories', []);

        $this->runModule('MyTransactions', ['teamid' => '2'], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        // The REGEXP ledger query binds the resolved team name (interpolated by the
        // mock prepared statement). It must carry Metros, not the param-derived Stars.
        $this->assertQueryExecuted("'Metros', '[[:>:]]'");
        $this->assertQueryNotExecuted('Stars');
    }

    /**
     * A logged-in user with no GM link sees the no-team state and triggers NO
     * ledger query (clean refusal, no die()).
     */
    public function testTeamlessUserSeesNoTeamState(): void
    {
        $this->authenticateWithUsername('orphan');
        // gm_username lookup returns no row -> null team.
        $this->mockDb->onQuery('gm_username', []);

        $output = $this->runModule('MyTransactions', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        self::assertStringContainsString('not assigned a team', $output);
        $this->assertQueryNotExecuted('nuke_stories');
    }
}
