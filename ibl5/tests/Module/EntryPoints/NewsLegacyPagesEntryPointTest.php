<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Characterization tests for the legacy News categories.php and article.php pages.
 *
 * The three `exit` branches in article.php cannot run under PHPUnit (exit inside
 * the include terminates the separate process); they are pinned at HTTP level.
 */
class NewsLegacyPagesEntryPointTest extends ModuleEntryPointTestCase
{
    private const STORY = [
        'sid' => 1, 'catid' => 15, 'aid' => 'AP', 'title' => 'Story One',
        'time' => '2026-05-13 12:00:00', 'hometext' => 'home', 'bodytext' => 'body',
        'comments' => 0, 'counter' => 0, 'topic' => 1, 'informant' => 'AP',
        'notes' => 'Hot take', 'acomm' => 0, 'haspoll' => 0, 'poll_id' => 0,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Language-file constants the legacy pages read; mainfile.php includes
        // language/lang-english.php in production. Values copied from that file.
        foreach (['_DATESTRING' => 'l, F d @ H:i:s T', '_COMMENTS' => 'comments', '_NOTE' => 'Note:'] as $k => $v) {
            if (!defined($k)) {
                define($k, $v);
            }
        }
        $this->mockDb->onQuery('LEFT JOIN', [
            ['topicid' => 1, 'topicname' => 'IBL', 'topicimage' => 'i.png', 'topictext' => 't'],
        ]);
        $this->mockDb->onQuery('nuke_stories_cat', [['title' => 'Trades']]);
        $this->mockDb->setMockData([self::STORY]);
    }

    /** First executed query whose SQL contains $needle; fails the test when none does. */
    private function executedQueryContaining(string $needle): string
    {
        foreach ($this->mockDb->getExecutedQueries() as $q) {
            if (str_contains($q, $needle)) {
                return $q;
            }
        }
        $this->fail("No executed query contains: $needle");
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $globals
     */
    private function runCategories(array $get, array $globals = []): string
    {
        return $this->runModule(
            'News',
            get: $get,
            extraGlobals: $globals + ['storyhome' => 10, 'multilingual' => 0, 'articlecomm' => 0],
            file: 'categories.php',
        );
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $globals
     */
    private function runArticle(array $get, array $globals = []): string
    {
        return $this->runModule(
            'News',
            get: $get,
            extraGlobals: $globals + ['multilingual' => 0, 'anonymous' => 'Anonymous'],
            file: 'article.php',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCategoriesNewindexRendersCategoryTitlePrefixAndReadMore(): void
    {
        $output = $this->runCategories(['op' => 'newindex', 'catid' => '15']);

        $this->assertStringContainsString('<h1 class="ibl-title">News Categories</h1>', $output);
        $this->assertStringContainsString('Trades: Story One', $output);
        $this->assertStringContainsString('href="modules.php?name=News&amp;file=article&amp;sid=1"', $output);
        $this->assertStringContainsString('<b>Read More...</b></a> | ', $output);
        $query = $this->executedQueryContaining('SELECT sid, aid, title');
        $this->assertStringContainsString('catid = 15', $query);
        $this->assertStringContainsString('LIMIT 10', $query);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCategoriesCatidZeroStillRendersPage(): void
    {
        $output = $this->runCategories(['op' => 'newindex', 'catid' => '0']);

        $this->assertStringContainsString('News Categories', $output);
        $this->assertStringContainsString('catid = 0', $this->executedQueryContaining('SELECT sid, aid, title'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCategoriesNonNumericCatidIsCastToZero(): void
    {
        $this->runCategories(['op' => 'newindex', 'catid' => 'abc']);

        $this->assertStringContainsString('catid = 0', $this->executedQueryContaining('SELECT sid, aid, title'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCategoriesDefaultOpRendersNothing(): void
    {
        $output = $this->runCategories(['op' => '']);

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('nuke_stories');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCategoriesCommentCountUsesCommentsLabel(): void
    {
        $this->mockDb->setMockData([array_merge(self::STORY, ['comments' => 2])]);

        $output = $this->runCategories(['op' => 'newindex', 'catid' => '15'], ['articlecomm' => 1]);

        $this->assertStringContainsString('2 comments</a>', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCategoriesMultilingualOnFiltersByCurrentLanguage(): void
    {
        $this->runCategories(
            ['op' => 'newindex', 'catid' => '15'],
            ['multilingual' => 1, 'currentlang' => 'english'],
        );

        $this->assertMatchesRegularExpression(
            "/alanguage\s*=\s*'english' OR alanguage\s*=\s*''/",
            $this->executedQueryContaining('SELECT sid, aid, title'),
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCategoriesMultilingualOffHasNoLanguageFilter(): void
    {
        $this->runCategories(['op' => 'newindex', 'catid' => '15']);

        $this->assertStringNotContainsString('alanguage', $this->executedQueryContaining('SELECT sid, aid, title'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testArticleRendersCategoryLinkTitleAndNote(): void
    {
        $output = $this->runArticle(['sid' => '1']);

        $this->assertStringContainsString('<h1 class="ibl-title">Story One</h1>', $output);
        $this->assertStringContainsString(
            '<a href="modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=15"><font class="storycat">Trades</font></a>: Story One',
            $output,
        );
        $this->assertStringContainsString("home\n\nbody\n\n<b>Note:</b> <i>Hot take</i>", $output);
        $this->assertStringContainsString('sid = 1', $this->executedQueryContaining('WHERE sid ='));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testArticleEmptyBodytextFallsBackToHometextPlusNote(): void
    {
        $this->mockDb->setMockData([array_merge(self::STORY, ['bodytext' => ''])]);

        $output = $this->runArticle(['sid' => '1']);

        $this->assertStringContainsString("home\n\n<b>Note:</b> <i>Hot take</i>", $output);
        $this->assertStringNotContainsString("home\n\n\n\n", $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testArticleNonNumericSidWithTeamidIsCastToZero(): void
    {
        $this->runArticle(['sid' => 'abc', 'teamid' => '3']);

        $this->assertStringContainsString('sid = 0', $this->executedQueryContaining('WHERE sid ='));
    }
}
