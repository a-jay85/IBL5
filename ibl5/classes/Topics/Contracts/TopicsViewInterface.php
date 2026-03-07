<?php

declare(strict_types=1);

namespace Topics\Contracts;

/**
 * Interface for the Topics view.
 *
 * Defines methods for rendering the topics listing page.
 *
 * @phpstan-type SearchFilterData array{
 *     topics: list<array{topicId: int, topicText: string}>,
 *     categories: list<array{catId: int, title: string}>,
 *     authors: list<string>,
 *     articleComm: bool
 * }
 */
interface TopicsViewInterface
{
    /**
     * Render the full topics page.
     *
     * @param array<int, array{
     *     topicId: int,
     *     topicName: string,
     *     topicImage: string,
     *     topicText: string,
     *     storyCount: int,
     *     totalReads: int,
     *     recentArticles: array<int, array{
     *         sid: int,
     *         title: string,
     *         catId: int,
     *         catTitle: string
     *     }>
     * }> $topics Array of topic data with articles
     * @param string $themePath Path prefix for topic images
     * @param SearchFilterData $searchFilters Search filter data for the form
     * @return string Rendered HTML
     */
    public function render(array $topics, string $themePath, array $searchFilters): string;
}
