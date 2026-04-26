<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use LeagueConfig\LeagueConfigRepository;
use LeagueConfig\LeagueConfigService;
use LeagueConfig\LeagueConfigView;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ImportLeagueConfigStep;

class ImportLeagueConfigStepTest extends TestCase
{
    private LeagueConfigRepository $stubRepo;
    private LeagueConfigService $stubService;
    private LeagueConfigView $stubView;
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubRepo = $this->createStub(LeagueConfigRepository::class);
        $this->stubService = $this->createStub(LeagueConfigService::class);
        $this->stubView = $this->createStub(LeagueConfigView::class);
        $this->stubResolver = $this->createStub(JsbSourceResolverInterface::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = $this->createStep();

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
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
