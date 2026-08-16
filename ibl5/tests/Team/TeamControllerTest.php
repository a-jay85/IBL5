<?php

declare(strict_types=1);

namespace Tests\Team;

use Auth\Contracts\AuthServiceInterface;
use Http\HttpRequest;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Team\Contracts\TeamControllerInterface;
use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamViewInterface;
use Team\Team;
use Team\TeamController;
use Tests\WideUnit\WideUnitTestCase;

/**
 * TeamControllerTest - Tests for TeamController
 */
class TeamControllerTest extends WideUnitTestCase
{
    private AuthServiceInterface $stubAuthService;
    private TeamIdentityRepositoryInterface $stubCommonRepo;
    private TeamViewInterface $stubView;

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/classes/Bootstrap/LegacyFunctions.php';

        $this->stubAuthService = self::createStub(AuthServiceInterface::class);
        $this->stubAuthService->method('getCookieArray')->willReturn(null);
        $this->stubAuthService->method('getUsername')->willReturn(null);
        $GLOBALS['authService'] = $this->stubAuthService;
        $GLOBALS['user'] = '';
        $GLOBALS['sitename'] = '';
        $GLOBALS['pagetitle'] = '';
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $this->stubCommonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $this->stubView = self::createStub(TeamViewInterface::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService'], $GLOBALS['user'], $GLOBALS['sitename'], $GLOBALS['pagetitle']);
        unset($_SERVER['HTTP_HX_BOOSTED']);
        parent::tearDown();
    }

    private function buildController(TeamServiceInterface $service, ?TeamViewInterface $view = null, ?HttpRequest $request = null): TeamController
    {
        return new TeamController(
            $this->mockDb,
            $this->stubCommonRepo,
            $this->stubAuthService,
            $service,
            $view ?? $this->stubView,
            $request ?? new HttpRequest()
        );
    }

    /**
     * Double-buffer wrapper for displayTeamPage().
     *
     * PageLayout::footer() calls ob_end_flush() in HTMX-boosted mode,
     * consuming L1 into L2. We clean up L2 afterward.
     */
    private function runDisplayTeamPage(TeamController $controller, int $teamid = 1): void
    {
        $baseLevel = ob_get_level();
        ob_start(); // L2 outer capture
        ob_start(); // L1 sacrificial
        try {
            $controller->displayTeamPage($teamid);
        } catch (\Throwable $e) {
            while (ob_get_level() > $baseLevel) {
                ob_end_clean();
            }
            throw $e;
        }
        // footer() consumed L1; clean L2
        while (ob_get_level() > $baseLevel) {
            ob_end_clean();
        }
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testImplementsTeamControllerInterface(): void
    {
        self::assertContains(TeamControllerInterface::class, (array) class_implements(TeamController::class));
    }

    // ============================================
    // CHARACTERIZATION PINS — displayTeamPage()
    //
    // Observation point: TeamServiceInterface::getTeamPageData() args.
    // The service callback captures args then throws RuntimeException
    // (caught by the controller), so assertions run outside any catch
    // and the mock expectation is also verified at test teardown.
    // ============================================

    public function testDisplayTeamPagePassesValidYearToService(): void
    {
        $request = new HttpRequest(request: ['yr' => '2024']);
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService, null, $request));

        $this->assertIsArray($captured);
        self::assertSame('2024', $captured[1]);
    }

    public function testDisplayTeamPagePassesNullYearForMalformedYearParameter(): void
    {
        $request = new HttpRequest(request: ['yr' => 'not-a-year']);
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService, null, $request));

        $this->assertIsArray($captured);
        self::assertNull($captured[1]);
    }

    public function testDisplayTeamPagePassesWhitelistedDisplayModeToService(): void
    {
        $request = new HttpRequest(request: ['display' => 'contracts']);
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService, null, $request));

        $this->assertIsArray($captured);
        self::assertSame('contracts', $captured[2]);
    }

    public function testDisplayTeamPageFallsBackToRatingsForArrayValuedDisplay(): void
    {
        $request = new HttpRequest(request: ['display' => ['contracts']]);
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService, null, $request));

        $this->assertIsArray($captured);
        self::assertSame('ratings', $captured[2]);
    }

    public function testDisplayTeamPageFallsBackToRatingsWhenSplitDisplayHasNoSplitKey(): void
    {
        $request = new HttpRequest(request: ['display' => 'split']);
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService, null, $request));

        $this->assertIsArray($captured);
        self::assertSame('ratings', $captured[2]);
        self::assertNull($captured[4]);
    }

    public function testDisplayTeamPageFallsBackToRatingsForUnknownDisplayMode(): void
    {
        $request = new HttpRequest(request: ['display' => 'unknown_mode']);
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService, null, $request));

        $this->assertIsArray($captured);
        self::assertSame('ratings', $captured[2]);
    }

    public function testDisplayTeamPagePassesExtensionResultAndMessageToView(): void
    {
        $request = new HttpRequest(get: ['result' => 'ok', 'msg' => 'saved']);

        /** @var array<string, mixed>|null $capturedPageData */
        $capturedPageData = null;

        $stubService = self::createStub(TeamServiceInterface::class);
        $stubService->method('getTeamPageData')->willReturn([
            'teamid' => 1,
            'team' => self::createStub(Team::class),
            'imagesPath' => '',
            'yr' => null,
            'display' => 'ratings',
            'insertyear' => '',
            'isActualTeam' => false,
            'tableOutput' => '',
            'draftPicksTable' => '',
            'currentSeasonCard' => '',
            'awardsCard' => '',
            'franchiseHistoryCard' => '',
            'rafters' => '',
            'userTeamName' => '',
            'isOwnTeam' => false,
            'extensionResult' => null,
            'extensionMsg' => null,
        ]);

        $mockView = $this->createMock(TeamViewInterface::class);
        $mockView->expects($this->once())
            ->method('render')
            ->willReturnCallback(function (mixed $pageData) use (&$capturedPageData): string {
                $capturedPageData = $pageData;
                return '';
            });

        $this->runDisplayTeamPage($this->buildController($stubService, $mockView, $request));

        $this->assertIsArray($capturedPageData);
        self::assertSame('ok', $capturedPageData['extensionResult']);
        self::assertSame('saved', $capturedPageData['extensionMsg']);
    }
}
