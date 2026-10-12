<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * DepthChartEntry/index.php defines global functions (userinfo, main, submit,
 * tabApi, nextSimApi, api) that cannot be redeclared, and is_user() has a
 * static cache. Each test runs in a separate process.
 *
 * Skipped:
 * - op=submit success path (POST + PRG redirect) — covered by E2E flows; the auth and team gates are pinned below.
 * - op=api with JSON body via php://input — runModule() doesn't support
 *   raw-body injection. GET-style api() is tested instead.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DepthChartEntryEntryPointTest extends ModuleEntryPointTestCase
{
    public function testDefaultOpRendersDepthChartForGm(): void
    {
        $this->authenticateAs('testgm');
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['op' => ''], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotSame('', $output);
    }

    public function testOpTabApiReturnsHtml(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['teamid' => '1', 'display' => 'ratings', 'op' => 'tab-api'], [], []);

        $this->assertNotSame('', $output);
    }

    public function testOpNextsimApiReturnsHtml(): void
    {
        $this->authenticateAs('testgm');
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['teamid' => '1', 'op' => 'nextsim-api'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotSame('', $output);
    }

    public function testOpApiWithoutAuthReturnsUnauthorizedJson(): void
    {
        $output = $this->runModule('DepthChartEntry', ['op' => 'api'], [], [
            'user' => '',
        ]);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Unauthorized', $decoded['error']);
    }

    public function testUnknownOpFallsToMain(): void
    {
        $this->authenticateAs('testgm');
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['op' => 'bogus'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotSame('', $output);
    }

    /**
     * Authenticate with a username (authenticateAs() leaves getUsername() null, which
     * TeamIdentityRepository::getTeamnameFromUsername() maps to Free Agents).
     */
    private function authenticateWithUsername(string $username): void
    {
        $authStub = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authStub->method('isAuthenticated')->willReturn(true);
        $authStub->method('isAdmin')->willReturn(false);
        $authStub->method('getUsername')->willReturn($username);
        $authStub->method('getCookieArray')->willReturn([$username, $username, '']);
        $GLOBALS['authService'] = $authStub;
        $GLOBALS['user'] = base64_encode("{$username}:{$username}:");
        $GLOBALS['cookie'] = [$username, $username, ''];
    }

    public function testDefaultOpUnauthenticatedRendersLoginBoxAndSkipsForm(): void
    {
        // loginbox() die()s for a still-unauthenticated session, which would kill the
        // PHPUnit child. The stub answers false to is_user() and true to loginbox()'s
        // own check, so loginbox() returns and main()'s early exit is observable.
        $authStub = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authStub->method('isAuthenticated')->willReturn(false, true);
        $authStub->method('isAdmin')->willReturn(false);
        $authStub->method('getCookieArray')->willReturn(null);
        $authStub->method('getUsername')->willReturn(null);
        $GLOBALS['authService'] = $authStub;
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['op' => ''], [], [
            'user' => '',
        ]);

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('ibl_team_info');
    }

    public function testOpSubmitWithoutAuthRendersInlineErrorAndWritesNothing(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['op' => 'submit'], ['Team_Name' => 'Boston'], [
            'user' => '',
        ]);

        $this->assertStringContainsString('Invalid or expired form submission', $output);
        $this->assertQueryNotExecuted('UPDATE');
        $this->assertQueryNotExecuted('INSERT');
        // The team lookup runs only past the auth gate: replacing !isUser() with false in
        // submit() lets it through, so gm_username is the assertion that discriminates.
        $this->assertQueryNotExecuted('gm_username');
    }

    public function testOpSubmitAuthenticatedWithoutTeamRendersInlineErrorAndWritesNothing(): void
    {
        $this->authenticateWithUsername('teamless');
        // The helper pre-seeds $GLOBALS['cookie']; clear it so the assertion below proves
        // submit() itself decoded the cookie.
        unset($GLOBALS['cookie']);
        $this->mockDb->onQuery('gm_username', []);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['op' => 'submit'], ['Team_Name' => 'Boston'], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertStringContainsString('Invalid or expired form submission', $output);
        $this->assertQueryNotExecuted('UPDATE');
        $this->assertQueryNotExecuted('INSERT');
        $this->assertQueryExecuted('gm_username');
        // cookieDecode() must leave ['teamless','teamless',''] in $GLOBALS['cookie'].
        $this->assertSame(['teamless', 'teamless', ''], $GLOBALS['cookie']);
    }

    public function testOpApiAuthenticatedWithoutTeamReturnsForbiddenJson(): void
    {
        $this->authenticateWithUsername('teamless');
        unset($GLOBALS['cookie']);
        $this->mockDb->onQuery('gm_username', []);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DepthChartEntry', ['op' => 'api', 'action' => 'list'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('No team assigned', $decoded['error']);
        $this->assertSame(['teamless', 'teamless', ''], $GLOBALS['cookie']);
    }

    public function testOpApiWithoutAuthDoesNotDecodeCookie(): void
    {
        unset($GLOBALS['cookie']);

        $output = $this->runModule('DepthChartEntry', ['op' => 'api'], [], [
            'user' => '',
        ]);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Unauthorized', $decoded['error']);
        $this->assertArrayNotHasKey('cookie', $GLOBALS);
    }
}
