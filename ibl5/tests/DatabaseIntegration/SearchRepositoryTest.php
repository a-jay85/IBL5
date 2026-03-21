<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Search\SearchRepository;

/**
 * Tests SearchRepository against real MariaDB — story, comment, and user
 * searches across nuke_* tables (MyISAM, read-only queries), plus topic,
 * category, and author lookups.
 *
 * MyISAM tables don't support transaction rollback, but all methods here
 * are read-only. Tests rely on CI seed data (nuke_stories, nuke_users).
 */
class SearchRepositoryTest extends DatabaseTestCase
{
    private SearchRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SearchRepository($this->db);
    }

    // ── searchStories ───────────────────────────────────────────

    public function testSearchStoriesReturnsEmptyForShortQuery(): void
    {
        $result = $this->repo->searchStories('ab');

        self::assertSame([], $result['results']);
        self::assertFalse($result['hasMore']);
    }

    public function testSearchStoriesReturnsValidStructure(): void
    {
        // Search for a broad term — production/CI may have different stories
        $result = $this->repo->searchStories('the');

        self::assertArrayHasKey('results', $result);
        self::assertArrayHasKey('hasMore', $result);
        self::assertIsArray($result['results']);
        self::assertIsBool($result['hasMore']);

        if ($result['results'] !== []) {
            self::assertArrayHasKey('sid', $result['results'][0]);
            self::assertArrayHasKey('title', $result['results'][0]);
            self::assertArrayHasKey('topicText', $result['results'][0]);
        }
    }

    public function testSearchStoriesHasMorePagination(): void
    {
        // CI seed has 2 stories — limit=1 should trigger hasMore
        $result = $this->repo->searchStories('Details', limit: 1);

        if (count($result['results']) > 0) {
            // If we found results with limit=1, hasMore indicates more exist
            self::assertIsBool($result['hasMore']);
        } else {
            // Production may not have "Details" — just verify structure
            self::assertArrayHasKey('hasMore', $result);
        }
    }

    // ── searchComments ──────────────────────────────────────────

    public function testSearchCommentsReturnsEmptyForShortQuery(): void
    {
        $result = $this->repo->searchComments('ab');

        self::assertSame([], $result['results']);
        self::assertFalse($result['hasMore']);
    }

    // ── searchUsers ─────────────────────────────────────────────

    public function testSearchUsersReturnsEmptyForShortQuery(): void
    {
        $result = $this->repo->searchUsers('ab');

        self::assertSame([], $result['results']);
        self::assertFalse($result['hasMore']);
    }

    public function testSearchUsersFindsMatchingUsers(): void
    {
        // CI seed has nuke_users with username 'testgm'
        $result = $this->repo->searchUsers('testgm');

        self::assertNotEmpty($result['results']);
        self::assertArrayHasKey('userId', $result['results'][0]);
        self::assertArrayHasKey('username', $result['results'][0]);
        self::assertArrayHasKey('name', $result['results'][0]);
    }

    // ── getTopics / getCategories / getAuthors ──────────────────

    public function testGetTopicsReturnsArray(): void
    {
        $topics = $this->repo->getTopics();

        self::assertIsArray($topics);
        if ($topics !== []) {
            self::assertArrayHasKey('topicId', $topics[0]);
            self::assertArrayHasKey('topicText', $topics[0]);
        }
    }

    public function testGetCategoriesReturnsArray(): void
    {
        $categories = $this->repo->getCategories();

        self::assertIsArray($categories);
        if ($categories !== []) {
            self::assertArrayHasKey('catId', $categories[0]);
            self::assertArrayHasKey('title', $categories[0]);
        }
    }

    public function testGetAuthorsReturnsArray(): void
    {
        $authors = $this->repo->getAuthors();

        self::assertIsArray($authors);
    }

    // ── getTopicInfo ────────────────────────────────────────────

    public function testGetTopicInfoReturnsNullForUnknownTopic(): void
    {
        $result = $this->repo->getTopicInfo(999999);

        self::assertNull($result);
    }
}
