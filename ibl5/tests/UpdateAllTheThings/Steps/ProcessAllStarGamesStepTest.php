<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use Boxscore\BoxscoreView;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ProcessAllStarGamesStep;

class ProcessAllStarGamesStepTest extends TestCase
{
    private BoxscoreProcessor $stubProcessor;
    private BoxscoreRepository $stubRepo;
    private BoxscoreView $stubView;

    protected function setUp(): void
    {
        $this->stubProcessor = $this->createStub(BoxscoreProcessor::class);
        $this->stubRepo = $this->createStub(BoxscoreRepository::class);
        $this->stubView = $this->createStub(BoxscoreView::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            '/tmp/IBL5.sco',
        );

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            '/tmp/IBL5.sco',
        );

        $this->assertSame('All-Star games processed', $step->getLabel());
    }

    public function testSkipsWhenFileNotFound(): void
    {
        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            '/nonexistent/IBL5.sco',
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No IBL5.sco file found', $result->detail);
    }

    public function testSuccessfulProcessingWithoutPendingRenames(): void
    {
        $this->stubProcessor->method('processAllStarGames')->willReturn([
            'success' => true,
            'messages' => [],
        ]);
        $this->stubRepo->method('findAllStarGamesWithDefaultNames')->willReturn([]);
        $this->stubView->method('renderAllStarLog')->willReturn('<div>All-Star OK</div>');

        $path = tempnam(sys_get_temp_dir(), 'sco_test_');
        if ($path === false) {
            $this->fail('Failed to create temp file');
        }

        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            $path,
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('All-Star games processed', $result->label);
        $this->assertStringContainsString('All-Star OK', $result->inlineHtml);
    }

    public function testSuccessfulProcessingWithPendingRenames(): void
    {
        $this->stubProcessor->method('processAllStarGames')->willReturn([
            'success' => true,
            'messages' => [],
        ]);
        $this->stubRepo->method('findAllStarGamesWithDefaultNames')->willReturn([
            ['id' => 1, 'Date' => '2026-02-03', 'name' => BoxscoreProcessor::DEFAULT_AWAY_NAME, 'visitorTeamID' => 50, 'homeTeamID' => 51],
        ]);
        $this->stubRepo->method('getPlayersForAllStarTeam')->willReturn(['Player A', 'Player B']);
        $this->stubView->method('renderAllStarLog')->willReturn('<div>Log</div>');
        $this->stubView->method('renderAllStarRenameUI')->willReturn('<div>Rename UI</div>');

        $path = tempnam(sys_get_temp_dir(), 'sco_test_');
        if ($path === false) {
            $this->fail('Failed to create temp file');
        }

        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            $path,
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('<div>Rename UI</div>', $result->inlineHtml);
    }
}
