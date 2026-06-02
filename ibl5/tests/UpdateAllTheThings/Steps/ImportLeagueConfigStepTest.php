<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use League\LeagueContext;
use LeagueConfig\LeagueConfigRepository;
use LeagueConfig\LeagueConfigService;
use LeagueConfig\LeagueConfigView;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Steps\ImportLeagueConfigStep;

class ImportLeagueConfigStepTest extends TestCase
{
    /** @var LeagueConfigRepository&\PHPUnit\Framework\MockObject\Stub */
    private LeagueConfigRepository $stubRepo;
    /** @var LeagueConfigService&\PHPUnit\Framework\MockObject\Stub */
    private LeagueConfigService $stubService;
    /** @var LeagueConfigView&\PHPUnit\Framework\MockObject\Stub */
    private LeagueConfigView $stubView;
    /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubRepo = self::createStub(LeagueConfigRepository::class);
        $this->stubService = self::createStub(LeagueConfigService::class);
        $this->stubView = self::createStub(LeagueConfigView::class);
        $this->stubResolver = self::createStub(JsbSourceResolverInterface::class);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = $this->createStep();

        $this->assertSame('League config', $step->getLabel());
    }

    public function testSkipsWhenAlreadyImported(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(true);

        $step = $this->createStep();
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Already imported', $result->detail);
    }

    public function testSkipsWhenResolverReturnsNull(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);
        $this->stubResolver->method('getContents')->willReturn(null);

        $step = $this->createStep();
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No IBL5.lge file found', $result->detail);
    }

    public function testSuccessfulImport(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);
        $this->stubResolver->method('getContents')->willReturn('lge-data');

        $this->stubService->method('processLgeData')->willReturn([
            'success' => true,
            'season_ending_year' => 2026,
            'teams_stored' => 28,
            'messages' => [],
        ]);
        $this->stubService->method('crossCheckWithFranchiseSeasons')->willReturn([]);

        $this->stubView->method('renderParseResult')->willReturn('<div>Parsed</div>');

        $step = $this->createStep();
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('League config imported', $result->label);
        $this->assertStringContainsString('<div>Parsed</div>', $result->inlineHtml);
    }

    public function testSuccessfulImportWithDiscrepancies(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);
        $this->stubResolver->method('getContents')->willReturn('lge-data');

        $this->stubService->method('processLgeData')->willReturn([
            'success' => true,
            'season_ending_year' => 2026,
            'teams_stored' => 28,
            'messages' => [],
        ]);
        $this->stubService->method('crossCheckWithFranchiseSeasons')->willReturn(['Mismatch']);

        $this->stubView->method('renderParseResult')->willReturn('<div>Parsed</div>');
        $this->stubView->method('renderCrossCheckResults')->willReturn('<div>Discrepancy</div>');

        $step = $this->createStep();
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('<div>Discrepancy</div>', $result->inlineHtml);
    }

    public function testIblContextCallsCrossCheckAfterSuccessfulImport(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);
        $this->stubResolver->method('getContents')->willReturn('lge-data');

        $mockService = $this->createMock(LeagueConfigService::class);
        $mockService->method('processLgeData')->willReturn([
            'success' => true,
            'season_ending_year' => 2026,
            'teams_stored' => 28,
            'messages' => [],
        ]);
        $mockService->expects($this->once())
            ->method('crossCheckWithFranchiseSeasons')
            ->with(2026)
            ->willReturn([]);

        $this->stubView->method('renderParseResult')->willReturn('');

        $step = new ImportLeagueConfigStep(
            $this->stubRepo,
            $mockService,
            $this->stubView,
            2026,
            $this->stubResolver,
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
    }

    public function testFailedImportReturnsFailure(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);
        $this->stubResolver->method('getContents')->willReturn('lge-data');

        $this->stubService->method('processLgeData')->willReturn([
            'success' => false,
            'season_ending_year' => 0,
            'teams_stored' => 0,
            'messages' => [],
            'error' => 'Invalid file format',
        ]);

        $this->stubView->method('renderParseResult')->willReturn('');

        $step = $this->createStep();
        $result = $step->execute();

        $this->assertFalse($result->success);
        $this->assertSame('Invalid file format', $result->errorMessage);
    }

    public function testOlympicsContextSkipsCrossCheck(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);
        $this->stubResolver->method('getContents')->willReturn('lge-data');

        $mockService = $this->createMock(LeagueConfigService::class);
        $mockService->method('processLgeData')->willReturn([
            'success' => true,
            'season_ending_year' => 2003,
            'teams_stored' => 8,
            'messages' => [],
        ]);
        $mockService->expects($this->never())
            ->method('crossCheckWithFranchiseSeasons');

        $this->stubView->method('renderParseResult')->willReturn('');

        $leagueContext = new LeagueContext();
        $leagueContext->setLeague(LeagueContext::LEAGUE_OLYMPICS);

        $step = new ImportLeagueConfigStep(
            $this->stubRepo,
            $mockService,
            $this->stubView,
            2003,
            $this->stubResolver,
            $leagueContext,
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
    }

    private function createStep(?JsbSourceResolverInterface $resolver = null): ImportLeagueConfigStep
    {
        return new ImportLeagueConfigStep(
            $this->stubRepo,
            $this->stubService,
            $this->stubView,
            2026,
            $resolver ?? $this->stubResolver,
        );
    }
}
