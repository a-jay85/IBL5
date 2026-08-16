<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use Auth\Contracts\AuthServiceInterface;
use DepthChartEntry\DepthChartEntryController;
use DepthChartEntry\Contracts\DepthChartEntryRepositoryInterface;
use DepthChartEntry\Contracts\DepthChartEntryServiceInterface;
use DepthChartEntry\Contracts\DepthChartEntrySubmissionHandlerInterface;
use DepthChartEntry\Contracts\DepthChartEntryViewInterface;
use Http\HttpRequest;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Team\Contracts\TeamTableServiceInterface;
use Tests\WideUnit\WideUnitTestCase;

/**
 * DepthChartEntryControllerTest - Tests for the depth chart workflow controller
 *
 * Tests:
 * - Controller instantiation
 * - Interface compliance
 * - Dependency injection
 * - Characterization pins for displayForm() input resolution
 */
class DepthChartEntryControllerTest extends WideUnitTestCase
{
    private TeamIdentityRepositoryInterface $stubCommonRepo;
    private DepthChartEntryRepositoryInterface $stubRepository;
    private DepthChartEntryServiceInterface $stubService;
    private DepthChartEntryViewInterface $stubView;
    private DepthChartEntrySubmissionHandlerInterface $stubSubmissionHandler;
    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/classes/Bootstrap/LegacyFunctions.php';

        // PageLayout::header() needs these globals
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

        // Team::initialize() requires an ibl_team_info row; register once for all queries
        $this->mockDb->onQuery('ibl_team_info', [[
            'teamid' => 1,
            'team_city' => 'Test',
            'team_name' => 'Team',
            'color1' => '#000000',
            'color2' => '#ffffff',
            'arena' => 'Test Arena',
            'capacity' => 1000,
            'owner_name' => 'Test Owner',
            'owner_email' => 'test@test.com',
            'discord_id' => null,
            'used_extension_this_chunk' => 0,
            'used_extension_this_season' => 0,
            'has_mle' => 0,
            'has_lle' => 0,
            'league_record' => null,
        ]]);

        // Build an injected Season so displayForm() and getTableOutput() do not
        // construct new Season($this->db) from within the controller.
        $this->season = new Season($this->mockDb);

        $this->stubCommonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $this->stubCommonRepo->method('getTeamnameFromUsername')->willReturn('TestTeam');
        $this->stubCommonRepo->method('getTidFromTeamname')->willReturn(1);

        $this->stubRepository = self::createStub(DepthChartEntryRepositoryInterface::class);
        $this->stubRepository->method('getPlayersOnTeam')->willReturn([]);

        $this->stubService = self::createStub(DepthChartEntryServiceInterface::class);
        $this->stubView = self::createStub(DepthChartEntryViewInterface::class);
        $this->stubSubmissionHandler = self::createStub(DepthChartEntrySubmissionHandlerInterface::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService'], $GLOBALS['user'], $GLOBALS['sitename'], $GLOBALS['pagetitle']);
        unset($_SERVER['HTTP_HX_BOOSTED']);
        parent::tearDown();
    }

    private function buildDceController(TeamTableServiceInterface $teamTableService, ?HttpRequest $request = null): DepthChartEntryController
    {
        return new DepthChartEntryController(
            $this->mockDb,
            $this->stubCommonRepo,
            $this->stubRepository,
            $this->stubService,
            $this->stubView,
            $teamTableService,
            $this->stubSubmissionHandler,
            $request ?? new HttpRequest(),
            $this->season
        );
    }

    /**
     * Single-buffer wrapper for displayForm().
     *
     * RuntimeException from renderTableForDisplay() propagates through
     * displayForm() before PageLayout::footer() is called, so only one
     * ob_start() level is needed (unlike TeamController which reaches footer).
     */
    private function runDisplayForm(DepthChartEntryController $controller, string $username = 'testuser'): void
    {
        ob_start();
        try {
            $controller->displayForm($username);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        ob_end_clean();
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleControllersCanBeInstantiated(): void
    {
        $repository = self::createStub(DepthChartEntryRepositoryInterface::class);
        $service = self::createStub(DepthChartEntryServiceInterface::class);
        $view = self::createStub(DepthChartEntryViewInterface::class);
        $teamTableService = self::createStub(TeamTableServiceInterface::class);
        $submissionHandler = self::createStub(DepthChartEntrySubmissionHandlerInterface::class);
        $controller1 = new DepthChartEntryController($this->mockDb, $this->stubCommonRepo, $repository, $service, $view, $teamTableService, $submissionHandler, new HttpRequest());
        $controller2 = new DepthChartEntryController($this->mockDb, $this->stubCommonRepo, $repository, $service, $view, $teamTableService, $submissionHandler, new HttpRequest());

        $this->assertNotSame($controller1, $controller2);
    }

    // ============================================
    // getTableOutput() SIGNATURE TESTS
    // ============================================

    public function testGetTableOutputAcceptsSplitParameter(): void
    {
        $method = new \ReflectionMethod(DepthChartEntryController::class, 'getTableOutput');
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('teamid', $params[0]->getName());
        $this->assertSame('display', $params[1]->getName());
        $this->assertSame('split', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
        $type = $params[2]->getType();
        $this->assertTrue($type === null || $type->allowsNull());
        $this->assertNull($params[2]->getDefaultValue());
    }

    public function testInterfaceDeclaresGetTableOutputWithSplitParameter(): void
    {
        $method = new \ReflectionMethod(
            \DepthChartEntry\Contracts\DepthChartEntryControllerInterface::class,
            'getTableOutput'
        );
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('split', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
    }

    // ============================================
    // CHARACTERIZATION PINS — displayForm()
    //
    // Observation point: TeamTableServiceInterface::renderTableForDisplay() args.
    // The callback captures args then throws RuntimeException('short-circuit'),
    // which propagates through displayForm() to the test. Assertions run after
    // catching the expected exception.
    // ============================================

    public function testDisplayFormPassesWhitelistedDisplayModeDownstream(): void
    {
        $request = new HttpRequest(request: ['display' => 'contracts']);
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockTeamTableService = $this->createMock(TeamTableServiceInterface::class);
        $mockTeamTableService->method('getRosterAndStarters')->willReturn(['roster' => [], 'starterPids' => []]);
        $mockTeamTableService->method('buildDropdownGroups')->willReturn([]);
        $mockTeamTableService->expects($this->once())
            ->method('renderTableForDisplay')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        try {
            $this->runDisplayForm($this->buildDceController($mockTeamTableService, $request));
        } catch (\RuntimeException $e) {
            self::assertSame('short-circuit', $e->getMessage());
        }

        $this->assertIsArray($captured);
        self::assertSame('contracts', $captured[0]);
    }

    public function testDisplayFormFallsBackToRatingsForArrayValuedDisplay(): void
    {
        $request = new HttpRequest(request: ['display' => ['contracts']]);
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockTeamTableService = $this->createMock(TeamTableServiceInterface::class);
        $mockTeamTableService->method('getRosterAndStarters')->willReturn(['roster' => [], 'starterPids' => []]);
        $mockTeamTableService->method('buildDropdownGroups')->willReturn([]);
        $mockTeamTableService->expects($this->once())
            ->method('renderTableForDisplay')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        try {
            $this->runDisplayForm($this->buildDceController($mockTeamTableService, $request));
        } catch (\RuntimeException $e) {
            self::assertSame('short-circuit', $e->getMessage());
        }

        $this->assertIsArray($captured);
        self::assertSame('ratings', $captured[0]);
    }

    public function testDisplayFormFallsBackToRatingsWhenSplitDisplayHasNoSplitKey(): void
    {
        $request = new HttpRequest(request: ['display' => 'split']);
        /** @var list<mixed>|null $captured */
        $captured = null;
        $mockTeamTableService = $this->createMock(TeamTableServiceInterface::class);
        $mockTeamTableService->method('getRosterAndStarters')->willReturn(['roster' => [], 'starterPids' => []]);
        $mockTeamTableService->method('buildDropdownGroups')->willReturn([]);
        $mockTeamTableService->expects($this->once())
            ->method('renderTableForDisplay')
            ->willReturnCallback(function (mixed ...$args) use (&$captured): never {
                $captured = array_values($args);
                throw new \RuntimeException('short-circuit');
            });

        try {
            $this->runDisplayForm($this->buildDceController($mockTeamTableService, $request));
        } catch (\RuntimeException $e) {
            self::assertSame('short-circuit', $e->getMessage());
        }

        $this->assertIsArray($captured);
        self::assertSame('ratings', $captured[0]);
        self::assertNull($captured[6]);
    }
}
