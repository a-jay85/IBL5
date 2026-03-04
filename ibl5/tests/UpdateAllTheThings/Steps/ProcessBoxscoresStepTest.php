<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreView;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ProcessBoxscoresStep;

class ProcessBoxscoresStepTest extends TestCase
{
    private BoxscoreProcessor $stubProcessor;
    private BoxscoreView $stubView;

    protected function setUp(): void
    {
        $this->stubProcessor = $this->createStub(BoxscoreProcessor::class);
        $this->stubView = $this->createStub(BoxscoreView::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new ProcessBoxscoresStep($this->stubProcessor, $this->stubView, '/tmp/IBL5.sco');

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = new ProcessBoxscoresStep($this->stubProcessor, $this->stubView, '/tmp/IBL5.sco');

        $this->assertSame('Boxscores processed', $step->getLabel());
    }

    public function testSkipsWhenFileNotFound(): void
    {
        $step = new ProcessBoxscoresStep($this->stubProcessor, $this->stubView, '/nonexistent/IBL5.sco');
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

        $this->stubProcessor->method('processScoFile')->willReturn($scoResult);
        $this->stubView->method('renderParseLog')->willReturn('<div>10 games inserted</div>');

        $path = tempnam(sys_get_temp_dir(), 'sco_test_');
        if ($path === false) {
            $this->fail('Failed to create temp file');
        }

        $step = new ProcessBoxscoresStep($this->stubProcessor, $this->stubView, $path);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Boxscores processed', $result->label);
        $this->assertStringContainsString('10 games inserted', $result->inlineHtml);
    }
}
