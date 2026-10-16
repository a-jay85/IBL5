<?php

declare(strict_types=1);

namespace Tests\Topics\News;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Module\EntryPoints\ModuleEntryPointTestCase;
use Topics\News\NewsController;
use Topics\News\NewsPageConfig;

/**
 * Golden characterization of NewsController::main(): the echoed body (theme chrome
 * stripped), the exact view models handed to the view, and the exact query arguments.
 *
 * Expected strings are derived by hand from the controller source, not recorded.
 */
class NewsControllerGoldenTest extends ModuleEntryPointTestCase
{
    private NewsPageConfig $config;
    private \Auth\Contracts\AuthServiceInterface $authStub;
    private \Repositories\Contracts\TeamIdentityRepositoryInterface $teamStub;
    private \LastSimRecap\Contracts\LastSimRecapServiceInterface $recapServiceStub;
    private \LastSimRecap\Contracts\LastSimRecapViewInterface $recapViewStub;

    protected function setUp(): void
    {
        // The marker stub must be declared before the base setUp() loads theme-stubs.php, and only for the
        // separate-process test; PHPUnit exposes the running test's name only through an internal method.
        if (str_contains($this->toString(), 'testGoldenHomeFileBranch')) { // @phpstan-ignore method.internal
            require_once __DIR__ . '/fixtures/blocks-stub.php';
        }
        parent::setUp();

        $this->config = $this->config();

        $this->authStub = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $this->authStub->method('isAuthenticated')->willReturn(false);

        $this->teamStub = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $this->recapServiceStub = self::createStub(\LastSimRecap\Contracts\LastSimRecapServiceInterface::class);
        $this->recapViewStub = self::createStub(\LastSimRecap\Contracts\LastSimRecapViewInterface::class);

        if (!defined('_COMMENTS')) {
            define('_COMMENTS', 'comments');
        }
        if (!defined('_SEARCH')) {
            define('_SEARCH', 'Search');
        }
    }

    public function testGoldenHomeAnonymousPlainStories(): void
    {
        $rows = [
            $this->story(['sid' => 7, 'catid' => 0, 'title' => 'Plain story', 'hometext' => 'home', 'bodytext' => '', 'comments' => 0, 'counter' => 3, 'topic' => 1, 'informant' => 'AP', 'aid' => 'AP', 'notes' => 'n', 'acomm' => 0]),
            $this->story(['sid' => 8, 'catid' => 5, 'title' => 'Cat story', 'hometext' => 'home', 'bodytext' => 'body', 'comments' => 2, 'counter' => 0, 'topic' => 2, 'informant' => 'ed', 'aid' => 'ed', 'notes' => '', 'acomm' => 0]),
            $this->story(['sid' => 9, 'catid' => 9, 'title' => 'Null cat', 'hometext' => '', 'bodytext' => '', 'comments' => 0, 'counter' => 0, 'topic' => 1, 'informant' => 'AP', 'aid' => 'AP', 'notes' => 'n', 'acomm' => 1]),
        ];
        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            news: $this->newsMock($rows, 0, 10, ''),
            view: $captured['view'],
        );

        $chrome = $this->captureChrome();
        $output = $this->runMain($controller);

        self::assertSame('<h1 class="ibl-title">News</h1>', $this->bodyOf($output, $chrome));
        self::assertSame([
            $this->vm(7, ['title' => 'Plain story', 'counter' => 3, 'notes' => 'n', 'morelink' => $this->readMore($this->url(7))]),
            $this->vm(8, [
                'aid' => 'ed',
                'informant' => 'ed',
                'title' => '<a class=\'readmore\' href="modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=5" aria-label="Trades"><font class="storycat">Trades</font></a>: Cat story',
                'counter' => 0,
                'topic' => 2,
                'notes' => '',
                'morelink' => $this->readMore($this->url(8)) . ' | <span class="news-article__meta-item">8 ' . _BYTESMORE . '</span>',
            ]),
            $this->vm(9, [
                'title' => '<a class=\'readmore\' href="modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=9" aria-label="Category"><font class="storycat"></font></a>: Null cat',
                'counter' => 0,
                'hometext' => '',
                'notes' => 'n',
                'morelink' => $this->readMore($this->url(9)),
            ]),
        ], $captured['models']->getArrayCopy());
    }

    public function testGoldenHomeFranchiseUserRecapAndCommentBranches(): void
    {
        $team = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $team->method('getTeamnameFromUsername')->willReturn('Boston');
        $team->method('getTidFromTeamname')->willReturn(3);

        $recapService = self::createStub(\LastSimRecap\Contracts\LastSimRecapServiceInterface::class);
        $recapService->method('buildSlateForTeam')->willReturn(
            new \LastSimRecap\Dto\RecapSlate(3, 'Boston', 'Celtics', 1, '2026-01-01', '2026-01-07', 3, 1, 10, '+5', '-2', 3, 1, []),
        );

        $recapView = self::createStub(\LastSimRecap\Contracts\LastSimRecapViewInterface::class);
        $recapView->method('render')->willReturn('<section class="recap">RECAP</section>');

        $rows = [
            $this->story(['sid' => 11, 'title' => 'Story 11', 'hometext' => 'home', 'bodytext' => '', 'acomm' => 0, 'comments' => 0]),
            $this->story(['sid' => 12, 'title' => 'Story 12', 'hometext' => 'home', 'bodytext' => '', 'acomm' => 0, 'comments' => 1]),
            $this->story(['sid' => 13, 'title' => 'Story 13', 'hometext' => 'home', 'bodytext' => 'body', 'acomm' => 0, 'comments' => 5]),
            $this->story(['sid' => 14, 'title' => 'Story 14', 'hometext' => 'home', 'bodytext' => '', 'acomm' => 1, 'comments' => 0]),
        ];
        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            config: $this->config(userNews: 1, articleComm: 1),
            auth: $this->authenticatedAs(['username' => 'gm', 'storynum' => '4', 'umode' => 'nested', 'uorder' => 2, 'thold' => '0']),
            team: $team,
            recapService: $recapService,
            recapView: $recapView,
            // newsMock pins getHomePageStories(identicalTo(4), identicalTo('')): storynum '4' is cast under userNews=1.
            news: $this->newsMock($rows, 0, 4, ''),
            view: $captured['view'],
        );

        $chrome = $this->captureChrome();
        $output = $this->runMain($controller);

        $o = '&amp;mode=nested&amp;order=2&amp;thold=0';
        self::assertSame('<h1 class="ibl-title">News</h1><section class="recap">RECAP</section>', $this->bodyOf($output, $chrome));
        self::assertSame([
            $this->vm(11, ['title' => 'Story 11', 'morelink' => '<a class="news-article__link" href="' . $this->url(11, $o) . '">' . _COMMENTSQ . '</a>']),
            $this->vm(12, ['title' => 'Story 12', 'morelink' => $this->readMore($this->url(12, $o)) . ' | <a class="news-article__link" href="' . $this->url(12, $o) . '">1 ' . _COMMENT . '</a>']),
            $this->vm(13, ['title' => 'Story 13', 'morelink' => $this->readMore($this->url(13, $o)) . ' | <span class="news-article__meta-item">8 ' . _BYTESMORE . '</span> | <a class="news-article__link" href="' . $this->url(13, $o) . '">5 ' . \constant('_COMMENTS') . '</a>']),
            $this->vm(14, ['title' => 'Story 14', 'morelink' => $this->readMore($this->url(14, $o))]),
        ], $captured['models']->getArrayCopy());
    }

    public function testGoldenHomeMultilingualClause(): void
    {
        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            config: $this->config(multilingual: 1, currentLang: 'french'),
            news: $this->newsMock([$this->story()], 0, 10, "AND (alanguage='french' OR alanguage='')"),
            view: $captured['view'],
        );

        $chrome = $this->captureChrome();
        $output = $this->runMain($controller);

        self::assertSame('<h1 class="ibl-title">News</h1>', $this->bodyOf($output, $chrome));
        self::assertCount(1, $captured['models']);
    }

    public function testGoldenTopicFound(): void
    {
        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            news: $this->newsMock([$this->story()], 7, 10, '', 'Rumors'),
            view: $captured['view'],
        );

        $chrome = $this->captureChrome();
        // main('7') passes a string topic id; newsMock pins it to identicalTo(7), so the (int) cast is observable.
        $output = $this->runMain($controller, '7');

        self::assertSame(
            '<h1 class="ibl-title">News</h1>' . $chrome['open']
            . '<center><font class="title">IBL Test: Rumors</font><br><br><form action="modules.php?name=Search" method="post"><input type="hidden" name="topic" value="7">'
            . _SEARCHONTOPIC . ': <input type="name" name="query" size="30">&nbsp;&nbsp;<input type="submit" value="' . _SEARCH . '"></form>[ <a href="index.php">'
            . _GOTOHOME . '</a> | <a href="modules.php?name=Topics">' . _SELECTNEWTOPIC . '</a> ]</center>'
            . $chrome['close'] . '<br>',
            $this->bodyOf($output, $chrome),
        );
        self::assertCount(1, $captured['models']);
    }

    public function testGoldenTopicNotFound(): void
    {
        $view = self::createMock(\Topics\News\Contracts\NewsViewInterface::class);
        $view->expects(self::once())->method('renderStories')->with(self::identicalTo([]));
        $controller = $this->buildController(
            news: $this->newsMock([], 42, 10, '', null),
            view: $view,
        );

        $chrome = $this->captureChrome();
        $output = $this->runMain($controller, 42);

        self::assertSame(
            '<h1 class="ibl-title">News</h1>' . $chrome['open']
            . '<center><font class="title">IBL Test</font><br><br>' . _NOINFO4TOPIC
            . '<br><br>[ <a href="modules.php?name=News">' . _GOTONEWSINDEX . '</a> | <a href="modules.php?name=Topics">'
            . _SELECTNEWTOPIC . '</a> ]</center>' . $chrome['close'] . '<br>',
            $this->bodyOf($output, $chrome),
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGoldenHomeFileBranch(): void
    {
        self::assertFalse(defined('HOME_FILE'));
        define('HOME_FILE', true);
        $this->mockDb->onQuery('_blocks', [[
            'bid' => 1, 'bkey' => '', 'title' => 'Center', 'content' => 'c', 'url' => null,
            'blockfile' => null, 'view' => 0, 'expire' => 0, 'action' => '', 'subscription' => 0,
        ]]);

        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            news: $this->newsMock([$this->story()], 0, 10, ''),
            view: $captured['view'],
        );

        $chrome = $this->captureChrome();
        $output = $this->runMain($controller);

        self::assertSame(
            '<h1 class="ibl-title">IBL: Internet Basketball League</h1><!-- blocks:Center:c -->',
            $this->bodyOf($output, $chrome),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function story(array $overrides = []): array
    {
        return $overrides + [
            'sid' => 7, 'catid' => 0, 'aid' => 'AP', 'title' => 'Title',
            'time' => '2026-05-13 12:00:00', 'hometext' => 'home', 'bodytext' => 'body',
            'comments' => 0, 'counter' => 0, 'topic' => 1, 'informant' => 'AP',
            'notes' => 'n', 'acomm' => 0,
        ];
    }

    private function readMore(string $url): string
    {
        return '<a class="news-article__link" href="' . $url . '">' . _READMORE . '</a>';
    }

    private function url(int $sid, string $opts = ''): string
    {
        return "modules.php?name=News&amp;file=article&amp;sid=$sid$opts";
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function vm(int $sid, array $overrides): array
    {
        return array_replace([
            'aid' => 'AP',
            'informant' => 'AP',
            'time' => 1700000000,
            'title' => 'Title',
            'counter' => 0,
            'topic' => 1,
            'hometext' => 'home',
            'notes' => 'n',
            'morelink' => '',
            'topicname' => "topic$sid",
            'topicimage' => "img$sid.png",
            'topictext' => "text$sid",
        ], $overrides);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function newsMock(
        array $rows,
        int $topic,
        int $storyNum,
        string $clause,
        ?string $topicText = null,
    ): \Topics\News\Contracts\NewsServiceInterface {
        $news = self::createMock(\Topics\News\Contracts\NewsServiceInterface::class);
        if ($topic === 0) {
            $news->expects(self::once())->method('getHomePageStories')
                ->with(self::identicalTo($storyNum), self::identicalTo($clause))->willReturn($rows);
            $news->expects(self::never())->method('getTopicPageStories');
        } else {
            $news->expects(self::never())->method('getHomePageStories');
            $news->expects(self::once())->method('getTopicPageStories')
                ->with(self::identicalTo($topic), self::identicalTo($storyNum), self::identicalTo($clause))->willReturn($rows);
            $news->expects(self::once())->method('getTopicText')->with(self::identicalTo($topic))->willReturn($topicText);
            if ($topicText !== null) {
                $news->expects(self::once())->method('bumpAllTopics');
            } else {
                $news->expects(self::never())->method('bumpAllTopics');
            }
        }
        $news->method('getTopicForStory')->willReturnCallback(
            static fn (int $sid): array => ['topicname' => "topic$sid", 'topicimage' => "img$sid.png", 'topictext' => "text$sid"],
        );
        $news->method('normalizeStoryTime')->willReturn(1700000000);
        $news->method('computeByteCounts')->willReturnCallback(
            static fn (string $h, string $b): array => ['intro' => strlen($h), 'full' => strlen($b), 'total' => strlen($h) + strlen($b)],
        );
        $news->method('getCategoryTitle')->willReturnCallback(
            static fn (int $catid): ?string => $catid === 5 ? 'Trades' : null,
        );

        return $news;
    }

    /**
     * @return array{view: \Topics\News\Contracts\NewsViewInterface, models: \ArrayObject<int, array<string, mixed>>}
     */
    private function captureViewModels(): array
    {
        /** @var \ArrayObject<int, array<string, mixed>> $models */
        $models = new \ArrayObject();
        $view = self::createMock(\Topics\News\Contracts\NewsViewInterface::class);
        $view->expects(self::once())->method('renderStories')->with(self::callback(
            static function (array $vm) use ($models): bool {
                foreach ($vm as $i => $model) {
                    $models[$i] = $model;
                }

                return true;
            },
        ));

        return ['view' => $view, 'models' => $models];
    }

    private function captureBuffered(\Closure $emit): string
    {
        $baseLevel = ob_get_level();
        ob_start(); // L2 — capture
        ob_start(); // L1 — sacrificial (PageLayout::footer() calls ob_end_flush())
        ob_start(); // L0 — sacrificial spare

        try {
            $emit();
        } finally {
            while (ob_get_level() > $baseLevel + 1) {
                ob_end_flush();
            }
        }

        return (string) ob_get_clean();
    }

    /**
     * @return array{header: string, open: string, close: string, footer: string}
     */
    private function captureChrome(): array
    {
        return [
            'header' => $this->captureBuffered(static function (): void {
                \PageLayout\PageLayout::header();
            }),
            'open' => (string) file_get_contents(__DIR__ . '/fixtures/table-chrome/open-remote.html'),
            'close' => (string) file_get_contents(__DIR__ . '/fixtures/table-chrome/close.html'),
            'footer' => $this->captureBuffered(static function (): void {
                \PageLayout\PageLayout::footer();
            }),
        ];
    }

    /**
     * @param array{header: string, open: string, close: string, footer: string} $chrome
     */
    private function bodyOf(string $output, array $chrome): string
    {
        self::assertTrue(str_starts_with($output, $chrome['header']), 'output does not start with the page header');
        self::assertTrue(str_ends_with($output, $chrome['footer']), 'output does not end with the page footer');

        return substr($output, strlen($chrome['header']), strlen($output) - strlen($chrome['header']) - strlen($chrome['footer']));
    }

    private function config(
        int $storyHome = 10,
        int $userNews = 0,
        int $articleComm = 0,
        string $siteName = 'IBL Test',
        int $multilingual = 0,
        string $currentLang = 'english',
    ): NewsPageConfig {
        return new NewsPageConfig($storyHome, $userNews, $articleComm, $siteName, $multilingual, $currentLang);
    }

    /**
     * @param array<string, mixed> $userInfo
     */
    private function authenticatedAs(array $userInfo): \Auth\Contracts\AuthServiceInterface
    {
        $auth = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $auth->method('isAuthenticated')->willReturn(true);
        $auth->method('getUserInfo')->willReturn($userInfo);

        return $auth;
    }

    private function buildController(
        ?NewsPageConfig $config = null,
        ?\Auth\Contracts\AuthServiceInterface $auth = null,
        ?\Repositories\Contracts\TeamIdentityRepositoryInterface $team = null,
        ?\LastSimRecap\Contracts\LastSimRecapServiceInterface $recapService = null,
        ?\LastSimRecap\Contracts\LastSimRecapViewInterface $recapView = null,
        ?\Topics\News\Contracts\NewsServiceInterface $news = null,
        ?\Topics\News\Contracts\NewsViewInterface $view = null,
    ): NewsController {
        return new NewsController(
            $config ?? $this->config,
            $auth ?? $this->authStub,
            $team ?? $this->teamStub,
            $recapService ?? $this->recapServiceStub,
            $recapView ?? $this->recapViewStub,
            $news ?? self::createStub(\Topics\News\Contracts\NewsServiceInterface::class),
            $this->withRealChrome($view ?? self::createStub(\Topics\News\Contracts\NewsViewInterface::class)),
        );
    }

    /**
     * Interface doubles return '' from the string-returning chrome methods; route them to the real NewsView
     * so the page-level output stays comparable to the captured legacy bytes.
     */
    private function withRealChrome(\Topics\News\Contracts\NewsViewInterface $view): \Topics\News\Contracts\NewsViewInterface
    {
        if ($view instanceof \PHPUnit\Framework\MockObject\Stub) {
            $real = new \Topics\News\NewsView();
            $view->method('renderPageTitle')->willReturnCallback($real->renderPageTitle(...));
            $view->method('renderTopicBanner')->willReturnCallback($real->renderTopicBanner(...));
        }

        return $view;
    }

    private function runMain(NewsController $controller, mixed $newTopic = 0): string
    {
        return $this->captureBuffered(static function () use ($controller, $newTopic): void {
            $controller->main($newTopic);
        });
    }
}
