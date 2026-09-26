<?php

declare(strict_types=1);

namespace Search\Contracts;

/**
 * Interface for the Search view.
 *
 * Defines methods for rendering the search page including the form,
 * results, and pagination.
 *
 * @phpstan-import-type StoryResult from SearchRepositoryInterface
 * @phpstan-import-type CommentResult from SearchRepositoryInterface
 * @phpstan-import-type UserResult from SearchRepositoryInterface
 * @phpstan-import-type TopicRow from SearchRepositoryInterface
 * @phpstan-import-type CategoryRow from SearchRepositoryInterface
 *
 * @phpstan-type SearchPageData array{
 *     query: string,
 *     type: string,
 *     topic: int,
 *     category: int,
 *     author: string,
 *     days: int,
 *     preset: string,
 *     min: int,
 *     offset: int,
 *     topicText: string,
 *     topics: list<TopicRow>,
 *     categories: list<CategoryRow>,
 *     authors: list<string>,
 *     results: list<StoryResult>|list<CommentResult>|list<UserResult>|null,
 *     hasMore: bool,
 *     articleComm: bool,
 *     error: string
 * }
 */
interface SearchViewInterface
{
    /**
     * Preset dropdown options, value => label. '' means no preset.
     * Keys other than '' must be keys of SearchRepositoryInterface::PRESET_CATEGORY_IDS.
     * Shared by SearchView and Topics\TopicsView so both forms render identical options.
     */
    public const PRESET_OPTIONS = [
        '' => 'No Preset',
        SearchRepositoryInterface::PRESET_TRANSACTIONS => 'Transactions',
    ];

    /**
     * Render the complete search page.
     *
     * @param SearchPageData $data All data needed to render the page
     * @return string Rendered HTML
     */
    public function render(array $data): string;
}
