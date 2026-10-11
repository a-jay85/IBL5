<?php

declare(strict_types=1);

namespace Tests\Topics\News;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Module\EntryPoints\ModuleEntryPointTestCase;
use Topics\News\NewsController;
use Topics\News\NewsPageConfig;

/**
 * Unit coverage for NewsController::main() with every collaborator injected.
 *
 * Extends ModuleEntryPointTestCase for the theme stubs and the boosted
 * PageLayout that main() calls.
 */
class NewsControllerTest extends ModuleEntryPointTestCase
{
    private NewsPageConfig $config;
    private \Auth\Contracts\AuthServiceInterface $authStub;
    private \Repositories\Contracts\TeamIdentityRepositoryInterface $teamStub;
    private \LastSimRecap\Contracts\LastSimRecapServiceInterface $recapServiceStub;
    private \LastSimRecap\Contracts\LastSimRecapViewInterface $recapViewStub;
    private \Topics\News\Contracts\NewsServiceInterface $newsServiceStub;
    private \Topics\News\Contracts\NewsViewInterface $newsViewStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->config();

        $this->authStub = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $this->authStub->method('isAuthenticated')->willReturn(false);

        $this->teamStub = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $this->recapServiceStub = self::createStub(\LastSimRecap\Contracts\LastSimRecapServiceInterface::class);
        $this->recapViewStub = self::createStub(\LastSimRecap\Contracts\LastSimRecapViewInterface::class);

        $this->newsServiceStub = self::createStub(\Topics\News\Contracts\NewsServiceInterface::class);
        $this->newsServiceStub->method('getHomePageStories')->willReturn([]);
        $this->newsServiceStub->method('getTopicPageStories')->willReturn([]);
        $this->newsServiceStub->method('getTopicText')->willReturn(null);
        $this->newsServiceStub->method('normalizeStoryTime')->willReturn(0);
        $this->newsServiceStub->method('computeByteCounts')->willReturn(['intro' => 0, 'full' => 0, 'total' => 0]);

        $this->newsViewStub = self::createStub(\Topics\News\Contracts\NewsViewInterface::class);

        if (!defined('_COMMENTS')) {
            define('_COMMENTS', 'comments');
        }
        if (!defined('_SEARCH')) {
            define('_SEARCH', 'Search');
        }
    }

    public function testAnonymousHomeRenderUsesStoryHomeAndSkipsRecap(): void
    {
        $news = self::createMock(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->expects($this->once())->method('getHomePageStories')->with(7, '')->willReturn([]);

        $team = self::createMock(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $team->expects($this->never())->method('getTeamnameFromUsername');

        $controller = $this->buildController(config: $this->config(storyHome: 7), team: $team, news: $news);

        $this->assertStringContainsString('<h1 class="ibl-title">News</h1>', $this->runMain($controller));
    }

    public function testLoggedInFranchiseUserGetsRecapCard(): void
    {
        $team = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $team->method('getTeamnameFromUsername')->willReturn('Lakers');
        $team->method('getTidFromTeamname')->willReturn(5);

        // The controller must ask for buildSlateForTeam(5), the tid resolved above.
        $recapService = self::createMock(\LastSimRecap\Contracts\LastSimRecapServiceInterface::class);
        $recapService->expects($this->once())->method('buildSlateForTeam')->with(5)->willReturn(
            new \LastSimRecap\Dto\RecapSlate(5, 'Los Angeles', 'Lakers', 1, '2026-01-01', '2026-01-07', 3, 1, 10, '+5', '-2', 3, 1, []),
        );

        $recapView = self::createStub(\LastSimRecap\Contracts\LastSimRecapViewInterface::class);
        $recapView->method('render')->willReturn('<section data-test="recap-card"></section>');

        $controller = $this->buildController(
            auth: $this->authenticatedAs(['username' => 'gm1']),
            team: $team,
            recapService: $recapService,
            recapView: $recapView,
        );

        $this->assertStringContainsString('<section data-test="recap-card"></section>', $this->runMain($controller));
    }

    public function testFreeAgentUserGetsNoRecapCard(): void
    {
        $team = self::createMock(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $team->method('getTeamnameFromUsername')->willReturn(\League\League::FREE_AGENTS_TEAM_NAME);
        $team->expects($this->never())->method('getTidFromTeamname');

        $recapService = self::createMock(\LastSimRecap\Contracts\LastSimRecapServiceInterface::class);
        $recapService->expects($this->never())->method('buildSlateForTeam');

        $controller = $this->buildController(
            auth: $this->authenticatedAs(['username' => 'gm1']),
            team: $team,
            recapService: $recapService,
        );

        $this->runMain($controller);
    }

    public function testNonFranchiseTidGetsNoRecapCard(): void
    {
        $team = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $team->method('getTeamnameFromUsername')->willReturn('Rookies');
        $team->method('getTidFromTeamname')->willReturn(\League\League::ROOKIES_TEAMID);

        $recapService = self::createMock(\LastSimRecap\Contracts\LastSimRecapServiceInterface::class);
        $recapService->expects($this->never())->method('buildSlateForTeam');

        $controller = $this->buildController(
            auth: $this->authenticatedAs(['username' => 'gm1']),
            team: $team,
            recapService: $recapService,
        );

        $this->runMain($controller);
    }

    public function testTopicWithNullTopicTextRendersNoInfoBranch(): void
    {
        $news = self::createMock(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->method('getTopicText')->willReturn(null);
        $news->expects($this->never())->method('bumpAllTopics');
        $news->expects($this->once())->method('getTopicPageStories')->with(4, 10, '')->willReturn([]);

        $controller = $this->buildController(news: $news);

        $output = $this->runMain($controller, 4);

        $this->assertStringContainsString("Sorry, there isn't information for the selected topic.", $output);
        $this->assertStringContainsString('<font class="title">IBL Test</font>', $output);
    }

    #[DataProvider('languageClauseProvider')]
    public function testLanguageClauseFollowsMultilingualFlag(int $flag, string $expected): void
    {
        $news = self::createMock(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->expects($this->once())->method('getHomePageStories')->with(10, $expected)->willReturn([]);

        $controller = $this->buildController(
            config: $this->config(multilingual: $flag, currentLang: 'zz'),
            news: $news,
        );

        $this->runMain($controller);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function languageClauseProvider(): array
    {
        return [
            'multilingual on' => [1, "AND (alanguage='zz' OR alanguage='')"],
            'multilingual off' => [0, ''],
            'multilingual 2 is off' => [2, ''],
        ];
    }

    public function testUserNewsEnabledUsesUserStoryNum(): void
    {
        $news = self::createMock(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->expects($this->once())->method('getHomePageStories')->with(3, '')->willReturn([]);

        $controller = $this->buildController(
            config: $this->config(userNews: 1),
            auth: $this->authenticatedAs(['username' => 'gm1', 'storynum' => 3]),
            team: $this->freeAgentTeam(),
            news: $news,
        );

        $this->runMain($controller);
    }

    public function testUserNewsDisabledIgnoresUserStoryNum(): void
    {
        $news = self::createMock(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->expects($this->once())->method('getHomePageStories')->with(10, '')->willReturn([]);

        $controller = $this->buildController(
            config: $this->config(userNews: 0),
            auth: $this->authenticatedAs(['username' => 'gm1', 'storynum' => 3]),
            team: $this->freeAgentTeam(),
            news: $news,
        );

        $this->runMain($controller);
    }

    public function testStoryFieldsReachViewSanitized(): void
    {
        $title = '<b>t</b> & "q"';
        $notes = '<i>n</i> & \'q\'';
        $topicRow = ['topicid' => 1, 'topicname' => '<x>', 'topicimage' => 'a"b.png', 'topictext' => '<script>z</script>'];

        $news = self::createStub(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->method('getHomePageStories')->willReturn([
            [
                'sid' => 1, 'catid' => 0, 'aid' => 'AP', 'title' => $title,
                'time' => '2026-05-13 12:00:00', 'hometext' => 'home', 'bodytext' => 'body',
                'comments' => 0, 'counter' => 0, 'topic' => 1, 'informant' => 'AP',
                'notes' => $notes, 'acomm' => 0,
            ],
        ]);
        $news->method('getTopicForStory')->willReturn($topicRow);
        $news->method('normalizeStoryTime')->willReturn(0);
        $news->method('computeByteCounts')->willReturn(['intro' => 0, 'full' => 0, 'total' => 0]);

        $view = self::createMock(\Topics\News\Contracts\NewsViewInterface::class);
        $view->expects($this->once())->method('renderStories')->with(self::callback(
            static fn (array $vm): bool => $vm[0]['title'] === \Security\HtmlSanitizer::safeHtmlOutput($title)
                && $vm[0]['notes'] === \Security\HtmlSanitizer::safeHtmlOutput($notes)
                && $vm[0]['topicname'] === \Security\HtmlSanitizer::e($topicRow['topicname'])
                && $vm[0]['topicimage'] === \Security\HtmlSanitizer::e($topicRow['topicimage'])
                && $vm[0]['topictext'] === \Security\HtmlSanitizer::e($topicRow['topictext']),
        ));

        $controller = $this->buildController(news: $news, view: $view);

        $this->runMain($controller);
    }

    public function testStoryLinkCarriesUserModeOrderAndThold(): void
    {
        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            auth: $this->authenticatedAs(['username' => 'gm', 'umode' => 'nested', 'uorder' => 1, 'thold' => 0, 'storynum' => 10]),
            team: $this->freeAgentTeam(),
            news: $this->newsReturning([$this->storyRow()]),
            view: $captured['view'],
        );

        $this->runMain($controller);

        $this->assertSame(
            '<a class="news-article__link" href="modules.php?name=News&amp;file=article&amp;sid=7&amp;mode=nested&amp;order=1&amp;thold=0">Read More...</a>',
            $captured['models'][0]['morelink'],
        );
    }

    /**
     * @param array<string, mixed>|null $userInfo
     */
    #[DataProvider('userInfoLinkProvider')]
    public function testStoryLinkOptionsFollowUserInfoShape(?array $userInfo, string $expectedHref): void
    {
        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            config: $this->config(articleComm: 1),
            auth: $userInfo === null ? null : $this->authenticatedAs($userInfo),
            team: $this->freeAgentTeam(),
            news: $this->newsReturning([$this->storyRow(['comments' => 1])]),
            view: $captured['view'],
        );

        $this->runMain($controller);

        $morelink = $captured['models'][0]['morelink'];
        $this->assertIsString($morelink);
        $this->assertSame(
            '<a class="news-article__link" href="' . $expectedHref . '">Read More...</a>'
                . ' | <a class="news-article__link" href="' . $expectedHref . '">1 comment</a>',
            $morelink,
        );
        $this->assertSame(substr_count($morelink, 'href="'), substr_count($morelink, 'href="' . $expectedHref . '"'));
    }

    /**
     * @return array<string, array{array<string, mixed>|null, string}>
     */
    public static function userInfoLinkProvider(): array
    {
        $url0 = 'modules.php?name=News&amp;file=article&amp;sid=7';

        return [
            'anonymous' => [null, $url0],
            'umode only' => [['umode' => 'flat'], $url0 . '&amp;mode=flat'],
            'null umode with numeric-string order and thold' => [
                ['umode' => null, 'uorder' => '1', 'thold' => '2'],
                $url0 . '&amp;order=1&amp;thold=2',
            ],
            'empty-string umode is set' => [
                ['umode' => '', 'uorder' => 0, 'thold' => 0],
                $url0 . '&amp;mode=&amp;order=0&amp;thold=0',
            ],
        ];
    }

    public function testStoryUrlUsesNumericStringSidUnchanged(): void
    {
        foreach ([7, '7'] as $sid) {
            $captured = $this->captureViewModels();
            $controller = $this->buildController(
                news: $this->newsReturning([$this->storyRow(['sid' => $sid])]),
                view: $captured['view'],
            );

            $this->runMain($controller);

            $this->assertSame(
                '<a class="news-article__link" href="modules.php?name=News&amp;file=article&amp;sid=7">Read More...</a>',
                $captured['models'][0]['morelink'],
            );
        }
    }

    #[DataProvider('moreLinkProvider')]
    public function testMoreLinkBranches(int $articleComm, mixed $acomm, mixed $comments, int $full, int $total, string $expected): void
    {
        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            config: $this->config(articleComm: $articleComm),
            news: $this->newsReturning(
                [$this->storyRow(['acomm' => $acomm, 'comments' => $comments])],
                ['intro' => 0, 'full' => $full, 'total' => $total],
            ),
            view: $captured['view'],
        );

        $this->runMain($controller);

        $this->assertSame(str_replace('{COMMENTS}', (string) constant('_COMMENTS'), $expected), $captured['models'][0]['morelink']);
    }

    /**
     * @return array<string, array{int, mixed, mixed, int, int, string}>
     */
    public static function moreLinkProvider(): array
    {
        $read = '<a class="news-article__link" href="modules.php?name=News&amp;file=article&amp;sid=7">Read More...</a>';
        $bytes = '<span class="news-article__meta-item">120 bytes more</span>';
        $cl = static fn (string $text): string => '<a class="news-article__link" href="modules.php?name=News&amp;file=article&amp;sid=7">' . $text . '</a>';

        return [
            'comments disabled' => [0, 0, 0, 0, 0, $read],
            'enabled, zero comments, no read more' => [1, 0, 0, 0, 0, $cl('comments?')],
            'one comment' => [1, 0, 1, 0, 0, $read . ' | ' . $cl('1 comment')],
            'three comments' => [1, 0, 3, 0, 0, $read . ' | ' . $cl('3 {COMMENTS}')],
            'acomm suppresses comment block' => [1, 1, 5, 0, 0, $read],
            'bytes more' => [0, 0, 0, 120, 120, $read . ' | ' . $bytes],
            'bytes more and comments question' => [1, 0, 0, 120, 120, $read . ' | ' . $bytes . ' | ' . $cl('comments?')],
            'negative comments yields empty' => [1, 0, -1, 0, 0, ''],
            'null comments is zero' => [1, 0, null, 0, 0, $cl('comments?')],
            'numeric-string acomm and comments' => [1, '0', '0', 0, 0, $cl('comments?')],
        ];
    }

    public function testCategoryPrefixAppliedOnlyForNonzeroCatid(): void
    {
        $withCategory = $this->newsReturning([$this->storyRow(['catid' => 4])], categoryTitle: 'Trades');
        $captured = $this->captureViewModels();
        $this->runMain($this->buildController(news: $withCategory, view: $captured['view']));

        $this->assertSame(
            "<a class='readmore' href=\"modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=4\" aria-label=\"Trades\"><font class=\"storycat\">Trades</font></a>: Title",
            $captured['models'][0]['title'],
        );

        $news = self::createMock(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->method('getHomePageStories')->willReturn([$this->storyRow(['catid' => 0])]);
        $news->method('getTopicForStory')->willReturn(['topicname' => 't', 'topicimage' => 'i.png', 'topictext' => 'tt']);
        $news->method('normalizeStoryTime')->willReturn(0);
        $news->method('computeByteCounts')->willReturn(['intro' => 0, 'full' => 0, 'total' => 0]);
        $news->expects($this->never())->method('getCategoryTitle');

        $captured = $this->captureViewModels();
        $this->runMain($this->buildController(news: $news, view: $captured['view']));

        $this->assertSame('Title', $captured['models'][0]['title']);
    }

    public function testCategoryPrefixFallsBackToCategoryLabelWhenTitleNull(): void
    {
        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            news: $this->newsReturning([$this->storyRow(['catid' => 4])]),
            view: $captured['view'],
        );

        $this->runMain($controller);

        $this->assertSame(
            "<a class='readmore' href=\"modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=4\" aria-label=\"Category\"><font class=\"storycat\"></font></a>: Title",
            $captured['models'][0]['title'],
        );
    }

    #[DataProvider('topicArgumentProvider')]
    public function testTopicArgumentNormalization(mixed $arg, string $expectedMethod): void
    {
        $news = self::createMock(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->method('getTopicText')->willReturn(null);

        if ($expectedMethod === 'getTopicPageStories') {
            $news->expects($this->once())->method('getTopicPageStories')->with(5, 10, '')->willReturn([]);
            $news->expects($this->never())->method('getHomePageStories');
        } else {
            $news->expects($this->once())->method('getHomePageStories')->with(10, '')->willReturn([]);
            $news->expects($this->never())->method('getTopicPageStories');
        }

        $this->runMain($this->buildController(news: $news), $arg);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function topicArgumentProvider(): array
    {
        return [
            'int 5' => [5, 'getTopicPageStories'],
            'numeric string 5' => ['5', 'getTopicPageStories'],
            'non-numeric string' => ['abc', 'getHomePageStories'],
            'null' => [null, 'getHomePageStories'],
            'zero' => [0, 'getHomePageStories'],
        ];
    }

    public function testUserStoryNumAcceptsNumericString(): void
    {
        $news = self::createMock(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->expects($this->once())->method('getHomePageStories')->with(5, '')->willReturn([]);

        $controller = $this->buildController(
            config: $this->config(userNews: 1),
            auth: $this->authenticatedAs(['username' => 'gm', 'storynum' => '5']),
            team: $this->freeAgentTeam(),
            news: $news,
        );

        $this->runMain($controller);
    }

    public function testCommentsBranchIsSkippedWhenArticleCommDisabledAndCommentsExist(): void
    {
        $captured = $this->captureViewModels();
        $controller = $this->buildController(
            config: $this->config(articleComm: 0),
            news: $this->newsReturning([$this->storyRow(['comments' => 3])]),
            view: $captured['view'],
        );

        $this->runMain($controller);

        $this->assertSame(
            '<a class="news-article__link" href="modules.php?name=News&amp;file=article&amp;sid=7">Read More...</a>',
            $captured['models'][0]['morelink'],
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function storyRow(array $overrides = []): array
    {
        return $overrides + [
            'sid' => 7, 'catid' => 0, 'aid' => 'AP', 'title' => 'Title',
            'time' => '2026-05-13 12:00:00', 'hometext' => 'home', 'bodytext' => 'body',
            'comments' => 0, 'counter' => 0, 'topic' => 1, 'informant' => 'AP',
            'notes' => 'n', 'acomm' => 0,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array{intro: int, full: int, total: int} $counts
     */
    private function newsReturning(
        array $rows,
        array $counts = ['intro' => 0, 'full' => 0, 'total' => 0],
        ?string $categoryTitle = null,
    ): \Topics\News\Contracts\NewsServiceInterface {
        $news = self::createStub(\Topics\News\Contracts\NewsServiceInterface::class);
        $news->method('getHomePageStories')->willReturn($rows);
        $news->method('getTopicPageStories')->willReturn($rows);
        $news->method('getTopicForStory')->willReturn(['topicname' => 't', 'topicimage' => 'i.png', 'topictext' => 'tt']);
        $news->method('normalizeStoryTime')->willReturn(0);
        $news->method('computeByteCounts')->willReturn($counts);
        $news->method('getCategoryTitle')->willReturn($categoryTitle);

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
        $view->expects($this->once())->method('renderStories')->with(self::callback(
            static function (array $vm) use ($models): bool {
                foreach ($vm as $i => $model) {
                    $models[$i] = $model;
                }

                return true;
            },
        ));

        return ['view' => $view, 'models' => $models];
    }

    public function testControllerSanitizerCallSitesAreUnchanged(): void
    {
        $source = $this->controllerSource();

        $found = [];
        foreach (explode("\n", $source) as $line) {
            if (str_contains($line, 'HtmlSanitizer::')) {
                $found[] = trim($line);
            }
        }

        $this->assertSame([
            '$topic_title = \Security\HtmlSanitizer::safeHtmlOutput($topicText);',
            '$title = \Security\HtmlSanitizer::safeHtmlOutput($row[\'title\']);',
            '$notes = \Security\HtmlSanitizer::safeHtmlOutput($row[\'notes\']);',
            '$topicname = \Security\HtmlSanitizer::e($topicRow[\'topicname\'] ?? \'\');',
            '$topicimage = \Security\HtmlSanitizer::e($topicRow[\'topicimage\'] ?? \'\');',
            '$topictext = \Security\HtmlSanitizer::e($topicRow[\'topictext\'] ?? \'\');',
            '$title1 = \Security\HtmlSanitizer::safeHtmlOutput($catTitle ?? \'\');',
        ], $found);
    }

    public function testControllerHoldsNoDatabaseHandleOrGlobals(): void
    {
        $class = new \ReflectionClass(NewsController::class);

        $constructor = $class->getConstructor();
        $this->assertNotNull($constructor);
        foreach ($constructor->getParameters() as $parameter) {
            $this->assertNotSame('mysqli', ltrim((string) $parameter->getType(), '?\\'));
        }
        foreach ($class->getProperties() as $property) {
            $this->assertNotSame('mysqli', ltrim((string) $property->getType(), '?\\'));
        }

        $this->assertDoesNotMatchRegularExpression('/^\s*global\s/m', $this->controllerSource());
    }

    private function controllerSource(): string
    {
        $file = (new \ReflectionClass(NewsController::class))->getFileName();
        $this->assertIsString($file);
        $source = file_get_contents($file);
        $this->assertIsString($source);

        return $source;
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

    private function freeAgentTeam(): \Repositories\Contracts\TeamIdentityRepositoryInterface
    {
        $team = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $team->method('getTeamnameFromUsername')->willReturn(\League\League::FREE_AGENTS_TEAM_NAME);

        return $team;
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
            $news ?? $this->newsServiceStub,
            $view ?? $this->newsViewStub,
        );
    }

    private function runMain(NewsController $controller, mixed $newTopic = 0): string
    {
        $baseLevel = ob_get_level();
        ob_start(); // L2 — capture
        ob_start(); // L1 — sacrificial (PageLayout::footer() calls ob_end_flush())
        ob_start(); // L0 — sacrificial spare

        try {
            $controller->main($newTopic);
        } finally {
            while (ob_get_level() > $baseLevel + 1) {
                ob_end_flush();
            }
        }

        return (string) ob_get_clean();
    }
}
