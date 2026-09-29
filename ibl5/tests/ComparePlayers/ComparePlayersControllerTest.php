<?php

declare(strict_types=1);

namespace Tests\ComparePlayers;

use Auth\Contracts\AuthServiceInterface;
use ComparePlayers\ComparePlayersController;
use ComparePlayers\Contracts\ComparePlayersRepositoryInterface;
use ComparePlayers\Contracts\ComparePlayersServiceInterface;
use ComparePlayers\Contracts\ComparePlayersViewInterface;
use Tests\WideUnit\WideUnitTestCase;
use Utilities\NukeCompat;

/**
 * @covers \ComparePlayers\ComparePlayersController
 */
final class ComparePlayersControllerTest extends WideUnitTestCase
{
    private ComparePlayersRepositoryInterface $stubRepository;
    private ComparePlayersViewInterface $stubView;

    /** @var array<string, mixed> */
    private array $savedPost = [];

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/classes/Bootstrap/LegacyFunctions.php';

        $stubAuthService = self::createStub(AuthServiceInterface::class);
        $stubAuthService->method('getCookieArray')->willReturn(null);
        $stubAuthService->method('getUsername')->willReturn(null);
        $GLOBALS['authService'] = $stubAuthService;
        $GLOBALS['user'] = '';
        $GLOBALS['sitename'] = '';
        $GLOBALS['pagetitle'] = '';
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $this->savedPost = $_POST;

        $this->stubRepository = self::createStub(ComparePlayersRepositoryInterface::class);
        $this->stubRepository->method('getAllPlayerNames')->willReturn([]);

        $this->stubView = self::createStub(ComparePlayersViewInterface::class);
        $this->stubView->method('renderSearchForm')->willReturn('SEARCH-FORM');
        $this->stubView->method('renderComparisonResults')->willReturn('COMPARISON-RESULTS');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService'], $GLOBALS['user'], $GLOBALS['sitename'], $GLOBALS['pagetitle']);
        unset($_SERVER['HTTP_HX_BOOSTED']);
        $_POST = $this->savedPost;
        parent::tearDown();
    }

    /**
     * Double-buffer wrapper for main().
     *
     * PageLayout::footer() calls ob_end_flush() in HTMX-boosted mode,
     * consuming L1 into L2. We collect from L2 afterward.
     */
    private function runMain(ComparePlayersController $controller, mixed $user = null): string
    {
        $baseLevel = ob_get_level();
        ob_start();
        ob_start();
        $output = '';
        try {
            $controller->main($user);
        } catch (\Throwable $e) {
            while (ob_get_level() > $baseLevel) {
                ob_end_clean();
            }
            throw $e;
        }
        while (ob_get_level() > $baseLevel) {
            $output = (string) ob_get_clean() . $output;
        }
        return $output;
    }

    private function buildController(
        ComparePlayersServiceInterface $service,
        NukeCompat|null $nukeCompat = null,
    ): ComparePlayersController {
        $nuke = $nukeCompat ?? self::createStub(NukeCompat::class);
        return new ComparePlayersController(
            $this->stubRepository,
            $service,
            $this->stubView,
            $nuke,
        );
    }

    // -----------------------------------------------------------------------
    // Search-form rendering
    // -----------------------------------------------------------------------

    public function testRendersSearchFormWhenNoPlayerPosted(): void
    {
        unset($_POST['Player1']);

        $stubService = self::createStub(ComparePlayersServiceInterface::class);

        $controller = $this->buildController($stubService);
        $output = $this->runMain($controller);

        self::assertStringContainsString('SEARCH-FORM', $output);
    }

    // -----------------------------------------------------------------------
    // Length-guard tests
    // -----------------------------------------------------------------------

    public function testRejectsPlayerNameOverOneHundredCharacters(): void
    {
        $_POST['Player1'] = str_repeat('a', 101);
        unset($_POST['Player2']);

        $mockService = self::createMock(ComparePlayersServiceInterface::class);
        $mockService->expects(self::never())->method('comparePlayers');

        $controller = $this->buildController($mockService);
        $output = $this->runMain($controller);

        self::assertStringContainsString('100 characters or less', $output);
        self::assertStringContainsString('SEARCH-FORM', $output);
    }

    public function testAcceptsPlayerNameOfExactlyOneHundredCharacters(): void
    {
        $_POST['Player1'] = str_repeat('a', 100);
        unset($_POST['Player2']);

        $mockService = self::createMock(ComparePlayersServiceInterface::class);
        $mockService->expects(self::once())
            ->method('comparePlayers')
            ->with(str_repeat('a', 100), '')
            ->willReturn(null);

        $controller = $this->buildController($mockService);
        $output = $this->runMain($controller);

        self::assertStringContainsString('SEARCH-FORM', $output);
    }

    public function testRejectsSecondPlayerNameOverOneHundredCharacters(): void
    {
        $_POST['Player1'] = 'ValidName';
        $_POST['Player2'] = str_repeat('a', 101);

        $mockService = self::createMock(ComparePlayersServiceInterface::class);
        $mockService->expects(self::never())->method('comparePlayers');

        $controller = $this->buildController($mockService);
        $output = $this->runMain($controller);

        self::assertStringContainsString('100 characters or less', $output);
        self::assertStringContainsString('SEARCH-FORM', $output);
    }

    // -----------------------------------------------------------------------
    // Trim test
    // -----------------------------------------------------------------------

    public function testTrimsPostedPlayerNamesBeforeComparing(): void
    {
        $_POST['Player1'] = '  Jordan  ';
        $_POST['Player2'] = '  Bird  ';

        $mockService = self::createMock(ComparePlayersServiceInterface::class);
        $mockService->expects(self::once())
            ->method('comparePlayers')
            ->with('Jordan', 'Bird')
            ->willReturn(null);

        $controller = $this->buildController($mockService);
        $output = $this->runMain($controller);

        self::assertStringContainsString('SEARCH-FORM', $output);
    }

    // -----------------------------------------------------------------------
    // Comparison result rendering
    // -----------------------------------------------------------------------

    public function testRendersNotFoundWhenComparisonIsNull(): void
    {
        $_POST['Player1'] = 'Unknown Player';
        unset($_POST['Player2']);

        $mockService = self::createMock(ComparePlayersServiceInterface::class);
        $mockService->expects(self::once())
            ->method('comparePlayers')
            ->with('Unknown Player', '')
            ->willReturn(null);

        $controller = $this->buildController($mockService);
        $output = $this->runMain($controller);

        self::assertStringContainsString('not found', $output);
        self::assertStringContainsString('SEARCH-FORM', $output);
        self::assertStringNotContainsString('COMPARISON-RESULTS', $output);
    }

    public function testRendersComparisonResultsForValidPlayers(): void
    {
        $_POST['Player1'] = 'Jordan';
        $_POST['Player2'] = 'Bird';

        $mockService = self::createMock(ComparePlayersServiceInterface::class);
        $mockService->expects(self::once())
            ->method('comparePlayers')
            ->with('Jordan', 'Bird')
            ->willReturn(['player1' => [], 'player2' => []]);

        $controller = $this->buildController($mockService);
        $output = $this->runMain($controller);

        self::assertStringContainsString('SEARCH-FORM', $output);
        self::assertStringContainsString('COMPARISON-RESULTS', $output);
    }

    // -----------------------------------------------------------------------
    // Cookie decode tests
    // -----------------------------------------------------------------------

    public function testSkipsCookieDecodeForAnonymousUser(): void
    {
        unset($_POST['Player1']);

        $stubService = self::createStub(ComparePlayersServiceInterface::class);

        $mockNuke = self::createMock(NukeCompat::class);
        $mockNuke->method('isUser')->willReturn(false);
        $mockNuke->expects(self::never())->method('cookieDecode');

        $controller = $this->buildController($stubService, $mockNuke);
        $output = $this->runMain($controller, null);

        self::assertStringContainsString('SEARCH-FORM', $output);
    }

    public function testDecodesCookieForAuthenticatedUser(): void
    {
        unset($_POST['Player1']);

        $stubService = self::createStub(ComparePlayersServiceInterface::class);

        $mockNuke = self::createMock(NukeCompat::class);
        $mockNuke->method('isUser')->willReturn(true);
        $mockNuke->expects(self::once())->method('cookieDecode')->with('testgm');

        $controller = $this->buildController($stubService, $mockNuke);
        $this->runMain($controller, 'testgm');
    }
}
