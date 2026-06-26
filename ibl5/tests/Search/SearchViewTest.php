<?php

declare(strict_types=1);

namespace Tests\Search;

use PHPUnit\Framework\TestCase;
use Search\Contracts\SearchViewInterface;
use Search\SearchView;

// PHP-Nuke language constants used by SearchView
if (!defined('_SEARCHUSERS')) {
    define('_SEARCHUSERS', 'Search Users');
}
if (!defined('_SEARCHIN')) {
    define('_SEARCHIN', 'Search in');
}
if (!defined('_SEARCH')) {
    define('_SEARCH', 'Search');
}
if (!defined('_SEARCHON')) {
    define('_SEARCHON', 'Search on');
}
if (!defined('_SEARCHRESULTS')) {
    define('_SEARCHRESULTS', 'Search Results');
}
if (!defined('_STORIES')) {
    define('_STORIES', 'Stories');
}
if (!defined('_COMMENTS')) {
    define('_COMMENTS', 'Comments');
}
if (!defined('_USERS')) {
    define('_USERS', 'Users');
}
if (!defined('_TOPIC')) {
    define('_TOPIC', 'Topic');
}
if (!defined('_ALLTOPICS')) {
    define('_ALLTOPICS', 'All Topics');
}
if (!defined('_CATEGORY')) {
    define('_CATEGORY', 'Category');
}
if (!defined('_ALLCATEGORIES')) {
    define('_ALLCATEGORIES', 'All Categories');
}
if (!defined('_AUTHOR')) {
    define('_AUTHOR', 'Author');
}
if (!defined('_ALLAUTHORS')) {
    define('_ALLAUTHORS', 'All Authors');
}
if (!defined('_TIMELIMIT')) {
    define('_TIMELIMIT', 'Time Limit');
}
if (!defined('_ANYTIME')) {
    define('_ANYTIME', 'Any Time');
}
if (!defined('_1WEEK')) {
    define('_1WEEK', '1 Week');
}
if (!defined('_2WEEKS')) {
    define('_2WEEKS', '2 Weeks');
}
if (!defined('_1MONTH')) {
    define('_1MONTH', '1 Month');
}
if (!defined('_3MONTHS')) {
    define('_3MONTHS', '3 Months');
}
if (!defined('_6MONTHS')) {
    define('_6MONTHS', '6 Months');
}
if (!defined('_1YEAR')) {
    define('_1YEAR', '1 Year');
}
if (!defined('_NOMATCHES')) {
    define('_NOMATCHES', 'No matches found');
}
if (!defined('_NEXT')) {
    define('_NEXT', 'Next');
}
if (!defined('_PREVIOUS')) {
    define('_PREVIOUS', 'Previous');
}
if (!defined('_POSTED')) {
    define('_POSTED', 'Posted');
}
if (!defined('_BY')) {
    define('_BY', 'by');
}
if (!defined('_ARTICLES')) {
    define('_ARTICLES', 'All Categories');
}
if (!defined('_SSTORIES')) {
    define('_SSTORIES', 'Stories');
}
if (!defined('_SCOMMENTS')) {
    define('_SCOMMENTS', 'Comments');
}
if (!defined('_SUSERS')) {
    define('_SUSERS', 'Users');
}
if (!defined('_ALL')) {
    define('_ALL', 'All');
}
if (!defined('_WEEK')) {
    define('_WEEK', 'Week');
}
if (!defined('_WEEKS')) {
    define('_WEEKS', 'Weeks');
}
if (!defined('_MONTH')) {
    define('_MONTH', 'Month');
}
if (!defined('_MONTHS')) {
    define('_MONTHS', 'Months');
}
if (!defined('_CONTRIBUTEDBY')) {
    define('_CONTRIBUTEDBY', 'Contributed by');
}
if (!defined('_POSTEDBY')) {
    define('_POSTEDBY', 'Posted by');
}
if (!defined('_ON')) {
    define('_ON', 'on');
}
if (!defined('_NOCOMMENTS')) {
    define('_NOCOMMENTS', 'No comments');
}
if (!defined('_UCOMMENT')) {
    define('_UCOMMENT', 'comment');
}
if (!defined('_UCOMMENTS')) {
    define('_UCOMMENTS', 'comments');
}
if (!defined('_EDIT')) {
    define('_EDIT', 'Edit');
}
if (!defined('_DELETE')) {
    define('_DELETE', 'Delete');
}
if (!defined('_ATTACHART')) {
    define('_ATTACHART', 'Attached Article');
}
if (!defined('_SREPLY')) {
    define('_SREPLY', 'reply');
}
if (!defined('_SREPLIES')) {
    define('_SREPLIES', 'replies');
}
if (!defined('_NONAME')) {
    define('_NONAME', 'Anonymous');
}
if (!defined('_PREVMATCHES')) {
    define('_PREVMATCHES', 'Previous Matches');
}
if (!defined('_NEXTMATCHES')) {
    define('_NEXTMATCHES', 'Next Matches');
}

// Legacy PHP-Nuke function stubs (must be in global namespace)
require_once __DIR__ . '/search_test_helpers.php';

/**
 * @covers \Search\SearchView
 */
class SearchViewTest extends TestCase
{
    private SearchView $view;

    protected function setUp(): void
    {
        $this->view = new SearchView();
    }

    public function testRenderShowsSearchForm(): void
    {
        $html = $this->view->render(self::createPageData());

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('Search', $html);
    }

    public function testRenderShowsErrorMessage(): void
    {
        $html = $this->view->render(self::createPageData(['error' => 'Query too short']));

        $this->assertStringContainsString('Query too short', $html);
    }

    public function testRenderShowsNoResultsMessageWhenEmpty(): void
    {
        $html = $this->view->render(self::createPageData(['query' => 'test', 'results' => []]));

        $this->assertStringContainsString('No matches found', $html);
    }

    public function testRenderShowsSearchResultsTitle(): void
    {
        $data = self::createPageData([
            'query' => 'trade',
            'results' => [
                ['sid' => 1, 'aid' => 'admin', 'informant' => 'admin', 'title' => 'Test Article', 'time' => '2024-01-15', 'comments' => 5, 'topicId' => 1, 'topicText' => 'News'],
            ],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('Search Results', $html);
        $this->assertStringContainsString('Test Article', $html);
    }

    public function testRenderUsersTypeShowsUserResults(): void
    {
        $data = self::createPageData([
            'query' => 'test',
            'type' => 'users',
            'results' => [
                ['userId' => 1, 'username' => 'testuser', 'name' => 'Test User'],
            ],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('testuser', $html);
    }

    public function testRenderShowsTopicOptions(): void
    {
        $data = self::createPageData([
            'topics' => [['topicId' => 1, 'topicText' => 'Basketball']],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('Basketball', $html);
    }

    // --- Golden-master characterization tests (Phase 0) ---

    public function testGoldenMasterStories(): void
    {
        $data = self::createPageData([
            'query' => 'basketball',
            'topicText' => 'All Topics',
            'offset' => 10,
            'results' => [
                ['sid' => 1, 'aid' => 'admin', 'informant' => 'reporter', 'title' => 'Story One', 'time' => 1700000000, 'comments' => 0, 'topicId' => 1, 'topicText' => 'News'],
                ['sid' => 2, 'aid' => 'editor', 'informant' => '', 'title' => 'Story Two', 'time' => 1700000000, 'comments' => 1, 'topicId' => 0, 'topicText' => ''],
                ['sid' => 3, 'aid' => 'writer', 'informant' => 'contrib', 'title' => 'Story Three', 'time' => 1700000000, 'comments' => 5, 'topicId' => 2, 'topicText' => 'Sports'],
            ],
            'hasMore' => true,
        ]);

        $expected = <<<'STORIES_GOLDEN'
<div class="search-page"><h1 class="ibl-title">Search in All Topics</h1><form action="modules.php?name=Search" method="post" class="search-form"><div class="search-form__input-row"><div class="ibl-search search-form__search-bar"><svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="query" class="ibl-search__input" value="basketball" placeholder="Search..."><button type="submit" class="ibl-search__btn">Search</button></div></div><div class="search-form__filters"><select name="topic" aria-label="Topic" class="search-form__select"><option value="">All Topics</option></select><select name="category" aria-label="Category" class="search-form__select"><option value="0">All Categories</option></select><select name="author" aria-label="Author" class="search-form__select"><option value="">All Authors</option></select><select name="days" aria-label="Date range" class="search-form__select"><option value="0" selected>All</option><option value="7">1 Week</option><option value="14">2 Weeks</option><option value="30">1 Month</option><option value="60">2 Months</option><option value="90">3 Months</option></select></div><div class="search-form__types"><span class="search-form__types-label">Search on</span><label class="search-form__type" for="search-type-stories">
            <input type="radio" name="type" value="stories" id="search-type-stories" checked>
            <span class="search-form__type-label">Stories</span>
        </label><label class="search-form__type" for="search-type-users">
            <input type="radio" name="type" value="users" id="search-type-users">
            <span class="search-form__type-label">Users</span>
        </label></div></form><div class="search-results"><h3 class="search-results__heading">Search Results</h3><div class="search-results__list"><div class="search-result" style="--anim-delay: 0ms"><div class="search-result__header"><a href="modules.php?name=News&amp;file=article&amp;sid=1" class="search-result__title">Story One</a></div><div class="search-result__meta"><span class="search-result__meta-item">Contributed by reporter</span><span class="search-result__meta-item">Posted by admin on <time datetime="2023-11-14T22:13:20+00:00" class="local-time">Tuesday, November 14 @ 22:13 GMT</time></span><span class="search-result__meta-item">Topic: <a href="modules.php?name=Search&amp;query=&amp;topic=1">News</a></span><span class="search-result__meta-item">No comments</span></div></div><div class="search-result" style="--anim-delay: 40ms"><div class="search-result__header"><a href="modules.php?name=News&amp;file=article&amp;sid=2" class="search-result__title">Story Two</a></div><div class="search-result__meta"><span class="search-result__meta-item">Posted by editor on <time datetime="2023-11-14T22:13:20+00:00" class="local-time">Tuesday, November 14 @ 22:13 GMT</time></span><span class="search-result__meta-item">1 comment</span></div></div><div class="search-result" style="--anim-delay: 80ms"><div class="search-result__header"><a href="modules.php?name=News&amp;file=article&amp;sid=3" class="search-result__title">Story Three</a></div><div class="search-result__meta"><span class="search-result__meta-item">Contributed by contrib</span><span class="search-result__meta-item">Posted by writer on <time datetime="2023-11-14T22:13:20+00:00" class="local-time">Tuesday, November 14 @ 22:13 GMT</time></span><span class="search-result__meta-item">Topic: <a href="modules.php?name=Search&amp;query=&amp;topic=2">Sports</a></span><span class="search-result__meta-item">5 comments</span></div></div></div><div class="search-pagination"><a href="modules.php?name=Search&amp;author=&amp;topic=0&amp;min=10&amp;query=basketball&amp;type=stories&amp;category=0" class="search-pagination__link search-pagination__link--next">Next Matches &rarr;</a></div></div></div>
STORIES_GOLDEN;

        $this->assertSame($expected, $this->view->render($data));
    }

    public function testGoldenMasterComments(): void
    {
        $data = self::createPageData([
            'query' => 'basketball',
            'type' => 'comments',
            'topicText' => 'All Topics',
            'articleComm' => true,
            'results' => [
                ['teamid' => 11, 'sid' => 10, 'subject' => 'Comment One', 'date' => 1700000000, 'name' => 'Alice', 'articleTitle' => 'Article A', 'replyCount' => 1],
                ['teamid' => 22, 'sid' => 20, 'subject' => 'Comment Two', 'date' => 1700000000, 'name' => '', 'articleTitle' => 'Article B', 'replyCount' => 3],
            ],
        ]);

        $expected = <<<'COMMENTS_GOLDEN'
<div class="search-page"><h1 class="ibl-title">Search in All Topics</h1><form action="modules.php?name=Search" method="post" class="search-form"><div class="search-form__input-row"><div class="ibl-search search-form__search-bar"><svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="query" class="ibl-search__input" value="basketball" placeholder="Search..."><button type="submit" class="ibl-search__btn">Search</button></div></div><div class="search-form__filters"><select name="topic" aria-label="Topic" class="search-form__select"><option value="">All Topics</option></select><select name="category" aria-label="Category" class="search-form__select"><option value="0">All Categories</option></select><select name="author" aria-label="Author" class="search-form__select"><option value="">All Authors</option></select><select name="days" aria-label="Date range" class="search-form__select"><option value="0" selected>All</option><option value="7">1 Week</option><option value="14">2 Weeks</option><option value="30">1 Month</option><option value="60">2 Months</option><option value="90">3 Months</option></select></div><div class="search-form__types"><span class="search-form__types-label">Search on</span><label class="search-form__type" for="search-type-stories">
            <input type="radio" name="type" value="stories" id="search-type-stories">
            <span class="search-form__type-label">Stories</span>
        </label><label class="search-form__type" for="search-type-comments">
            <input type="radio" name="type" value="comments" id="search-type-comments" checked>
            <span class="search-form__type-label">Comments</span>
        </label><label class="search-form__type" for="search-type-users">
            <input type="radio" name="type" value="users" id="search-type-users">
            <span class="search-form__type-label">Users</span>
        </label></div></form><div class="search-results"><h3 class="search-results__heading">Search Results</h3><div class="search-results__list"><div class="search-result" style="--anim-delay: 0ms"><div class="search-result__header"><a href="modules.php?name=News&amp;file=article&amp;thold=-1&amp;mode=flat&amp;order=1&amp;sid=10#11" class="search-result__title">Comment One</a></div><div class="search-result__meta"><span class="search-result__meta-item">Posted by Alice on <time datetime="2023-11-14T22:13:20+00:00" class="local-time">Tuesday, November 14 @ 22:13 GMT</time></span><span class="search-result__meta-item">Attached Article: Article A</span><span class="search-result__meta-item">1 reply</span></div></div><div class="search-result" style="--anim-delay: 40ms"><div class="search-result__header"><a href="modules.php?name=News&amp;file=article&amp;thold=-1&amp;mode=flat&amp;order=1&amp;sid=20#22" class="search-result__title">Comment Two</a></div><div class="search-result__meta"><span class="search-result__meta-item">Attached Article: Article B</span><span class="search-result__meta-item">3 replies</span></div></div></div></div></div>
COMMENTS_GOLDEN;

        $this->assertSame($expected, $this->view->render($data));
    }

    public function testGoldenMasterUsers(): void
    {
        $data = self::createPageData([
            'query' => 'basketball',
            'type' => 'users',
            'topicText' => 'All Topics',
            'results' => [
                ['userId' => 1, 'username' => 'alice', 'name' => 'Alice Smith'],
                ['userId' => 2, 'username' => 'bob', 'name' => ''],
            ],
        ]);

        $expected = <<<'USERS_GOLDEN'
<div class="search-page"><h1 class="ibl-title">Search Users</h1><form action="modules.php?name=Search" method="post" class="search-form"><div class="search-form__input-row"><div class="ibl-search search-form__search-bar"><svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="query" class="ibl-search__input" value="basketball" placeholder="Search..."><button type="submit" class="ibl-search__btn">Search</button></div></div><div class="search-form__filters"><select name="topic" aria-label="Topic" class="search-form__select"><option value="">All Topics</option></select><select name="category" aria-label="Category" class="search-form__select"><option value="0">All Categories</option></select><select name="author" aria-label="Author" class="search-form__select"><option value="">All Authors</option></select><select name="days" aria-label="Date range" class="search-form__select"><option value="0" selected>All</option><option value="7">1 Week</option><option value="14">2 Weeks</option><option value="30">1 Month</option><option value="60">2 Months</option><option value="90">3 Months</option></select></div><div class="search-form__types"><span class="search-form__types-label">Search on</span><label class="search-form__type" for="search-type-stories">
            <input type="radio" name="type" value="stories" id="search-type-stories">
            <span class="search-form__type-label">Stories</span>
        </label><label class="search-form__type" for="search-type-users">
            <input type="radio" name="type" value="users" id="search-type-users" checked>
            <span class="search-form__type-label">Users</span>
        </label></div></form><div class="search-results"><h3 class="search-results__heading">Search Results</h3><div class="search-results__list"><div class="search-result search-result--compact" style="--anim-delay: 0ms"><div class="search-result__header"><span class="search-result__title">alice</span><span class="search-result__subtitle">Alice Smith</span></div></div><div class="search-result search-result--compact" style="--anim-delay: 40ms"><div class="search-result__header"><span class="search-result__title">bob</span><span class="search-result__subtitle">Anonymous</span></div></div></div></div></div>
USERS_GOLDEN;

        $this->assertSame($expected, $this->view->render($data));
    }

    public function testGoldenMasterEmptyResults(): void
    {
        $data = self::createPageData([
            'query' => 'test',
            'topicText' => 'All Topics',
            'results' => [],
        ]);

        $expected = <<<'EMPTY_GOLDEN'
<div class="search-page"><h1 class="ibl-title">Search in All Topics</h1><form action="modules.php?name=Search" method="post" class="search-form"><div class="search-form__input-row"><div class="ibl-search search-form__search-bar"><svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="query" class="ibl-search__input" value="test" placeholder="Search..."><button type="submit" class="ibl-search__btn">Search</button></div></div><div class="search-form__filters"><select name="topic" aria-label="Topic" class="search-form__select"><option value="">All Topics</option></select><select name="category" aria-label="Category" class="search-form__select"><option value="0">All Categories</option></select><select name="author" aria-label="Author" class="search-form__select"><option value="">All Authors</option></select><select name="days" aria-label="Date range" class="search-form__select"><option value="0" selected>All</option><option value="7">1 Week</option><option value="14">2 Weeks</option><option value="30">1 Month</option><option value="60">2 Months</option><option value="90">3 Months</option></select></div><div class="search-form__types"><span class="search-form__types-label">Search on</span><label class="search-form__type" for="search-type-stories">
            <input type="radio" name="type" value="stories" id="search-type-stories" checked>
            <span class="search-form__type-label">Stories</span>
        </label><label class="search-form__type" for="search-type-users">
            <input type="radio" name="type" value="users" id="search-type-users">
            <span class="search-form__type-label">Users</span>
        </label></div></form><div class="search-results"><h3 class="search-results__heading">Search Results</h3><div class="ibl-empty-state"><p class="ibl-empty-state__text">No matches found</p></div></div></div>
EMPTY_GOLDEN;

        $this->assertSame($expected, $this->view->render($data));
    }

    public function testEscapesHtmlSpecialCharsInStoryTitle(): void
    {
        $data = self::createPageData([
            'query' => 'test',
            'topicText' => 'All Topics',
            'results' => [
                ['sid' => 99, 'aid' => 'admin', 'informant' => '', 'title' => '<script>"&\'', 'time' => 1700000000, 'comments' => 0, 'topicId' => 0, 'topicText' => ''],
            ],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('&lt;script&gt;&quot;&amp;&apos;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{query: string, type: string, topic: int, category: int, author: string, days: int, min: int, offset: int, topicText: string, topics: list<array{topicId: int, topicText: string}>, categories: list<array{catId: int, title: string}>, authors: list<string>, results: list<mixed>|null, hasMore: bool, articleComm: bool, error: string}
     */
    private static function createPageData(array $overrides = []): array
    {
        $defaults = [
            'query' => '',
            'type' => 'stories',
            'topic' => 0,
            'category' => 0,
            'author' => '',
            'days' => 0,
            'min' => 0,
            'offset' => 0,
            'topicText' => '',
            'topics' => [],
            'categories' => [],
            'authors' => [],
            'results' => null,
            'hasMore' => false,
            'articleComm' => false,
            'error' => '',
        ];

        /** @var array{query: string, type: string, topic: int, category: int, author: string, days: int, min: int, offset: int, topicText: string, topics: list<array{topicId: int, topicText: string}>, categories: list<array{catId: int, title: string}>, authors: list<string>, results: list<mixed>|null, hasMore: bool, articleComm: bool, error: string} */
        return array_merge($defaults, $overrides);
    }
}
