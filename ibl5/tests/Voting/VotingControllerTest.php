<?php

declare(strict_types=1);

namespace Tests\Voting;

use Tests\Module\EntryPoints\ModuleEntryPointTestCase;

/**
 * Unit coverage for the admin gate around the Voting Results expander.
 *
 * Extends ModuleEntryPointTestCase for the theme stubs, the boosted PageLayout
 * and $this->mockDb; showBallot() constructs Season and League from the
 * connection, so the ibl_settings / ibl_sim_dates arrangement is copied from
 * VotingResultsEntryPointTest.
 */
class VotingControllerTest extends ModuleEntryPointTestCase
{
    public function testMainRendersResultsExpanderForAdmin(): void
    {
        $this->arrangeSeason();

        $ballotView = self::createMock(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->method('renderBallotForm')->willReturn('<form></form>');
        $ballotView->expects($this->once())
            ->method('renderResultsExpander')
            ->with('<p>RESULTS</p>')
            ->willReturn('<div id="Results"></div>');

        $results = self::createMock(\Voting\Contracts\VotingResultsControllerInterface::class);
        $results->expects($this->once())->method('render')->willReturn('<p>RESULTS</p>');

        $controller = $this->makeController($ballotView, isAdmin: true, results: $results);

        $this->assertStringContainsString('<div id="Results"></div>', $this->runMain($controller, 'testadmin'));
    }

    public function testMainSkipsResultsForNonAdminEvenWhenResultsControllerInjected(): void
    {
        $this->arrangeSeason();

        $ballotView = self::createMock(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->method('renderBallotForm')->willReturn('<form></form>');
        $ballotView->expects($this->never())->method('renderResultsExpander');

        $results = self::createMock(\Voting\Contracts\VotingResultsControllerInterface::class);
        $results->expects($this->never())->method('render');

        $controller = $this->makeController($ballotView, isAdmin: false, results: $results);

        $this->assertStringNotContainsString('id="Results"', $this->runMain($controller, 'testgm'));
    }

    public function testMainSkipsResultsWhenNoResultsControllerInjected(): void
    {
        $this->arrangeSeason();

        $ballotView = self::createMock(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->method('renderBallotForm')->willReturn('<form></form>');
        $ballotView->expects($this->never())->method('renderResultsExpander');

        // Ninth argument omitted: an admin still gets no expander and no error.
        $controller = $this->makeController($ballotView, isAdmin: true, results: null);

        $this->assertStringNotContainsString('id="Results"', $this->runMain($controller, 'testadmin'));
    }

    /**
     * PageLayout::footer() calls ob_end_flush(), so main() needs the same double
     * buffering ModuleEntryPointTestCase::runModule() uses: L1 is sacrificial.
     */
    private function runMain(\Voting\VotingController $controller, string $user): string
    {
        $baseLevel = ob_get_level();
        ob_start(); // L2 — capture
        ob_start(); // L1 — sacrificial

        try {
            $controller->main($user);
        } finally {
            while (ob_get_level() > $baseLevel + 1) {
                ob_end_flush();
            }
        }

        return (string) ob_get_clean();
    }

    private function makeController(
        \Voting\Contracts\VotingBallotViewInterface $ballotView,
        bool $isAdmin,
        ?\Voting\Contracts\VotingResultsControllerInterface $results,
    ): \Voting\VotingController {
        $ballotService = self::createStub(\Voting\Contracts\VotingBallotServiceInterface::class);
        $ballotService->method('getBallotData')->willReturn([]);

        $nukeCompat = self::createStub(\Utilities\NukeCompat::class);
        $nukeCompat->method('isUser')->willReturn(true);

        $authService = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authService->method('isAdmin')->willReturn($isAdmin);
        $authService->method('getUsername')->willReturn('tester');

        $teamIdentity = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $teamIdentity->method('getTeamnameFromUsername')->willReturn('Test Team');
        $teamIdentity->method('getTidFromTeamname')->willReturn(1);

        return new \Voting\VotingController(
            $GLOBALS['mysqli_db'],
            $ballotService,
            $ballotView,
            self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class),
            self::createStub(\Voting\Contracts\VotingSubmissionViewInterface::class),
            $nukeCompat,
            $authService,
            $teamIdentity,
            $results,
        );
    }

    private function arrangeSeason(): void
    {
        $this->mockDb->onQuery('ibl_settings', [
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['name' => 'Current Season Ending Year', 'value' => '2026'],
            ['name' => 'Allow Trades', 'value' => 'Yes'],
            ['name' => 'Allow Waiver Moves', 'value' => 'Yes'],
        ]);
        $this->mockDb->onQuery('ibl_sim_dates', [
            ['sim' => 1, 'start_date' => '2025-11-01', 'end_date' => '2025-11-07'],
        ]);
        $this->mockDb->setMockData([]);
    }
}
