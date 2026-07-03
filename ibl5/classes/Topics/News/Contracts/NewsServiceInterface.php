<?php

declare(strict_types=1);

namespace Topics\News\Contracts;

interface NewsServiceInterface
{
    /** @return array<int, array<string, mixed>> */
    public function getHomePageStories(int $limit, string $langClause): array;

    /** @return array<int, array<string, mixed>> */
    public function getTopicPageStories(int $topicId, int $limit, string $langClause): array;

    /** @return array<int, array<string, mixed>> */
    public function getCategoryPageStories(int $catId, int $limit, string $langClause): array;

    /** @return array<string, mixed>|null */
    public function getStory(int $sid): ?array;

    /** @return array<string, mixed>|null */
    public function getTopicForStory(int $sid): ?array;

    public function getTopicText(int $topicId): ?string;

    public function getCategoryTitle(int $catId): ?string;

    public function bumpAllTopics(): int;

    public function bumpCategory(int $catId): int;

    public function bumpStory(int $sid): int;

    public function normalizeStoryTime(int|string $time): int;

    /** @return array{intro:int,full:int,total:int} */
    public function computeByteCounts(string $hometext, string $bodytext): array;
}
