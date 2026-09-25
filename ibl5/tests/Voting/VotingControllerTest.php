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

    /**
     * Flipped in the POST-redisplay change: a failed submission now re-renders the ballot.
     */
    public function testSubmitAsgVoteOnValidationFailureRendersErrorsNotBallot(): void
    {
        $this->arrangeSeason();
        $ballotView = self::createMock(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->expects($this->once())->method('renderBallotForm')
            ->with(
                'modules.php?name=Voting&op=submit_asg',
                'Test Team',
                1,
                'Regular Season',
                self::anything(),
                ['ECF' => ['Only One, Boston Celtics'], 'ECB' => [], 'WCF' => [], 'WCB' => []],
            )
            ->willReturn('<form id="ballot"></form>');

        $result = \Voting\SubmissionResult::withErrors(['You cannot select less than FOUR frontcourt players from the Eastern Conference.']);
        $svc = self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class);
        $svc->method('submitAsgVote')->willReturn($result);

        $view = self::createMock(\Voting\Contracts\VotingSubmissionViewInterface::class);
        $view->expects($this->never())->method('renderErrors');

        $controller = $this->makeController($ballotView, isAdmin: false, results: null, submissionService: $svc, submissionView: $view);
        $this->stubCsrfToken('asg_vote');
        $_POST['ECF'] = ['Only One, Boston Celtics'];

        $out = $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->submitAsgVote('testgm'));

        $this->assertStringContainsString('voting-submission-error', $out);
        $this->assertSame(1, substr_count($out, self::HEADER_MARKER));
        $this->assertSame(1, $this->footerCount);
        $this->assertStringContainsString('<form id="ballot"></form>', $out);
    }

    /**
     * Flipped in the POST-redisplay change: a failed submission now re-renders the ballot.
     */
    public function testSubmitEoyVoteOnValidationFailureRendersErrorsNotBallot(): void
    {
        $this->arrangeSeason();
        $ballotView = self::createMock(\Voting\Contracts\VotingBallotViewInterface::class);
        // Season class-alias pins phase to 'Regular Season', so form action and phase cannot be asserted.
        $ballotView->expects($this->once())->method('renderBallotForm')
            ->with(
                self::anything(),
                'Test Team',
                1,
                self::anything(),
                self::anything(),
                ['MVP' => [1 => 'Only One, Boston Celtics', 2 => '', 3 => ''], 'Six' => [1 => '', 2 => '', 3 => ''], 'ROY' => [1 => '', 2 => '', 3 => ''], 'GM' => [1 => '', 2 => '', 3 => '']],
            )
            ->willReturn('<form id="ballot"></form>');

        $result = \Voting\SubmissionResult::withErrors(['Sorry, you must vote for three different players for MVP.']);
        $svc = self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class);
        $svc->method('submitEoyVote')->willReturn($result);

        $view = self::createMock(\Voting\Contracts\VotingSubmissionViewInterface::class);
        $view->expects($this->never())->method('renderErrors');

        $controller = $this->makeController($ballotView, isAdmin: false, results: null, submissionService: $svc, submissionView: $view);
        $this->stubCsrfToken('eoy_vote');
        $_POST['MVP'] = [1 => 'Only One, Boston Celtics'];

        $out = $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->submitEoyVote('testgm'));

        $this->assertStringContainsString('voting-submission-error', $out);
        $this->assertSame(1, substr_count($out, self::HEADER_MARKER));
        $this->assertSame(1, $this->footerCount);
        $this->assertStringContainsString('<form id="ballot"></form>', $out);
    }

    public function testSubmitAsgVoteOnValidationFailureRedisplaysBallotWithPicksChecked(): void
    {
        $this->arrangeSeason();
        // Season class-alias pins phase to 'Regular Season'; ECF category is used because
        // submitAsgVote() only reads ECF/ECB/WCF/WCB from $_POST — $_POST['GM'] is dropped.
        $stats1 = \Player\Stats\PlayerStats::withPlrRow(
            $GLOBALS['mysqli_db'],
            ['pid' => 1, 'name' => 'Ann Lee', 'pos' => 'F'],
        );
        $stats2 = \Player\Stats\PlayerStats::withPlrRow(
            $GLOBALS['mysqli_db'],
            ['pid' => 2, 'name' => 'Bob Ray', 'pos' => 'F'],
        );
        $ecfCategories = [[
            'code' => 'ECF',
            'title' => 'Eastern Conference Frontcourt',
            'instruction' => 'Select FOUR.',
            'candidates' => [
                ['name' => 'Ann Lee', 'teamName' => 'Team A', 'stats' => $stats1, 'playerID' => 1],
                ['name' => 'Bob Ray', 'teamName' => 'Team B', 'stats' => $stats2, 'playerID' => 2],
            ],
        ]];

        $ballotSvc = self::createStub(\Voting\Contracts\VotingBallotServiceInterface::class);
        $ballotSvc->method('getBallotData')->willReturn($ecfCategories);

        $result = \Voting\SubmissionResult::withErrors(['You cannot select less than FOUR frontcourt players from the Eastern Conference.']);
        $svc = self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class);
        $svc->method('submitAsgVote')->willReturn($result);

        $controller = $this->makeController(
            $this->realBallotView(),
            isAdmin: false,
            results: null,
            submissionService: $svc,
            ballotService: $ballotSvc,
        );
        $this->stubCsrfToken('asg_vote');
        $_POST['ECF'] = ['Ann Lee, Team A'];

        $out = $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->submitAsgVote('testgm'));

        $this->assertStringContainsString('voting-submission-error', $out);
        $this->assertSame(1, substr_count($out, 'name="_csrf_token"'));
        $this->assertStringContainsString('value="Ann Lee, Team A" checked', $out);
        $this->assertStringContainsString('value="Bob Ray, Team B">', $out);
        $this->assertLessThan(strpos($out, '<form'), strpos($out, 'voting-submission-error'));
        $this->assertLessThan(strpos($out, 'voting-submission-error'), strpos($out, '</h1>'));
        $this->assertSame(1, substr_count($out, self::HEADER_MARKER));
        $this->assertSame(1, $this->footerCount);
    }

    public function testSubmitEoyVoteOnValidationFailureRedisplaysBallotWithPicksChecked(): void
    {
        $this->arrangeSeason();
        // Season class-alias pins phase to 'Regular Season'; override view to force EOY ('Playoffs') rendering.
        $ballotView = new class extends \Voting\VotingBallotView {
            /**
             * @param string $formAction
             * @param string $voterTeamName
             * @param int $teamid
             * @param string $phase
             * @param list<array{code: string, title: string, instruction: string, candidates: list<array<string, mixed>>}> $categories
             * @param array<string, array<int, string>> $selections
             */
            public function renderBallotForm(string $formAction, string $voterTeamName, int $teamid, string $phase, array $categories, array $selections = []): string
            {
                return parent::renderBallotForm($formAction, $voterTeamName, $teamid, 'Playoffs', $categories, $selections);
            }
        };

        $ballotSvc = self::createStub(\Voting\Contracts\VotingBallotServiceInterface::class);
        $ballotSvc->method('getBallotData')->willReturn($this->twoGmCandidates());

        $result = \Voting\SubmissionResult::withErrors(['Sorry, you must vote for three different players for MVP.']);
        $svc = self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class);
        $svc->method('submitEoyVote')->willReturn($result);

        $controller = $this->makeController(
            $ballotView,
            isAdmin: false,
            results: null,
            submissionService: $svc,
            ballotService: $ballotSvc,
        );
        $this->stubCsrfToken('eoy_vote');
        $_POST['GM'] = [2 => 'Ann Lee, Team A'];

        $out = $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->submitEoyVote('testgm'));

        $this->assertStringContainsString('voting-submission-error', $out);
        $this->assertSame(1, substr_count($out, 'name="_csrf_token"'));
        $this->assertStringContainsString('name="GM[2]" value="Ann Lee, Team A" checked', $out);
        $this->assertStringContainsString('name="GM[1]" value="Ann Lee, Team A">', $out);
        $this->assertSame(1, substr_count($out, self::HEADER_MARKER));
        $this->assertSame(1, $this->footerCount);
    }

    public function testSubmitAsgVoteRedisplayEscapesErrorText(): void
    {
        $this->arrangeSeason();
        $ballotView = self::createStub(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->method('renderBallotForm')->willReturn('<form></form>');

        $result = \Voting\SubmissionResult::withErrors(['Bad <b>pick</b>']);
        $svc = self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class);
        $svc->method('submitAsgVote')->willReturn($result);

        $controller = $this->makeController($ballotView, isAdmin: false, results: null, submissionService: $svc);
        $this->stubCsrfToken('asg_vote');
        $_POST['ECF'] = ['Only One, Boston Celtics'];

        $out = $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->submitAsgVote('testgm'));

        $this->assertStringContainsString('Bad &lt;b&gt;pick&lt;/b&gt;', $out);
        $this->assertStringNotContainsString('<b>pick</b>', $out);
    }

    public function testSubmitAsgVoteWithInvalidCsrfTokenDoesNotRedisplayBallot(): void
    {
        $this->arrangeSeason();
        $ballotView = self::createMock(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->expects($this->never())->method('renderBallotForm');

        $controller = $this->makeController($ballotView, isAdmin: false, results: null);
        // Do NOT call stubCsrfToken() — no valid token in $_POST
        $_POST['ECF'] = ['Only One, Boston Celtics'];

        $out = $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->submitAsgVote('testgm'));

        $this->assertStringContainsString('Invalid or expired form submission', $out);
        $this->assertStringContainsString('Return to the ballot', $out);
        $this->assertStringNotContainsString('voting-submission-error', $out);
    }

    public function testSubmitAsgVoteOnSuccessDoesNotRedisplayBallot(): void
    {
        $this->arrangeSeason();
        $ballotView = self::createMock(\Voting\Contracts\VotingBallotViewInterface::class);
        $ballotView->expects($this->never())->method('renderBallotForm');

        $result = \Voting\SubmissionResult::success();
        $svc = self::createStub(\Voting\Contracts\VotingSubmissionServiceInterface::class);
        $svc->method('submitAsgVote')->willReturn($result);

        $submissionView = self::createStub(\Voting\Contracts\VotingSubmissionViewInterface::class);
        $submissionView->method('renderAsgConfirmation')->willReturn('<p class="voting-submission-success">ok</p>');

        $controller = $this->makeController($ballotView, isAdmin: false, results: null, submissionService: $svc, submissionView: $submissionView);
        $this->stubCsrfToken('asg_vote');
        $_POST['ECF'] = ['Only One, Boston Celtics'];

        $out = $this->runAction($controller, static fn (\Voting\VotingController $c) => $c->submitAsgVote('testgm'));

        $this->assertStringContainsString('voting-submission-success', $out);
        $this->assertStringNotContainsString('voting-submission-error', $out);
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
        ?\Voting\Contracts\VotingBallotServiceInterface $ballotService = null,
    ): \Voting\VotingController {
        if ($ballotService === null) {
            $ballotService = self::createStub(\Voting\Contracts\VotingBallotServiceInterface::class);
            $ballotService->method('getBallotData')->willReturn([]);
        }

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

    /**
     * Two GM candidates on non-voter teams (Ann Lee/Team A, Bob Ray/Team B).
     * GM rows need no PlayerStats, so the fixture stays free of Player\Stats construction.
     *
     * @return list<array{code: string, title: string, instruction: string, candidates: list<array<string, mixed>>}>
     */
    private function twoGmCandidates(): array
    {
        return [[
            'code' => 'GM',
            'title' => 'GM of the Year',
            'instruction' => 'Select THREE.',
            'candidates' => [
                ['type' => 'gm', 'name' => 'Ann Lee', 'teamName' => 'Team A'],
                ['type' => 'gm', 'name' => 'Bob Ray', 'teamName' => 'Team B'],
            ],
        ]];
    }

    private function realBallotView(): \Voting\VotingBallotView
    {
        return new \Voting\VotingBallotView();
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
