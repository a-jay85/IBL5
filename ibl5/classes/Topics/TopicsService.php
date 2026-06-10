<?php

declare(strict_types=1);

namespace Topics;

use Search\Contracts\SearchRepositoryInterface;
use Topics\Contracts\TopicsRepositoryInterface;
use Topics\Contracts\TopicsServiceInterface;

/**
 * Service for assembling Topics page data.
 *
 * @see TopicsServiceInterface
 */
class TopicsService implements TopicsServiceInterface
{
    private TopicsRepositoryInterface $topicsRepository;
    private SearchRepositoryInterface $searchRepository;

    public function __construct(
        \mysqli $db,
        string $prefix = 'nuke',
        ?TopicsRepositoryInterface $topicsRepository = null,
        ?SearchRepositoryInterface $searchRepository = null,
    ) {
        $this->topicsRepository = $topicsRepository ?? new TopicsRepository($db, $prefix);
        $this->searchRepository = $searchRepository ?? new \Search\SearchRepository($db, $prefix);
    }

    /**
     * @see TopicsServiceInterface::getPageData()
     */
    public function getPageData(bool $articleComm): array
    {
        return [
            'topics' => $this->topicsRepository->getTopicsWithArticles(),
            'searchFilters' => [
                'topics' => $this->searchRepository->getTopics(),
                'categories' => $this->searchRepository->getCategories(),
                'authors' => $this->searchRepository->getAuthors(),
                'articleComm' => $articleComm,
            ],
        ];
    }
}
