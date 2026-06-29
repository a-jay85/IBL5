<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use Tests\WideUnit\WideUnitTestCase;
use DepthChartEntry\DepthChartEntrySubmissionHandler;
use League\League;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * Ownership/IDOR proof for DepthChartEntrySubmissionHandler (D-09).
 *
 * The handler derives the authoritative write target from the session username,
 * never from POST `Team_Name`. When the session resolves to no team (null) or to
 * Free Agents, the submission must be refused BEFORE any depth-chart write — the
 * proof is "no UPDATE issued", not merely `success === false`.
 *
 * This file extends WideUnitTestCase for the query log; the existing plain-
 * TestCase failure tests (DepthChartEntrySubmissionHandlerTest) stay untouched.
 *
 * @covers \DepthChartEntry\DepthChartEntrySubmissionHandler
 */
class DepthChartEntrySubmissionHandlerOwnershipTest extends WideUnitTestCase
{
    public function testRejectsNullSessionTeamNoWrite(): void
    {
        $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        // Orphan user: resolves to no team at all.
        $commonRepo->method('getTeamnameFromUsername')->willReturn(null);

        $handler = new DepthChartEntrySubmissionHandler($this->mockDb, $commonRepo);

        // POST names a victim team ('Stars'); it must be ignored entirely.
        $result = $handler->handleSubmission(['Team_Name' => 'Stars'], 'orphan-user');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required team information', $result['errorsHtml']);

        // No victim write: neither the per-player depth chart nor the team
        // history timestamp may be touched.
        $this->assertQueryNotExecuted('UPDATE `ibl_plr`');
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
        $this->assertQueryNotExecuted('UPDATE `ibl_team_info`');
        $this->assertQueryNotExecuted('UPDATE ibl_team_info');
    }

    public function testRejectsFreeAgentsSessionTeamNoWrite(): void
    {
        $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        // Empty/unknown session resolves to Free Agents — not a real team.
        $commonRepo->method('getTeamnameFromUsername')->willReturn(League::FREE_AGENTS_TEAM_NAME);

        $handler = new DepthChartEntrySubmissionHandler($this->mockDb, $commonRepo);

        $result = $handler->handleSubmission(['Team_Name' => 'Stars'], 'fa-user');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required team information', $result['errorsHtml']);

        $this->assertQueryNotExecuted('UPDATE `ibl_plr`');
        $this->assertQueryNotExecuted('UPDATE ibl_plr');
        $this->assertQueryNotExecuted('UPDATE `ibl_team_info`');
        $this->assertQueryNotExecuted('UPDATE ibl_team_info');
    }
}
