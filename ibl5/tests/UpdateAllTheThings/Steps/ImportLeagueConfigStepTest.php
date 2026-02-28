<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use LeagueConfig\LeagueConfigRepository;
use LeagueConfig\LeagueConfigService;
use LeagueConfig\LeagueConfigView;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ImportLeagueConfigStep;

class ImportLeagueConfigStepTest extends TestCase
{
    private LeagueConfigRepository $stubRepo;
    private LeagueConfigService $stubService;
    private LeagueConfigView $stubView;

    protected function setUp(): void
    {
        $this->stubRepo = $this->createStub(LeagueConfigRepository::class);
        $this->stubService = $this->createStub(LeagueConfigService::class);
        $this->stubView = $this->createStub(LeagueConfigView::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = $this->createStep('/tmp/IBL5.lge');

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = $this->createStep('/tmp/IBL5.lge');

        $this->assertSame('League config', $step->getLabel());
    }

    public function testSkipsWhenAlreadyImported(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(true);

        $step = $this->createStep('/tmp/IBL5.lge');
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Already imported', $result->detail);
    }

    public function testSkipsWhenFileNotFound(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);

        $step = $this->createStep('/nonexistent/IBL5.lge');
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No IBL5.lge file found', $result->detail);
    }

    public function testSuccessfulImport(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);

        $this->stubService->method('processLgeFile')->willReturn([
            'success' => true,
            'season_ending_year' => 2026,
            'teams_stored' => 28,
            'messages' => [],
        ]);
        $this->stubService->method('crossCheckWithFranchiseSeasons')->willReturn([]);

        $this->stubView->method('renderParseResult')->willReturn('<div>Parsed</div>');

        $step = $this->createStep($this->createTempFile());
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('League config imported', $result->label);
        $this->assertStringContainsString('<div>Parsed</div>', $result->inlineHtml);
    }

    public function testSuccessfulImportWithDiscrepancies(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);

        $this->stubService->method('processLgeFile')->willReturn([
            'success' => true,
            'season_ending_year' => 2026,
            'teams_stored' => 28,
            'messages' => [],
        ]);
        $this->stubService->method('crossCheckWithFranchiseSeasons')->willReturn(['Mismatch']);

        $this->stubView->method('renderParseResult')->willReturn('<div>Parsed</div>');
        $this->stubView->method('renderCrossCheckResults')->willReturn('<div>Discrepancy</div>');

        $step = $this->createStep($this->createTempFile());
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('<div>Discrepancy</div>', $result->inlineHtml);
    }

    public function testFailedImportReturnsFailure(): void
    {
        $this->stubRepo->method('hasConfigForSeason')->willReturn(false);

        $this->stubService->method('processLgeFile')->willReturn([
            'success' => false,
            'season_ending_year' => 0,
            'teams_stored' => 0,
            'messages' => [],
            'error' => 'Invalid file format',
        ]);

        $this->stubView->method('renderParseResult')->willReturn('');

        $step = $this->createStep($this->createTempFile());
        $result = $step->execute();

        $this->assertFalse($result->success);
        $this->assertSame('Invalid file format', $result->errorMessage);
    }

    private function createStep(string $lgePath): ImportLeagueConfigStep
    {
        return new ImportLeagueConfigStep(
            $this->stubRepo,
            $this->stubService,
            $this->stubView,
            2026,
            $lgePath,
        );
    }

    private function createTempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lge_test_');
        if ($path === false) {
            $this->fail('Failed to create temp file');
        }
        $this->addToAssertionCount(1);

        return $path;
    }
}
