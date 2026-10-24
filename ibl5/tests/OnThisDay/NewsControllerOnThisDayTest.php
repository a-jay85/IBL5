<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use Api\Contracts\HtmlResponderInterface;
use OnThisDay\Contracts\OnThisDayServiceInterface;
use OnThisDay\Contracts\OnThisDayViewInterface;
use OnThisDay\OnThisDaySelection;
use Tests\Module\EntryPoints\ModuleEntryPointTestCase;
use Topics\News\NewsController;
use Topics\News\NewsPageConfig;

final class CapturingHtmlResponder implements HtmlResponderInterface
{
    /** @var list<string> */
    public array $captured = [];

    public function html(string $content): void
    {
        $this->captured[] = $content;
    }

    public function json(mixed $data): void
    {
    }
}

/**
 * Covers the "On this day" wiring in NewsController::main().
 */
class NewsControllerOnThisDayTest extends ModuleEntryPointTestCase
{
    private const BLOCK_MARKER = '<section data-test="on-this-day-block"></section>';
    private const TITLE_MARKER = '<h1 data-test="page-title"></h1>';

    private CapturingHtmlResponder $responder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->responder = new CapturingHtmlResponder();
    }

    private function buildController(
        OnThisDayServiceInterface $service,
        OnThisDayViewInterface $view,
        ?\Topics\News\Contracts\NewsViewInterface $newsView = null,
    ): NewsController {
        $auth = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $auth->method('isAuthenticated')->willReturn(false);

        $newsService = self::createStub(\Topics\News\Contracts\NewsServiceInterface::class);
        $newsService->method('getHomePageStories')->willReturn([]);
        $newsService->method('getTopicPageStories')->willReturn([]);
        $newsService->method('getTopicText')->willReturn(null);

        if ($newsView === null) {
            $newsView = self::createStub(\Topics\News\Contracts\NewsViewInterface::class);
            $newsView->method('renderPageTitle')->willReturn(self::TITLE_MARKER);
            $newsView->method('renderTopicBanner')->willReturn('');
        }

        return new NewsController(
            new NewsPageConfig(10, 0, 0, 'IBL Test', 0, 'english'),
            $auth,
            self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class),
            self::createStub(\LastSimRecap\Contracts\LastSimRecapServiceInterface::class),
            self::createStub(\LastSimRecap\Contracts\LastSimRecapViewInterface::class),
            $newsService,
            $newsView,
            $this->responder,
            $service,
            $view,
        );
    }

    private function selection(): OnThisDaySelection
    {
        return new OnThisDaySelection([], 2026, '2026-02-14', 0, 3);
    }

    private function runMain(NewsController $controller, int $newTopic): void
    {
        $baseLevel = ob_get_level();
        ob_start();
        ob_start(); // sacrificial: PageLayout::footer() calls ob_end_flush()
        ob_start();

        try {
            $controller->main($newTopic);
        } finally {
            while (ob_get_level() > $baseLevel) {
                ob_end_clean();
            }
        }
    }

    public function testRendersOnThisDayBlockOnLanding(): void
    {
        $service = self::createMock(OnThisDayServiceInterface::class);
        $service->expects($this->once())->method('getMomentsForSim')->willReturn($this->selection());
        $view = self::createStub(OnThisDayViewInterface::class);
        $view->method('renderHomepageBlock')->willReturn(self::BLOCK_MARKER);

        $this->runMain($this->buildController($service, $view), 0);

        $this->assertContains(self::BLOCK_MARKER, $this->responder->captured);
        $this->assertContains(self::TITLE_MARKER, $this->responder->captured);
    }

    public function testOmitsBlockOnTopicPage(): void
    {
        $service = self::createMock(OnThisDayServiceInterface::class);
        $service->expects($this->never())->method('getMomentsForSim');
        $view = self::createStub(OnThisDayViewInterface::class);
        $view->method('renderHomepageBlock')->willReturn(self::BLOCK_MARKER);

        $this->runMain($this->buildController($service, $view), 5);

        $this->assertNotContains(self::BLOCK_MARKER, $this->responder->captured);
    }

    public function testOmitsBlockWhenViewReturnsEmptyString(): void
    {
        $service = self::createStub(OnThisDayServiceInterface::class);
        $service->method('getMomentsForSim')->willReturn($this->selection());
        $view = self::createStub(OnThisDayViewInterface::class);
        $view->method('renderHomepageBlock')->willReturn('');

        $this->runMain($this->buildController($service, $view), 0);

        $this->assertSame([self::TITLE_MARKER], $this->responder->captured);
    }

    public function testServiceExceptionDoesNotBreakHomepage(): void
    {
        $service = self::createStub(OnThisDayServiceInterface::class);
        $service->method('getMomentsForSim')->willThrowException(new \RuntimeException('history query failed'));
        $view = self::createStub(OnThisDayViewInterface::class);
        $view->method('renderHomepageBlock')->willReturn(self::BLOCK_MARKER);

        $newsView = self::createMock(\Topics\News\Contracts\NewsViewInterface::class);
        $newsView->method('renderPageTitle')->willReturn(self::TITLE_MARKER);
        $newsView->expects($this->once())->method('renderStories')->with([]);

        $previousLog = ini_set('error_log', '/dev/null');
        try {
            $this->runMain($this->buildController($service, $view, $newsView), 0);
        } finally {
            ini_set('error_log', $previousLog === false ? '' : $previousLog);
        }

        $this->assertContains(self::TITLE_MARKER, $this->responder->captured);
        $this->assertNotContains(self::BLOCK_MARKER, $this->responder->captured);
    }
}
