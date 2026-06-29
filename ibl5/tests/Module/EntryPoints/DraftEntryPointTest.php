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
        $output = $this->runModule('Draft', ['op' => ''], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    public function testUnknownOpFallsToMain(): void
    {
        $output = $this->runModule('Draft', ['op' => 'bogus'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    /**
     * Route the gm_username → team_name lookup so the ownership gate sees a
     * controlled session team. onQuery patterns are checked before the
     * team-info special handler in MockDatabase::sql_query().
     */
    private function setSessionTeam(?string $teamName): void
    {
        $rows = $teamName === null ? [] : [['team_name' => $teamName]];
        $this->mockDb->onQuery('gm_username', $rows);
    }

    /**
     * @param array<string, string> $extraPost
     * @return string
     */
    private function runDraftSelect(string $postTeamName, array $extraPost = []): string
    {
        $token = \Security\CsrfGuard::generateRawToken('draft_selection');

        return $this->runModule('Draft', ['op' => 'select'], array_merge([
            '_csrf_token' => $token,
            'teamname' => $postTeamName,
            'player' => 'Some Prospect',
            'draft_round' => '1',
            'draft_pick' => '1',
        ], $extraPost), [
            'user' => $GLOBALS['user'],
        ]);
    }

    public function testOwnerWithValidTokenReachesDraftWrite(): void
    {
        // Session user owns Metros; POST matches → ownership passes and the
        // handler runs, touching ibl_draft (getCurrentDraftSelection at minimum).
        $this->setSessionTeam('Metros');

        $this->runDraftSelect('Metros');

        $this->assertQueryExecuted('ibl_draft');
    }

    public function testIdorDifferentTeamIsRefusedNoWrite(): void
    {
        // Session user owns Metros but POSTs another team's name → rejected
        // before any draft mutation.
        $this->setSessionTeam('Metros');

        $output = $this->runDraftSelect('Stars');

        $this->assertStringContainsString('You can only make selections for your own team.', $output);
        $this->assertQueryNotExecuted('ibl_draft');
        $this->assertQueryNotExecuted('ibl_draft_class');
    }

    public function testFreeAgentsSessionIsRefusedNoWrite(): void
    {
        // Session resolves to no team (gm_username lookup empty → null) → rejected
        // even though the POST carries a real team name.
        $this->setSessionTeam(null);

        $output = $this->runDraftSelect('Metros');

        $this->assertStringContainsString('You can only make selections for your own team.', $output);
        $this->assertQueryNotExecuted('ibl_draft');
    }

    public function testForgedTokenIsRefusedNoWrite(): void
    {
        $this->setSessionTeam('Metros');

        $output = $this->runModule('Draft', ['op' => 'select'], [
            '_csrf_token' => 'deadbeef',
            'teamname' => 'Metros',
            'player' => 'Some Prospect',
            'draft_round' => '1',
            'draft_pick' => '1',
        ], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertStringContainsString('Invalid or expired form submission', $output);
        $this->assertQueryNotExecuted('ibl_draft');
    }
}
