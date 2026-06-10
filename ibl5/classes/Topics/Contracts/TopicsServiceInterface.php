<?php

declare(strict_types=1);

namespace Topics\Contracts;

/**
 * Service interface for Topics module operations.
 *
 * Assembles the page-data for the Topics route: topic rows from the
 * repository plus search-filter options from the Search module.
 */
interface TopicsServiceInterface
{
    /**
     * Assemble the full Topics page data.
     *
     * @param bool $articleComm Whether article comments are enabled
     * @return array{
     *     topics: array<int, array{
     *         topicId: int,
     *         topicName: string,
     *         topicImage: string,
     *         topicText: string,
     *         storyCount: int,
     *         totalReads: int,
     *         recentArticles: array<int, array{sid: int, title: string, catId: int, catTitle: string}>
     *     }>,
     *     searchFilters: array{
     *         topics: list<array{topicId: int, topicText: string}>,
     *         categories: list<array{catId: int, title: string}>,
     *         authors: array<int, string>,
     *         articleComm: bool
     *     }
     * }
     */
    public function getPageData(bool $articleComm): array;
}
