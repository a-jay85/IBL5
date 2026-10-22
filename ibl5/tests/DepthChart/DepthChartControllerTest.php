<?php

declare(strict_types=1);

namespace Tests\DepthChart;

use Auth\Contracts\AuthServiceInterface;
use DepthChart\DepthChartController;
use DepthChart\Contracts\DepthChartRepositoryInterface;
use DepthChart\Contracts\DepthChartServiceInterface;
use DepthChart\Contracts\DepthChartSubmissionHandlerInterface;
use DepthChart\Contracts\DepthChartViewInterface;
use Http\HttpRequest;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Team\Contracts\TeamTableServiceInterface;
use Tests\WideUnit\WideUnitTestCase;

/**
 * DepthChartControllerTest - Tests for the depth chart workflow controller
 *
 * Tests:
 * - Controller instantiation
 * - Interface compliance
 * - Dependency injection
 * - Characterization pins for displayForm() input resolution
 */
class DepthChartControllerTest extends WideUnitTestCase
{
    private TeamIdentityRepositoryInterface $stubCommonRepo;
    private DepthChartRepositoryInterface $stubRepository;
    private DepthChartServiceInterface $stubService;
    private DepthChartViewInterface $stubView;
    private DepthChartSubmissionHandlerInterface $stubSubmissionHandler;
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

        $this->stubRepository = self::createStub(DepthChartRepositoryInterface::class);
        $this->stubRepository->method('getPlayersOnTeam')->willReturn([]);

        $this->stubService = self::createStub(DepthChartServiceInterface::class);
        $this->stubView = self::createStub(DepthChartViewInterface::class);
        $this->stubSubmissionHandler = self::createStub(DepthChartSubmissionHandlerInterface::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService'], $GLOBALS['user'], $GLOBALS['sitename'], $GLOBALS['pagetitle']);
        unset($_SERVER['HTTP_HX_BOOSTED']);
        parent::tearDown();
    }

    private function buildDceController(TeamTableServiceInterface $teamTableService, ?HttpRequest $request = null): DepthChartController
    {
        return new DepthChartController(
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
    private function runDisplayForm(DepthChartController $controller, string $username = 'testuser'): void
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

    /**
     * Full-render wrapper for displayForm(): runs through PageLayout::footer().
     * footer() ob_end_flush()es the sacrificial L1 buffer into L2, so L2 holds
     * the whole page on return (same shape as TeamControllerTest::runDisplayTeamPage).
     */
    private function runDisplayFormToCompletion(DepthChartController $controller, string $username = 'testuser'): string
    {
        $baseLevel = ob_get_level();
        ob_start(); // L2 outer capture
        ob_start(); // L1 sacrificial, consumed by PageLayout::footer()
        try {
            $controller->displayForm($username);
        } catch (\Throwable $e) {
            while (ob_get_level() > $baseLevel) {
                ob_end_clean();
            }
            throw $e;
        }
        $output = '';
        while (ob_get_level() > $baseLevel) {
            $output = (string) ob_get_clean() . $output;
        }
        return $output;
    }

    /**
     * TeamTableService stub whose renderTableForDisplay() returns a marker
     * instead of throwing, so displayForm() runs to the footer.
     */
    private function buildPassThroughTeamTableService(string $tableHtml = '<table id="ratings-marker"></table>'): TeamTableServiceInterface
    {
        $stub = self::createStub(TeamTableServiceInterface::class);
        $stub->method('getRosterAndStarters')->willReturn(['roster' => [], 'starterPids' => []]);
        $stub->method('buildDropdownGroups')->willReturn([]);
        $stub->method('renderTableForDisplay')->willReturn($tableHtml);
        return $stub;
    }

    /**
     * Recording view mock: every render* call appends [method, args] to $calls.
     * @param list<array{0: string, 1: list<mixed>}> $calls
     */
    private function buildRecordingView(array &$calls, int $playerRows): DepthChartViewInterface
    {
        $view = $this->createMock(DepthChartViewInterface::class);
        foreach (['renderTeamLogo', 'renderSavedDepthChartDropdown', 'renderHelpSection', 'renderLineupPreview', 'renderFormHeader', 'renderFormFooter', 'renderMobileView'] as $method) {
            $view->expects(self::once())->method($method)
                ->willReturnCallback(function (mixed ...$args) use (&$calls, $method): void {
                    $calls[] = [$method, array_values($args)];
                });
        }
        $view->expects(self::exactly($playerRows))->method('renderPlayerRow')
            ->willReturnCallback(function (mixed ...$args) use (&$calls): void {
                $calls[] = ['renderPlayerRow', array_values($args)];
            });
        return $view;
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleControllersCanBeInstantiated(): void
    {
        $repository = self::createStub(DepthChartRepositoryInterface::class);
        $service = self::createStub(DepthChartServiceInterface::class);
        $view = self::createStub(DepthChartViewInterface::class);
        $teamTableService = self::createStub(TeamTableServiceInterface::class);
        $submissionHandler = self::createStub(DepthChartSubmissionHandlerInterface::class);
        $controller1 = new DepthChartController($this->mockDb, $this->stubCommonRepo, $repository, $service, $view, $teamTableService, $submissionHandler, new HttpRequest());
        $controller2 = new DepthChartController($this->mockDb, $this->stubCommonRepo, $repository, $service, $view, $teamTableService, $submissionHandler, new HttpRequest());

        $this->assertNotSame($controller1, $controller2);
    }

    // ============================================
    // getTableOutput() SIGNATURE TESTS
    // ============================================

    public function testGetTableOutputAcceptsSplitParameter(): void
    {
        $method = new \ReflectionMethod(DepthChartController::class, 'getTableOutput');
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
            \DepthChart\Contracts\DepthChartControllerInterface::class,
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

    /**
     * DepthChartController::displayForm passes a valid split key through to renderTableForDisplay.
     */
    public function testDisplayFormHonorsValidSplitKey(): void
    {
        $request = new HttpRequest(request: ['display' => 'split', 'split' => 'home']);
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
        self::assertSame('split', $captured[0]);
        self::assertSame('home', $captured[6]);
    }

    public function testDisplayFormFallsBackToRatingsForInvalidSplitKey(): void
    {
        $request = new HttpRequest(request: ['display' => 'split', 'split' => 'bogus_key']);
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

    public function testDisplayFormFallsBackToRatingsForArrayValuedSplit(): void
    {
        $request = new HttpRequest(request: ['display' => 'split', 'split' => ['home']]);
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

    public function testDisplayFormIgnoresSplitForNonSplitDisplay(): void
    {
        $request = new HttpRequest(request: ['display' => 'contracts', 'split' => 'home']);
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
        self::assertNull($captured[6]);
    }

    // ============================================
    // CHARACTERIZATION PINS — displayForm() full render
    //
    // Observation points: recorded view calls, captured echo output, and
    // $_SESSION flash state. These run displayForm() through PageLayout::footer().
    // ============================================

    /**
     * @return DepthChartRepositoryInterface
     */
    private function buildTwoPlayerRepository(): DepthChartRepositoryInterface
    {
        $repository = self::createStub(DepthChartRepositoryInterface::class);
        $repository->method('getPlayersOnTeam')->willReturn([
            ['pid' => 7, 'name' => 'Seven', 'dc_pg_depth' => 1],
            ['pid' => 9, 'name' => 'Nine', 'dc_pg_depth' => 2],
        ]);
        return $repository;
    }

    public function testDisplayFormDefaultsToRatingsWhenDisplayParamAbsent(): void
    {
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
            $this->runDisplayForm($this->buildDceController($mockTeamTableService, new HttpRequest()));
        } catch (\RuntimeException $e) {
            self::assertSame('short-circuit', $e->getMessage());
        }

        $this->assertIsArray($captured);
        self::assertSame('ratings', $captured[0]);
        self::assertNull($captured[6]);
    }

    public function testDisplayFormRendersViewCallsInFixedOrder(): void
    {
        $this->stubRepository = $this->buildTwoPlayerRepository();
        $stubService = self::createStub(DepthChartServiceInterface::class);
        $stubService->method('computeQualityScore')->willReturn(12.5);
        $this->stubService = $stubService;
        /** @var list<array{0: string, 1: list<mixed>}> $calls */
        $calls = [];
        $this->stubView = $this->buildRecordingView($calls, 2);

        $this->runDisplayFormToCompletion($this->buildDceController($this->buildPassThroughTeamTableService()));

        self::assertSame(
            ['renderTeamLogo', 'renderSavedDepthChartDropdown', 'renderHelpSection', 'renderLineupPreview', 'renderFormHeader', 'renderPlayerRow', 'renderPlayerRow', 'renderFormFooter', 'renderMobileView'],
            array_column($calls, 0)
        );
        // renderFormHeader args: 'TestTeam', 1, ['PG','SG','SF','PF','C'] (JsbConstants::PLAYER_POSITIONS)
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        self::assertSame([1], $calls[0][1]);
        self::assertSame(['TestTeam', 1, $positions], $calls[4][1]);
        self::assertSame(1, $calls[5][1][1]);
        self::assertSame(2, $calls[6][1][1]);
        self::assertSame($positions, $calls[8][1][1]);
    }

    public function testDisplayFormMergesQualityScoreAndFlashOverrideIntoPlayerRows(): void
    {
        $_SESSION['_ibl_depth_chart_flash'] = ['errors_html' => '', 'post_data' => ['dc_pg_depth_7' => '3']];
        $this->stubRepository = $this->buildTwoPlayerRepository();
        $stubService = self::createStub(DepthChartServiceInterface::class);
        $stubService->method('computeQualityScore')->willReturn(12.5);
        $stubService->method('buildFormOverride')->willReturn([
            7 => ['dc_pg_depth' => 3, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0, 'dc_pf_depth' => 0, 'dc_c_depth' => 0, 'dc_can_play_in_game' => 1, 'dc_minutes' => 30],
        ]);
        $this->stubService = $stubService;
        /** @var list<array{0: string, 1: list<mixed>}> $calls */
        $calls = [];
        $this->stubView = $this->buildRecordingView($calls, 2);

        $this->runDisplayFormToCompletion($this->buildDceController($this->buildPassThroughTeamTableService()));

        $expectedRows = [
            ['pid' => 7, 'name' => 'Seven', 'dc_pg_depth' => 3, 'quality_score' => 12.5, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0, 'dc_pf_depth' => 0, 'dc_c_depth' => 0, 'dc_can_play_in_game' => 1, 'dc_minutes' => 30],
            ['pid' => 9, 'name' => 'Nine', 'dc_pg_depth' => 2, 'quality_score' => 12.5],
        ];
        self::assertSame($expectedRows[0], $calls[5][1][0]);
        self::assertSame($expectedRows[1], $calls[6][1][0]);
        self::assertSame($expectedRows, $calls[8][1][0]);
    }

    public function testDisplayFormConsumesFailureFlashAndRendersErrorAlert(): void
    {
        $_SESSION['_ibl_depth_chart_flash'] = ['errors_html' => '<ul><li>Bad lineup</li></ul>', 'post_data' => []];
        $stubService = self::createStub(DepthChartServiceInterface::class);
        $stubService->method('buildFormOverride')->willReturn([]);
        $this->stubService = $stubService;

        $output = $this->runDisplayFormToCompletion($this->buildDceController($this->buildPassThroughTeamTableService()));

        $alert = '<div class="ibl-alert ibl-alert--error"><ul><li>Bad lineup</li></ul></div>';
        $title = '<h1 class="ibl-title">Depth Chart Entry</h1>';
        self::assertStringContainsString($alert, $output);
        self::assertStringContainsString($title, $output);
        self::assertGreaterThan((int) strpos($output, $title), (int) strpos($output, $alert));
        self::assertFalse(isset($_SESSION['_ibl_depth_chart_flash']));
    }

    public function testDisplayFormOmitsErrorAlertAndOverrideWithoutFlash(): void
    {
        unset($_SESSION['_ibl_depth_chart_flash']);
        $this->stubRepository = $this->buildTwoPlayerRepository();
        $mockService = $this->createMock(DepthChartServiceInterface::class);
        $mockService->expects(self::never())->method('buildFormOverride');
        $mockService->method('computeQualityScore')->willReturn(12.5);
        $this->stubService = $mockService;
        /** @var list<array{0: string, 1: list<mixed>}> $calls */
        $calls = [];
        $this->stubView = $this->buildRecordingView($calls, 2);

        $output = $this->runDisplayFormToCompletion($this->buildDceController($this->buildPassThroughTeamTableService()));

        self::assertStringNotContainsString('ibl-alert--error', $output);
        self::assertSame(['pid' => 7, 'name' => 'Seven', 'dc_pg_depth' => 1, 'quality_score' => 12.5], $calls[5][1][0]);
    }

    public function testDisplayFormLeavesNonArrayFlashUntouched(): void
    {
        $_SESSION['_ibl_depth_chart_flash'] = 'not-an-array';

        $output = $this->runDisplayFormToCompletion($this->buildDceController($this->buildPassThroughTeamTableService()));

        self::assertStringNotContainsString('ibl-alert--error', $output);
        self::assertSame('not-an-array', $_SESSION['_ibl_depth_chart_flash']);
    }

    public function testDisplayFormSkipsMalformedFlashFieldsButStillConsumesFlash(): void
    {
        $_SESSION['_ibl_depth_chart_flash'] = ['errors_html' => 42, 'post_data' => 'x'];
        $mockService = $this->createMock(DepthChartServiceInterface::class);
        $mockService->expects(self::never())->method('buildFormOverride');
        $this->stubService = $mockService;

        $output = $this->runDisplayFormToCompletion($this->buildDceController($this->buildPassThroughTeamTableService()));

        self::assertStringNotContainsString('ibl-alert--error', $output);
        self::assertFalse(isset($_SESSION['_ibl_depth_chart_flash']));
    }

    public function testDisplayFormEmitsJsConfigAndScriptTagsAfterRatingsTable(): void
    {
        $this->stubRepository = $this->buildTwoPlayerRepository();
        $stubService = self::createStub(DepthChartServiceInterface::class);
        $stubService->method('computeQualityScore')->willReturn(12.5);
        $this->stubService = $stubService;

        $output = $this->runDisplayFormToCompletion($this->buildDceController($this->buildPassThroughTeamTableService()));

        $open = '<div class="table-scroll-wrapper"><div class="table-scroll-container" tabindex="0" role="region" aria-label="Player ratings">';
        // TableViewDropdown::wrap() injects a caption inside the table, so match the opening tag only.
        $marker = '<table id="ratings-marker">';
        $openPos = strpos($output, $open);
        self::assertNotFalse($openPos);
        $markerPos = strpos($output, $marker, $openPos);
        self::assertNotFalse($markerPos);
        $closePos = strpos($output, '</div></div>', $markerPos);
        self::assertNotFalse($closePos);

        $config = '<script>window.IBL_DEPTH_CHART_CONFIG = {"teamId":1,"apiBaseUrl":"modules.php?name=DepthChartEntry&op=api","currentRosterPids":[7,9]};</script>';
        $configPos = strpos($output, $config, $closePos);
        self::assertNotFalse($configPos);

        $previous = $configPos;
        foreach (['depth-chart-changes.js', 'depth-chart-lineup-preview.js', 'saved-depth-charts.js', 'depth-chart-mobile.js', 'contract-hint.js'] as $script) {
            $pos = strpos($output, '<script src="jslib/' . $script . '"></script>', $previous);
            self::assertNotFalse($pos, $script);
            $previous = $pos;
        }

        $nextSimPos = strpos($output, '<h2 class="ibl-title">Next Sim</h2>', $previous);
        self::assertNotFalse($nextSimPos);
        self::assertStringContainsString('No games projected next sim!', $output);
    }

    public function testDisplayFormPublicSurfaceIsUnchanged(): void
    {
        $class = new \ReflectionClass(DepthChartController::class);
        $public = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            $class->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        sort($public);
        self::assertSame(['__construct', 'displayForm', 'getTableOutput', 'handleSubmit'], $public);

        foreach ($class->getMethods() as $method) {
            if (!$method->isPublic()) {
                self::assertTrue($method->isPrivate(), $method->getName());
            }
        }
    }
}
