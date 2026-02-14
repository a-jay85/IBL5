<?php

declare(strict_types=1);

namespace Tests\Search;

use Search\Contracts\SearchRepositoryInterface;
use Search\SearchRepository;
use Tests\Integration\IntegrationTestCase;

/**
 * @covers \Search\SearchRepository
 */
class SearchRepositoryTest extends IntegrationTestCase
{
    private SearchRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SearchRepository($this->mockDb);
    }

    public function testImplementsRepositoryInterface(): void
    {
        $this->assertInstanceOf(SearchRepositoryInterface::class, $this->repository);
    }

    public function testSearchStoriesReturnsEmptyForShortQuery(): void
    {
        $result = $this->repository->searchStories('ab');

        $this->assertSame([], $result['results']);
        $this->assertFalse($result['hasMore']);
    }

    public function testSearchStoriesReturnsResults(): void
    {
        $this->mockDb->setMockData([
            [
                'sid' => 1,
                'aid' => 'admin',
                'informant' => 'admin',
                'title' => 'Test Trade',
                'time' => '2024-01-15 12:00:00',
                'hometext' => 'content',
                'bodytext' => 'body',
                'comments' => 5,
                'topic' => 3,
                'topictext' => 'Trades',
            ],
        ]);

        $result = $this->repository->searchStories('trade');

        $this->assertCount(1, $result['results']);
        $this->assertSame(1, $result['results'][0]['sid']);
        $this->assertSame('Test Trade', $result['results'][0]['title']);
        $this->assertSame(3, $result['results'][0]['topicId']);
    }

    public function testSearchStoriesDetectsHasMore(): void
    {
        // Mock 11 rows (limit default is 10, so 11 means hasMore = true)
        $rows = [];
        for ($i = 1; $i <= 11; $i++) {
            $rows[] = [
                'sid' => $i,
                'aid' => 'admin',
                'informant' => 'admin',
                'title' => "Article {$i}",
                'time' => '2024-01-15 12:00:00',
                'hometext' => 'content',
                'bodytext' => 'body',
                'comments' => 0,
                'topic' => 1,
                'topictext' => 'News',
            ];
        }
        $this->mockDb->setMockData($rows);

        $result = $this->repository->searchStories('article');

        $this->assertTrue($result['hasMore']);
        $this->assertCount(10, $result['results']);
    }

    public function testSearchCommentsReturnsEmptyForShortQuery(): void
    {
        $result = $this->repository->searchComments('ab');

        $this->assertSame([], $result['results']);
        $this->assertFalse($result['hasMore']);
    }

    public function testSearchCommentsReturnsResults(): void
    {
        $this->mockDb->setMockData([
            [
                'tid' => 1,
                'sid' => 10,
                'subject' => 'Test Comment',
                'date' => '2024-01-15 12:00:00',
                'name' => 'testuser',
                'article_title' => 'Article Title',
                'reply_count' => 2,
            ],
        ]);

        $result = $this->repository->searchComments('test');

        $this->assertCount(1, $result['results']);
        $this->assertSame('Test Comment', $result['results'][0]['subject']);
        $this->assertSame(2, $result['results'][0]['replyCount']);
    }

    public function testSearchUsersReturnsEmptyForShortQuery(): void
    {
        $result = $this->repository->searchUsers('ab');

        $this->assertSame([], $result['results']);
    }

    public function testSearchUsersReturnsResults(): void
    {
        $this->mockDb->setMockData([
            [
                'user_id' => 1,
                'username' => 'testuser',
                'name' => 'Test User',
            ],
        ]);

        $result = $this->repository->searchUsers('test');

        $this->assertCount(1, $result['results']);
        $this->assertSame('testuser', $result['results'][0]['username']);
    }

    public function testGetTopicsReturnsTransformedData(): void
    {
        $this->mockDb->setMockData([
            ['topicid' => 1, 'topictext' => 'Basketball'],
        ]);

        $result = $this->repository->getTopics();

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['topicId']);
        $this->assertSame('Basketball', $result[0]['topicText']);
    }

    public function testGetCategoriesReturnsTransformedData(): void
    {
        $this->mockDb->setMockData([
            ['catid' => 2, 'title' => 'Trades'],
        ]);

        $result = $this->repository->getCategories();

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['catId']);
        $this->assertSame('Trades', $result[0]['title']);
    }

    public function testGetAuthorsReturnsAuthorsArray(): void
    {
        $this->mockDb->setMockData([
            ['aid' => 'admin'],
            ['aid' => 'editor'],
        ]);

        $result = $this->repository->getAuthors();

        $this->assertSame(['admin', 'editor'], $result);
    }

    public function testGetTopicInfoReturnsTransformedData(): void
    {
        $this->mockDb->setMockData([
            ['topicimage' => 'basketball.gif', 'topictext' => 'Basketball'],
        ]);

        $result = $this->repository->getTopicInfo(1);

        $this->assertNotNull($result);
        $this->assertSame('basketball.gif', $result['topicImage']);
        $this->assertSame('Basketball', $result['topicText']);
    }

    public function testGetTopicInfoReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTopicInfo(999);

        $this->assertNull($result);
    }
}
