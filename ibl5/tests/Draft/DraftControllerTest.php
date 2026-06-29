<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use Draft\DraftController;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Security\CsrfGuard;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * DraftControllerTest - Tests for draft controller
 *
 * Tests:
 * - Controller instantiation
 * - Interface compliance
 * - Validation flow (handleDraftSelection and submitSelection)
 * - submitSelection() auth / CSRF / ownership guards (restored from #1107,
 *   adapted to the refactored controller)
 */
class DraftControllerTest extends TestCase
{
    private MockDatabase $mockDb;
    private TeamIdentityRepositoryInterface $mockCommonRepository;
    private Season $mockSeason;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
        $_SESSION = [];
        $_POST = [];
        $this->setupMockDependencies();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
        $_SESSION = [];
        $_POST = [];
        CsrfGuard::setTestClock(null);
    }

    private function setupMockDependencies(): void
    {
        $this->mockCommonRepository = self::createStub(TeamIdentityRepositoryInterface::class);

        $this->mockSeason = self::createStub(Season::class);
        $this->mockSeason->beginningYear = 2024;
        $this->mockSeason->endingYear = 2025;
        $this->mockSeason->phase = 'Draft';
    }

    /**
     * A NukeCompat stub representing a logged-in user whose cookie decodes to
     * the given username.
     */
    private function authedNukeCompat(string $username = 'testuser'): \Utilities\NukeCompat
    {
        $nuke = self::createStub(\Utilities\NukeCompat::class);
        $nuke->method('isUser')->willReturn(true);
        $nuke->method('cookieDecode')->willReturn([0 => '', 1 => $username]);

        return $nuke;
    }

    /**
     * A TeamIdentityRepository stub whose getTeamnameFromUsername() resolves to
     * the given session team (null = teamless session).
     */
    private function repoWithSessionTeam(?string $teamName): TeamIdentityRepositoryInterface
    {
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamnameFromUsername')->willReturn($teamName);

        return $repo;
    }

    /**
     * Mint a valid draft_selection CSRF token and place it in $_POST so
     * CsrfGuard::validateSubmittedToken() accepts the submission.
     */
    private function withValidCsrfToken(): void
    {
        CsrfGuard::clearTokens('draft_selection');
        $_POST['_csrf_token'] = CsrfGuard::generateRawToken('draft_selection');
    }

    /** Assert no draft-table query (read or write) was issued. */
    private function assertNoDraftQuery(): void
    {
        foreach ($this->mockDb->getExecutedQueries() as $query) {
            $this->assertStringNotContainsStringIgnoringCase('ibl_draft', $query);
        }
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testControllerCanBeInstantiated(): void
    {
        $controller = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $this->assertIsObject($controller);
    }

    // ============================================
    // VALIDATION TESTS
    // ============================================

    public function testHandleDraftSelectionReturnsErrorForNullPlayerName(): void
    {
        $controller = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $result = $controller->handleDraftSelection('Test Team', null, 1, 1);

        $this->assertIsString($result);
        $this->assertStringContainsString('select a player', $result);
    }

    public function testHandleDraftSelectionReturnsErrorForEmptyPlayerName(): void
    {
        $controller = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $result = $controller->handleDraftSelection('Test Team', '', 1, 1);

        $this->assertIsString($result);
    }

    /**
     * Characterization (Matrix row 1): with auth + a valid CSRF token + an
     * owning session team, submitSelection() still reaches the post-authorization
     * validation path and returns the "select a player" error for a missing
     * player key. Proves the new guards are prepended without altering the
     * existing validation behavior under the 2-arg signature.
     */
    public function testSubmitSelectionWithoutPlayerKeyReturnsValidationError(): void
    {
        $this->withValidCsrfToken();
        $controller = new DraftController(
            $this->mockDb,
            $this->repoWithSessionTeam('Test Team'),
            $this->mockSeason,
            null,
            null,
            $this->authedNukeCompat()
        );

        $result = $controller->submitSelection(
            ['teamname' => 'Test Team', 'draft_round' => '1', 'draft_pick' => '1'],
            'user-cookie'
        );

        $this->assertStringContainsString('select a player', $result);
    }

    // ============================================
    // SECURITY GUARD TESTS (restored from #1107)
    // ============================================

    /**
     * Matrix row 2: a logged-out POST renders the login box and returns '' —
     * no draft query is issued.
     */
    public function testLoggedOutSubmissionInvokesLoginBoxAndWritesNothing(): void
    {
        $loginBoxCalled = false;
        $nuke = self::createStub(\Utilities\NukeCompat::class);
        $nuke->method('isUser')->willReturn(false);
        $nuke->method('loginBox')->willReturnCallback(function () use (&$loginBoxCalled): void {
            $loginBoxCalled = true;
        });

        $controller = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason,
            null,
            null,
            $nuke
        );

        $result = $controller->submitSelection(
            ['teamname' => 'Test Team', 'player' => 'Some Prospect', 'draft_round' => '1', 'draft_pick' => '1'],
            'user-cookie'
        );

        $this->assertTrue($loginBoxCalled);
        $this->assertSame('', $result);
        $this->assertNoDraftQuery();
    }

    /**
     * Matrix row 3: an authenticated submission with a missing/forged CSRF token
     * is rejected before any draft query.
     */
    public function testForgedCsrfTokenIsRejectedWithNoDraftQuery(): void
    {
        $_POST['_csrf_token'] = 'deadbeef';
        $controller = new DraftController(
            $this->mockDb,
            $this->repoWithSessionTeam('Test Team'),
            $this->mockSeason,
            null,
            null,
            $this->authedNukeCompat()
        );

        $result = $controller->submitSelection(
            ['teamname' => 'Test Team', 'player' => 'Some Prospect', 'draft_round' => '1', 'draft_pick' => '1'],
            'user-cookie'
        );

        $this->assertStringContainsString('Invalid or expired form submission', $result);
        $this->assertNoDraftQuery();
    }

    /**
     * Matrix row 4: an authenticated submission for a team the session user does
     * not own is rejected before any draft query.
     */
    public function testWrongTeamIsRejectedWithNoDraftQuery(): void
    {
        $this->withValidCsrfToken();
        $controller = new DraftController(
            $this->mockDb,
            $this->repoWithSessionTeam('Metros'),
            $this->mockSeason,
            null,
            null,
            $this->authedNukeCompat()
        );

        $result = $controller->submitSelection(
            ['teamname' => 'Stars', 'player' => 'Some Prospect', 'draft_round' => '1', 'draft_pick' => '1'],
            'user-cookie'
        );

        $this->assertStringContainsString('You can only make selections for your own team.', $result);
        $this->assertNoDraftQuery();
    }

    /**
     * Matrix row 5: a teamless session (getTeamnameFromUsername → null) is
     * rejected even when the POST carries a real team name.
     */
    public function testTeamlessSessionIsRejectedWithNoDraftQuery(): void
    {
        $this->withValidCsrfToken();
        $controller = new DraftController(
            $this->mockDb,
            $this->repoWithSessionTeam(null),
            $this->mockSeason,
            null,
            null,
            $this->authedNukeCompat()
        );

        $result = $controller->submitSelection(
            ['teamname' => 'Metros', 'player' => 'Some Prospect', 'draft_round' => '1', 'draft_pick' => '1'],
            'user-cookie'
        );

        $this->assertStringContainsString('You can only make selections for your own team.', $result);
        $this->assertNoDraftQuery();
    }

    /**
     * Matrix row 7: a fully-authorized submission (auth + valid token + owning
     * team) passes the guards and reaches the draft-processing path — the
     * draft-selection lookup query is issued.
     */
    public function testAuthorizedSubmissionReachesDraftProcessing(): void
    {
        $this->withValidCsrfToken();
        $controller = new DraftController(
            $this->mockDb,
            $this->repoWithSessionTeam('Metros'),
            $this->mockSeason,
            null,
            null,
            $this->authedNukeCompat()
        );

        $controller->submitSelection(
            ['teamname' => 'Metros', 'player' => 'Some Prospect', 'draft_round' => '1', 'draft_pick' => '1'],
            'user-cookie'
        );

        $issuedDraftQuery = false;
        foreach ($this->mockDb->getExecutedQueries() as $query) {
            if (stripos($query, 'ibl_draft') !== false) {
                $issuedDraftQuery = true;
                break;
            }
        }
        $this->assertTrue($issuedDraftQuery, 'Authorized submission should reach the draft-selection query.');
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleControllersCanBeInstantiated(): void
    {
        $controller1 = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );
        $controller2 = new DraftController(
            $this->mockDb,
            $this->mockCommonRepository,
            $this->mockSeason
        );

        $this->assertNotSame($controller1, $controller2);
    }
}
