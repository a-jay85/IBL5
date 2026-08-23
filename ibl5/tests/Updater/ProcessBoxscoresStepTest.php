<?php

declare(strict_types=1);

namespace Tests\Updater;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreView;
use Boxscore\RejectedGame;
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

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 2,
            'gamesUpdated' => 1,
            'gamesSkipped' => 0,
            'linesProcessed' => 3,
            'messages' => [],
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($scoData);

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertTrue($result->collapsibleLog);
        self::assertStringContainsString('Parse log', $result->inlineHtml);
    }

    public function testPassesRejectCountAsMessageErrorCount(): void
    {
        $rejects = [
            new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: 1,
                homeTeamid: 2,
                gameOfThatDay: 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
            new RejectedGame(
                gameDate: '2008-04-06',
                visitorTeamid: 3,
                homeTeamid: 4,
                gameOfThatDay: 2,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
        ];

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 2,
            'linesProcessed' => 2,
            'messages' => [],
            'rejectedGames' => $rejects,
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertSame(2, $result->messageErrorCount);

        $headline = null;
        $rejectedLines = [];
        foreach ($result->messages as $msg) {
            if (str_starts_with($msg, '2 game(s) rejected')) {
                $headline = $msg;
            } elseif (str_starts_with($msg, '  rejected:')) {
                $rejectedLines[] = $msg;
            }
        }
        self::assertNotNull($headline);
        self::assertCount(2, $rejectedLines);
    }

    public function testStillSucceedsWhenGamesAreRejected(): void
    {
        $reject = new RejectedGame(
            gameDate: '2008-04-05',
            visitorTeamid: 1,
            homeTeamid: 2,
            gameOfThatDay: 1,
            reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
        );

        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 1,
            'linesProcessed' => 1,
            'messages' => [],
            'rejectedGames' => [$reject],
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertTrue($result->success);
    }

    public function testMessageErrorCountIsZeroWhenNoRejects(): void
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $processor->method('processScoData')->willReturn([
            'success' => true,
            'gamesInserted' => 2,
            'gamesUpdated' => 1,
            'gamesSkipped' => 0,
            'linesProcessed' => 3,
            'messages' => [],
        ]);

        $view = self::createStub(BoxscoreView::class);
        $view->method('renderParseLog')->willReturn('<div>Parse log</div>');

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('sco data');

        $step = new ProcessBoxscoresStep($processor, $view, $resolver);
        $result = $step->execute();

        self::assertSame(0, $result->messageErrorCount);
        self::assertSame([], $result->messages);
    }

    private function buildStep(?string $scoContents): ProcessBoxscoresStep
    {
        $processor = self::createStub(BoxscoreProcessor::class);
        $view = self::createStub(BoxscoreView::class);

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($scoContents);

        return new ProcessBoxscoresStep($processor, $view, $resolver);
    }
}
