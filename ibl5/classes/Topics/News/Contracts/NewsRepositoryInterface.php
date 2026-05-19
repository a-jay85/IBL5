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
}
