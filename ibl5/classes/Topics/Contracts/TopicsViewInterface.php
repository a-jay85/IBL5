<?php

declare(strict_types=1);

namespace Topics\Contracts;

/**
 * Interface for the Topics view.
 *
 * Defines methods for rendering the topics listing page.
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
     * @return string Rendered HTML
     */
    public function render(array $topics, string $themePath): string;
}
