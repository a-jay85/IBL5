<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use Boxscore\BoxscoreView;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ProcessAllStarGamesStep;

class ProcessAllStarGamesStepTest extends TestCase
{
    /** @var BoxscoreProcessor&\PHPUnit\Framework\MockObject\Stub */
    private BoxscoreProcessor $stubProcessor;
    /** @var BoxscoreRepository&\PHPUnit\Framework\MockObject\Stub */
    private BoxscoreRepository $stubRepo;
    /** @var BoxscoreView&\PHPUnit\Framework\MockObject\Stub */
    private BoxscoreView $stubView;
    /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubProcessor = self::createStub(BoxscoreProcessor::class);
        $this->stubRepo = self::createStub(BoxscoreRepository::class);
        $this->stubView = self::createStub(BoxscoreView::class);
        $this->stubResolver = self::createStub(JsbSourceResolverInterface::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            $this->stubResolver,
        );

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            $this->stubResolver,
        );

        $this->assertSame('All-Star games processed', $step->getLabel());
    }

    public function testSkipsWhenResolverReturnsNull(): void
    {
        $this->stubResolver->method('getContents')->willReturn(null);

        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            $this->stubResolver,
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No IBL5.sco file found', $result->detail);
    }

    public function testSuccessfulProcessingWithoutPendingRenames(): void
    {
        $this->stubResolver->method('getContents')->willReturn('sco data');
        $this->stubProcessor->method('processAllStarGamesData')->willReturn([
            'success' => true,
            'messages' => [],
        ]);
        $this->stubRepo->method('findAllStarGamesWithDefaultNames')->willReturn([]);
        $this->stubView->method('renderAllStarLog')->willReturn('<div>All-Star OK</div>');

        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            $this->stubResolver,
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('All-Star games processed', $result->label);
        $this->assertStringContainsString('All-Star OK', $result->inlineHtml);
    }

    public function testSuccessfulProcessingWithPendingRenames(): void
    {
        $this->stubResolver->method('getContents')->willReturn('sco data');
        $this->stubProcessor->method('processAllStarGamesData')->willReturn([
            'success' => true,
            'messages' => [],
        ]);
        $this->stubRepo->method('findAllStarGamesWithDefaultNames')->willReturn([
            ['id' => 1, 'game_date' => '2026-02-03', 'name' => BoxscoreProcessor::DEFAULT_AWAY_NAME, 'visitor_teamid' => 50, 'home_teamid' => 51],
        ]);
        $this->stubRepo->method('getPlayersForAllStarTeam')->willReturn(['Player A', 'Player B']);
        $this->stubView->method('renderAllStarLog')->willReturn('<div>Log</div>');
        $this->stubView->method('renderAllStarRenameUI')->willReturn('<div>Rename UI</div>');

        $step = new ProcessAllStarGamesStep(
            $this->stubProcessor,
            $this->stubRepo,
            $this->stubView,
            $this->stubResolver,
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('<div>Rename UI</div>', $result->inlineHtml);
    }
}
