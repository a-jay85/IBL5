<?php

declare(strict_types=1);

namespace Search\Contracts;

/**
 * Interface for the Search repository.
 *
 * Defines methods for searching stories, comments, and users,
 * and for retrieving filter options (topics, categories, authors).
 *
 * @phpstan-type StoryResult array{sid: int, aid: string, informant: string, title: string, time: string, comments: int, topicId: int, topicText: string}
 * @phpstan-type CommentResult array{teamid: int, sid: int, subject: string, date: string, name: string, articleTitle: string, replyCount: int}
 * @phpstan-type UserResult array{userId: int, username: string, name: string}
 * @phpstan-type StorySearchResult array{results: list<StoryResult>, hasMore: bool}
 * @phpstan-type CommentSearchResult array{results: list<CommentResult>, hasMore: bool}
 * @phpstan-type UserSearchResult array{results: list<UserResult>, hasMore: bool}
 * @phpstan-type TopicRow array{topicId: int, topicText: string}
 * @phpstan-type CategoryRow array{catId: int, title: string}
 * @phpstan-type TopicInfoRow array{topicImage: string, topicText: string}
 */
interface SearchRepositoryInterface
{
    /** Preset name for the transactions view (replaces the retired TransactionHistory module). */
    public const PRESET_TRANSACTIONS = 'transactions';

    /**
     * Whitelist of valid preset names mapped to the story category IDs they select.
     * IDs: 1 Waiver Pool Moves, 2 Trades, 3 Contract Extensions, 8 Free Agency,
     * 10 Rookie Extension, 14 Position Changes.
     */
    public const PRESET_CATEGORY_IDS = [
        self::PRESET_TRANSACTIONS => [1, 2, 3, 8, 10, 14],
    ];

    /**
     * Search for stories matching the given criteria.
     *
     * @param string $query Search query (min 3 characters)
     * @param int $topic Topic ID filter (0 = all topics)
     * @param int $category Category ID filter (0 = all categories)
     * @param string $author Author ID filter ('' = all authors)
     * @param int $days Date range filter (0 = all time)
     * @param int $offset Pagination offset
     * @param int $limit Results per page
     * @return StorySearchResult
     */
    public function searchStories(
        string $query,
        int $topic = 0,
        int $category = 0,
        string $author = '',
        int $days = 0,
        int $offset = 0,
        int $limit = 10
    ): array;

    /**
     * Search stories restricted to a named category preset.
     *
     * Unlike searchStories(), an empty or short $query is allowed: the preset alone
     * selects rows. When $query is 3+ characters, the same four-column LIKE filter
     * as searchStories() is applied on top of the preset.
     *
     * @param string $preset Preset name; must be a key of PRESET_CATEGORY_IDS
     * @param string $query Optional text filter (ignored when shorter than 3 characters)
     * @param int $topic Topic ID filter (0 = all topics)
     * @param string $author Author ID filter ('' = all authors)
     * @param int $days Date range filter (0 = all time)
     * @param int $offset Pagination offset
     * @param int $limit Results per page
     * @return StorySearchResult Empty result when $preset is not whitelisted
     */
    public function searchStoriesByPreset(
        string $preset,
        string $query = '',
        int $topic = 0,
        string $author = '',
        int $days = 0,
        int $offset = 0,
        int $limit = 10
    ): array;

    /**
     * Search for comments matching the given query.
     *
     * @param string $query Search query
     * @param int $offset Pagination offset
     * @param int $limit Results per page
     * @return CommentSearchResult
     */
    public function searchComments(string $query, int $offset = 0, int $limit = 10): array;

    /**
     * Search for users matching the given query.
     *
     * @param string $query Search query
     * @param int $offset Pagination offset
     * @param int $limit Results per page
     * @return UserSearchResult
     */
    public function searchUsers(string $query, int $offset = 0, int $limit = 10): array;

    /**
     * Get all topics for the search filter dropdown.
     *
     * @return list<TopicRow>
     */
    public function getTopics(): array;

    /**
     * Get all categories for the search filter dropdown.
     *
     * @return list<CategoryRow>
     */
    public function getCategories(): array;

    /**
     * Get all authors for the search filter dropdown.
     *
     * @return array<int, string>
     */
    public function getAuthors(): array;

    /**
     * Get topic info by ID.
     *
     * @param int $topicId Topic ID
     * @return TopicInfoRow|null
     */
    public function getTopicInfo(int $topicId): ?array;
}
