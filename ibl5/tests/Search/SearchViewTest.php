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

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(SearchViewInterface::class, $this->view);
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

    /**
     * @return array{query: string, type: string, topic: int, category: int, author: string, days: int, min: int, offset: int, topicText: string, topics: list<array{topicId: int, topicText: string}>, categories: list<array{catId: int, title: string}>, authors: list<string>, results: list<mixed>|null, hasMore: bool, isAdmin: bool, adminFile: string, articleComm: bool, error: string}
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
            'isAdmin' => false,
            'adminFile' => '',
            'articleComm' => false,
            'error' => '',
        ];

        /** @var array{query: string, type: string, topic: int, category: int, author: string, days: int, min: int, offset: int, topicText: string, topics: list<array{topicId: int, topicText: string}>, categories: list<array{catId: int, title: string}>, authors: list<string>, results: list<mixed>|null, hasMore: bool, isAdmin: bool, adminFile: string, articleComm: bool, error: string} */
        return array_merge($defaults, $overrides);
    }
}
