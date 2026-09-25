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
    /** The boosted PageLayout::header() emits exactly one <title>. */
    private const string HEADER_MARKER = '<title>';

    /** Number of buffers PageLayout::footer() popped during the last runAction(). */
    private int $footerCount = 0;

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

    public function testMainEmitsExactlyOneHeaderAndFooter(): void
    {
        $this->arrangeSeason();
        $ballotView = self::createStub(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->method('renderBallotForm')->willReturn('<form id="ballot"></form>');
        $controller = $this->makeController($ballotView, isAdmin: false, results: null);

        $out = $this->runMain($controller, 'testgm');

        $this->assertSame(1, substr_count($out, '<form id="ballot"></form>'));
        $this->assertSame(1, substr_count($out, self::HEADER_MARKER));
        $this->assertSame(1, $this->footerCount);
    }

    public function testSubmitAsgVoteOnValidationFailureRendersErrorsNotBallot(): void
    {
        $this->arrangeSeason();
        $ballotView = self::createMock(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->expects($this->never())->method('renderBallotForm');

        $result = \Voting\SubmissionResult::withErrors(['You cannot select less than FOUR frontcourt players from the Eastern Conference.']);
        $svc = self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class);
        $svc->method('submitAsgVote')->willReturn($result);

        $view = self::createMock(\Voting\Contracts\VotingSubmissionViewInterface::class);
        $view->expects($this->once())->method('renderErrors')->willReturn('<p class="voting-submission-error">ERR</p>');

        $controller = $this->makeController($ballotView, isAdmin: false, results: null, submissionService: $svc, submissionView: $view);
        $this->stubCsrfToken('asg_vote');
        $_POST['ECF'] = ['Only One, Boston Celtics'];

        $out = $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->submitAsgVote('testgm'));

        $this->assertStringContainsString('voting-submission-error', $out);
        $this->assertSame(1, substr_count($out, self::HEADER_MARKER));
        $this->assertSame(1, $this->footerCount);
    }

    public function testSubmitEoyVoteOnValidationFailureRendersErrorsNotBallot(): void
    {
        $this->arrangeSeason();
        $ballotView = self::createMock(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->expects($this->never())->method('renderBallotForm');

        $result = \Voting\SubmissionResult::withErrors(['Sorry, you must vote for three different players for MVP.']);
        $svc = self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class);
        $svc->method('submitEoyVote')->willReturn($result);

        $view = self::createMock(\Voting\Contracts\VotingSubmissionViewInterface::class);
        $view->expects($this->once())->method('renderErrors')->willReturn('<p class="voting-submission-error">ERR</p>');

        $controller = $this->makeController($ballotView, isAdmin: false, results: null, submissionService: $svc, submissionView: $view);
        $this->stubCsrfToken('eoy_vote');
        $_POST['MVP'] = [1 => 'Only One, Boston Celtics'];

        $out = $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->submitEoyVote('testgm'));

        $this->assertStringContainsString('voting-submission-error', $out);
        $this->assertSame(1, substr_count($out, self::HEADER_MARKER));
        $this->assertSame(1, $this->footerCount);
    }

    private function runMain(\Voting\VotingController $controller, string $user): string
    {
        return $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->main($user));
    }

    /**
     * PageLayout::footer() calls ob_end_flush(), so the action needs the same
     * buffering ModuleEntryPointTestCase::runModule() uses: L1 is sacrificial.
     * A second sacrificial level (L0) lets a doubled footer pop a spare buffer
     * instead of the capture one, so footerCount can report it.
     *
     * @param callable(\Voting\VotingController): void $action
     */
    private function runAction(\Voting\VotingController $controller, callable $action): string
    {
        $baseLevel = ob_get_level();
        ob_start(); // L2 — capture
        ob_start(); // L1 — sacrificial
        ob_start(); // L0 — sacrificial spare

        try {
            $action($controller);
        } finally {
            $this->footerCount = $baseLevel + 3 - ob_get_level();
            while (ob_get_level() > $baseLevel + 1) {
                ob_end_flush();
            }
        }

        return (string) ob_get_clean();
    }

    private function stubCsrfToken(string $formName): void
    {
        $_POST['_csrf_token'] = \Security\CsrfGuard::generateRawToken($formName);
    }

    private function makeController(
        \Voting\Contracts\VotingBallotViewInterface $ballotView,
        bool $isAdmin,
        ?\Voting\Contracts\VotingResultsControllerInterface $results,
        ?\Voting\Contracts\VotingSubmissionServiceInterface $submissionService = null,
        ?\Voting\Contracts\VotingSubmissionViewInterface $submissionView = null,
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
            $submissionService ?? self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class),
            $submissionView ?? self::createStub(\Voting\Contracts\VotingSubmissionViewInterface::class),
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
