<?php

declare(strict_types=1);

namespace Search\Contracts;

/**
 * Interface for the Search view.
 *
 * Defines methods for rendering the search page including the form,
 * results, and pagination.
 *
 * @phpstan-import-type StoryResult from SearchServiceInterface
 * @phpstan-import-type CommentResult from SearchServiceInterface
 * @phpstan-import-type UserResult from SearchServiceInterface
 * @phpstan-import-type TopicRow from SearchServiceInterface
 * @phpstan-import-type CategoryRow from SearchServiceInterface
 *
 * @phpstan-type SearchPageData array{
 *     query: string,
 *     type: string,
 *     topic: int,
 *     category: int,
 *     author: string,
 *     days: int,
 *     min: int,
 *     offset: int,
 *     topicText: string,
 *     topics: list<TopicRow>,
 *     categories: list<CategoryRow>,
 *     authors: list<string>,
 *     results: list<StoryResult>|list<CommentResult>|list<UserResult>|null,
 *     hasMore: bool,
 *     isAdmin: bool,
 *     adminFile: string,
 *     articleComm: bool,
 *     error: string
 * }
 */
interface SearchViewInterface
{
    /**
     * Render the complete search page.
     *
     * @param SearchPageData $data All data needed to render the page
     * @return string Rendered HTML
     */
    public function render(array $data): string;
}
