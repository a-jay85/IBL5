<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreView;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ProcessBoxscoresStep;

class ProcessBoxscoresStepTest extends TestCase
{
    /** @var BoxscoreProcessor&\PHPUnit\Framework\MockObject\Stub */
    private BoxscoreProcessor $stubProcessor;
    /** @var BoxscoreView&\PHPUnit\Framework\MockObject\Stub */
    private BoxscoreView $stubView;
    /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubProcessor = $this->createStub(BoxscoreProcessor::class);
        $this->stubView = $this->createStub(BoxscoreView::class);
        $this->stubResolver = $this->createStub(JsbSourceResolverInterface::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new ProcessBoxscoresStep($this->stubProcessor, $this->stubView, $this->stubResolver);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = new ProcessBoxscoresStep($this->stubProcessor, $this->stubView, $this->stubResolver);

        $this->assertSame('Boxscores processed', $step->getLabel());
    }

    public function testSkipsWhenResolverReturnsNull(): void
    {
        $this->stubResolver->method('getContents')->willReturn(null);

        $step = new ProcessBoxscoresStep($this->stubProcessor, $this->stubView, $this->stubResolver);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No IBL5.sco file found', $result->detail);
    }

    public function testSuccessfulProcessing(): void
    {
        $scoResult = [
            'success' => true,
            'gamesInserted' => 10,
            'gamesUpdated' => 2,
            'gamesSkipped' => 0,
            'linesProcessed' => 120,
            'messages' => [],
        ];

        $this->stubResolver->method('getContents')->willReturn('sco data');
        $this->stubProcessor->method('processScoData')->willReturn($scoResult);
        $this->stubView->method('renderParseLog')->willReturn('<div>10 games inserted</div>');

        $step = new ProcessBoxscoresStep($this->stubProcessor, $this->stubView, $this->stubResolver);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Boxscores processed', $result->label);
        $this->assertStringContainsString('10 games inserted', $result->inlineHtml);
        $this->assertTrue($result->collapsibleLog);
    }
}
