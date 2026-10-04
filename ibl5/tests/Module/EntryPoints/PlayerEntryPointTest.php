<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * Player/index.php defines global functions (showpage, negotiate, rookieoption,
 * processrookieoption) that cannot be redeclared, so each test runs in a
 * separate process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PlayerEntryPointTest extends ModuleEntryPointTestCase
{
    public function testMissingPaRendersNoPlayerSelectedNotice(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Player');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('No player selected', $output);
    }

    public function testInvalidPaRendersNoPlayerSelectedNotice(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Player', ['pa' => 'bogus']);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('No player selected', $output);
    }

    /**
     * @param array<string, mixed> $playerOverrides
     */
    private function seedShowpageMocks(array $playerOverrides = []): void
    {
        $player = TestDataFactory::createPlayer(array_merge(
            ['pid' => 1, 'name' => 'Test Player', 'teamname' => 'Test Team', 'color1' => 'FF0000', 'color2' => '000000'],
            $playerOverrides,
        ));

        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([$player]);

        // The PlayerRepository JOIN query contains both ibl_plr and ibl_team_info,
        // so we must route it via onQuery before the team-info special handler fires.
        $this->mockDb->onQuery('FROM ibl_plr', [$player]);
        $this->mockDb->onQuery('ibl_hist', []);
        $this->mockDb->onQuery('ibl_box_scores', []);
        $this->mockDb->onQuery('ibl_sim_dates', []);
        $this->mockDb->onQuery('ibl_playoff_career_totals', []);
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_awards', []);
        $this->mockDb->onQuery('ibl_draft', []);
        $this->mockDb->onQuery('COUNT', [['total' => 0]]);
    }

    public function testShowpageDispatchesAndQueriesPlayer(): void
    {
        $this->seedShowpageMocks();

        $output = $this->runModule(
            'Player',
            ['pa' => 'showpage', 'pid' => '1'],
        );

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testShowpageWithNonNumericPidCastsToZero(): void
    {
        $this->seedShowpageMocks(['pid' => 0, 'name' => 'Unknown']);

        $output = $this->runModule(
            'Player',
            ['pa' => 'showpage', 'pid' => 'garbage'],
        );

        $this->assertQueryExecuted('ibl_plr');
    }

    public function testNegotiateRendersNegotiation(): void
    {
        $player = TestDataFactory::createPlayer(['pid' => 1]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([$player]);
        // PlayerRepository::loadByID JOINs ibl_team_info; route it before the
        // team-info special handler intercepts the response.
        $this->mockDb->onQuery('FROM ibl_plr', [$player]);
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);

        $output = $this->runModule('Player', ['pa' => 'negotiate', 'pid' => '1']);

        $this->assertNotEmpty($output);
    }

    public function testNegotiateWithAuthRendersNegotiation(): void
    {
        $this->authenticateAs('testuser');
        $player = TestDataFactory::createPlayer(['pid' => 1]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([$player]);
        $this->mockDb->onQuery('FROM ibl_plr', [$player]);
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);

        $output = $this->runModule('Player', ['pa' => 'negotiate', 'pid' => '1']);

        $this->assertNotEmpty($output);
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

    public function testProcessRookieOptionUnauthenticatedRendersLoginBox(): void
    {
        // loginbox() die()s for a still-unauthenticated session, which would kill the
        // PHPUnit child. The stub answers false to is_user() and true to loginbox()'s
        // own check, so loginbox() returns and the handler's early return is observable.
        $authStub = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authStub->method('isAuthenticated')->willReturn(false, true);
        $authStub->method('isAdmin')->willReturn(false);
        $authStub->method('getCookieArray')->willReturn(null);
        $GLOBALS['authService'] = $authStub;

        $output = $this->runModule(
            'Player',
            ['pa' => 'processrookieoption'],
            ['teamname' => 'Boston', 'playerID' => '1'],
        );

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('UPDATE');
        $this->assertQueryNotExecuted('ibl_team_info');
    }

    private function seedTeamlessUser(): void
    {
        $this->authenticateWithUsername('testuser');
        $player = TestDataFactory::createPlayer(['pid' => 1]);
        $this->mockDb->onQuery('gm_username', []);
        $this->mockDb->onQuery('FROM ibl_plr', [$player]);
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->setMockData([$player]);
    }

    public function testNegotiateTeamlessUserRendersNoTeamError(): void
    {
        $this->seedTeamlessUser();

        $output = $this->runModule('Player', ['pa' => 'negotiate', 'pid' => '1']);

        $this->assertStringContainsString('ibl-alert ibl-alert--error', $output);
        $this->assertStringContainsString('You do not have a team assigned.', $output);
        $this->assertStringContainsString('Go Back', $output);
        $this->assertStringNotContainsString('<form', $output);
    }

    public function testRookieOptionTeamlessUserRendersNoTeamError(): void
    {
        $this->seedTeamlessUser();

        $output = $this->runModule('Player', ['pa' => 'rookieoption', 'pid' => '1']);

        $this->assertStringContainsString('ibl-alert ibl-alert--error', $output);
        $this->assertStringContainsString('You do not have a team assigned.', $output);
        $this->assertStringContainsString('Go Back', $output);
        $this->assertStringNotContainsString('<form', $output);
    }

    /**
     * @param array<string, mixed> $playerOverrides
     */
    private function seedRookieOptionUser(string $userTeam, array $playerOverrides = []): void
    {
        $this->authenticateWithUsername('testuser');
        $player = TestDataFactory::createPlayer(array_merge(
            ['pid' => 1, 'name' => 'Test Player', 'teamname' => 'Test Team'],
            $playerOverrides,
        ));
        $this->mockDb->onQuery('gm_username', [['team_name' => $userTeam]]);
        $this->mockDb->onQuery('FROM ibl_plr', [$player]);
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->setMockData([$player]);
    }

    public function testRookieOptionOwnershipFailureRendersError(): void
    {
        $this->seedRookieOptionUser('Other Team');

        $output = $this->runModule('Player', ['pa' => 'rookieoption', 'pid' => '1']);

        $this->assertStringContainsString('ibl-alert ibl-alert--error', $output);
        $this->assertStringContainsString('is not on your team.', $output);
        $this->assertStringContainsString('Go Back', $output);
        $this->assertStringNotContainsString('<form', $output);
    }

    public function testRookieOptionEligibilityFailureRendersError(): void
    {
        // The Season test alias pins the phase to "Regular Season", so no player is
        // rookie-option eligible and the eligibility branch always fires here.
        $this->seedRookieOptionUser('Test Team');

        $output = $this->runModule('Player', ['pa' => 'rookieoption', 'pid' => '1']);

        $this->assertStringContainsString('ibl-alert ibl-alert--error', $output);
        $this->assertStringContainsString('is not eligible for a rookie option.', $output);
        $this->assertStringContainsString('Go Back', $output);
        $this->assertStringNotContainsString('<form', $output);
    }

    public function testShowpageResolvesUuidToPlayerId(): void
    {
        $uuid = '123e4567-e89b-42d3-a456-426614174000';
        $this->mockDb->onQuery('WHERE uuid', [['pid' => 42]]);
        $this->seedShowpageMocks(['pid' => 42, 'name' => 'Uuid Player']);

        $output = $this->runModule('Player', ['pa' => 'showpage', 'pid' => $uuid]);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted("uuid = '" . $uuid . "'");
        $this->assertQueryExecuted('p.pid = 42');
    }

    public function testShowpageUnknownUuidFallsBackToZeroCast(): void
    {
        $uuid = '123e4567-e89b-42d3-a456-426614174000';
        $this->mockDb->onQuery('WHERE uuid', []);
        $this->seedShowpageMocks(['pid' => 0, 'name' => 'Unknown']);

        $output = $this->runModule('Player', ['pa' => 'showpage', 'pid' => $uuid]);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted("uuid = '" . $uuid . "'");
        $this->assertQueryExecuted('p.pid = 0');
    }

    public function testModuleDeclaresNoGlobalFunctions(): void
    {
        $this->mockDb->setMockData([]);
        $this->runModule('Player', []);

        $this->assertFalse(function_exists('showpage'));
        $this->assertFalse(function_exists('negotiate'));
        $this->assertFalse(function_exists('rookieoption'));
        $this->assertFalse(function_exists('processrookieoption'));
    }
}
