<?php

declare(strict_types=1);

namespace Tests\Team;

use Auth\Contracts\AuthServiceInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Team\Contracts\TeamControllerInterface;
use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamViewInterface;
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

        $this->stubAuthService = $this->createStub(AuthServiceInterface::class);
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

        $this->stubCommonRepo = $this->createStub(TeamIdentityRepositoryInterface::class);
        $this->stubView = $this->createStub(TeamViewInterface::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService'], $GLOBALS['user'], $GLOBALS['sitename'], $GLOBALS['pagetitle']);
        unset($_REQUEST['yr'], $_REQUEST['display'], $_REQUEST['split']);
        unset($_GET['result'], $_GET['msg']);
        unset($_SERVER['HTTP_HX_BOOSTED']);
        parent::tearDown();
    }

    private function buildController(TeamServiceInterface $service): TeamController
    {
        return new TeamController(
            $this->mockDb,
            $this->stubCommonRepo,
            $this->stubAuthService,
            $service,
            $this->stubView
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

    public function testDisplayTeamPagePassesValidYrToService(): void
    {
        $_REQUEST['yr'] = '2024';
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService));

        $this->assertIsArray($captured);
        self::assertSame('2024', $captured[1]);
    }

    public function testDisplayTeamPageDropsInvalidYrFromService(): void
    {
        $_REQUEST['yr'] = 'not-a-year';
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService));

        $this->assertIsArray($captured);
        self::assertNull($captured[1]);
    }

    public function testDisplayTeamPagePassesValidDisplayToService(): void
    {
        $_REQUEST['display'] = 'contracts';
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService));

        $this->assertIsArray($captured);
        self::assertSame('contracts', $captured[2]);
    }

    public function testDisplayTeamPageFallsBackToRatingsWhenDisplayIsArray(): void
    {
        $_REQUEST['display'] = ['contracts'];
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService));

        $this->assertIsArray($captured);
        self::assertSame('ratings', $captured[2]);
    }

    public function testDisplayTeamPageFallsBackToRatingsWhenDisplayIsSplitWithNoSplitKey(): void
    {
        $_REQUEST['display'] = 'split';
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockService = $this->createMock(TeamServiceInterface::class);
        $mockService->expects($this->once())
            ->method('getTeamPageData')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        $this->runDisplayTeamPage($this->buildController($mockService));

        $this->assertIsArray($captured);
        self::assertSame('ratings', $captured[2]);
        self::assertNull($captured[4]);
    }
}
