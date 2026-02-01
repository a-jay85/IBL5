<?php

declare(strict_types=1);

namespace Search\Contracts;

/**
 * Interface for the Search view.
 *
 * Defines methods for rendering the search page including the form,
 * results, and pagination.
 */
interface SearchViewInterface
{
    /**
     * Render the complete search page.
     *
     * @param array{
     *     query: string,
     *     type: string,
     *     topic: int,
     *     category: int,
     *     author: string,
     *     days: int,
     *     min: int,
     *     offset: int,
     *     topicText: string,
     *     topics: array,
     *     categories: array,
     *     authors: array,
     *     results: array|null,
     *     hasMore: bool,
     *     isAdmin: bool,
     *     adminFile: string,
     *     articleComm: bool,
     *     error: string
     * } $data All data needed to render the page
     * @return string Rendered HTML
     */
    public function render(array $data): string;
}
