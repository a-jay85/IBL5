<?php

declare(strict_types=1);

namespace Topics\Contracts;

/**
 * Interface for the Topics repository.
 *
 * Defines methods for retrieving news topics with their associated
 * article counts and recent stories.
 */
interface TopicsRepositoryInterface
{
    /**
     * Get all active topics with story counts and recent articles.
     *
     * Returns topics ordered alphabetically by topic text, each including
     * the topic's metadata, aggregate statistics, and up to 10 most recent
     * articles.
     *
     * @return array<int, array{
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
     * }>
     */
    public function getTopicsWithArticles(): array;
}
