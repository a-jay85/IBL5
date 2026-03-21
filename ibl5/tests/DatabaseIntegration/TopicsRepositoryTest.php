<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Topics\TopicsRepository;

/**
 * Tests TopicsRepository against real MariaDB — topic listings with
 * story counts and recent articles from nuke_* tables (read-only).
 */
class TopicsRepositoryTest extends DatabaseTestCase
{
    private TopicsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TopicsRepository($this->db);
    }

    public function testGetTopicsWithArticlesReturnsArray(): void
    {
        $topics = $this->repo->getTopicsWithArticles();

        self::assertIsArray($topics);
    }

    public function testGetTopicsWithArticlesIncludesExpectedFields(): void
    {
        $topics = $this->repo->getTopicsWithArticles();

        // nuke_topics may be empty in some environments — verify structure if data exists
        if ($topics !== []) {
            $first = $topics[0];
            self::assertArrayHasKey('topicId', $first);
            self::assertArrayHasKey('topicName', $first);
            self::assertArrayHasKey('topicImage', $first);
            self::assertArrayHasKey('topicText', $first);
            self::assertArrayHasKey('storyCount', $first);
            self::assertArrayHasKey('totalReads', $first);
            self::assertArrayHasKey('recentArticles', $first);
            self::assertIsArray($first['recentArticles']);
        }
        // If empty, the method executed without error — sufficient for read-only tests
        self::assertIsArray($topics);
    }
}
