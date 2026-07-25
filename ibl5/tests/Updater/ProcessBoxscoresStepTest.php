<?php

declare(strict_types=1);

namespace Tests\Updater;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreView;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Steps\ProcessBoxscoresStep;

class ProcessBoxscoresStepTest extends TestCase
{
    public function testGetLabelReturnsBoxscoresProcessed(): void
    {
        $step = $this->buildStep(scoContents: null);
        self::assertSame('Boxscores processed', $step->getLabel());
    }

    public function testSkipsWhenNoScoFileFound(): void
    {
        $step = $this->buildStep(scoContents: null);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsStringIgnoringCase('sco', $result->detail);
    }

    public function testSucceedsWhenScoFilePresent(): void
    {
        $scoData = 'IBL5.sco file data';

        $processor = $this->createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 2,
            'gamesUpdated' => 1,
            'gamesSkipped' => 0,
            'linesProcessed' => 3,
            'messages' => [],
        ]);

        $view = $this->createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = $this->createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($scoData);

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertTrue($result->collapsibleLog);
        self::assertStringContainsString('Parse log', $result->inlineHtml);
    }

    private function buildStep(?string $scoContents): ProcessBoxscoresStep
    {
        $processor = $this->createStub(BoxscoreProcessor::class);
        $view = $this->createStub(BoxscoreView::class);

        $resolver = $this->createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($scoContents);

        return new ProcessBoxscoresStep($processor, $view, $resolver);
    }
}
