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

    private function runMain(NewsController $controller, int $newTopic = 0): string
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
