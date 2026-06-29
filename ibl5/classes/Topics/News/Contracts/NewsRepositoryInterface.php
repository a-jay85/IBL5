<?php

declare(strict_types=1);

namespace Topics\News\Contracts;

interface NewsRepositoryInterface
{
    public function createNewsStory(
        int $categoryID,
        int $topicID,
        string $title,
        string $hometext,
        string $aid = 'Associated Press'
    ): int;

    public function getTopicIDByTeamName(string $teamName): ?int;

    public function getCategoryIDByTitle(string $categoryTitle): ?int;

    public function incrementCategoryCounter(string $categoryTitle): int;

    /** @return array<int, array<string, mixed>> */
    public function getHomePageStories(int $limit, string $langClause = ''): array;

    /** @return array<int, array<string, mixed>> */
    public function getStoriesByTopic(int $topicId, int $limit, string $langClause = ''): array;

    /** @return array<int, array<string, mixed>> */
    public function getStoriesByCategory(int $catId, int $limit, string $langClause = ''): array;

    /** @return array<string, mixed>|null */
    public function getStoryById(int $sid): ?array;

    /** @return array<string, mixed>|null */
    public function getTopicForStory(int $sid): ?array;

    public function getTopicText(int $topicId): ?string;

    public function getCategoryTitle(int $catId): ?string;

    public function incrementTopicCounterAll(): int;

    public function incrementCategoryCounterById(int $catId): int;

    public function incrementStoryCounter(int $sid): int;
}
