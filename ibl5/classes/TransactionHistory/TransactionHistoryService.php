<?php

declare(strict_types=1);

namespace TransactionHistory;

use TransactionHistory\Contracts\TransactionHistoryRepositoryInterface;
use TransactionHistory\Contracts\TransactionHistoryViewInterface;

/**
 * Service layer for Transaction History module.
 *
 * Handles filter extraction, category/month mappings, and orchestrates
 * repository calls to assemble page data.
 *
 * @phpstan-import-type PageData from TransactionHistoryViewInterface
 */
class TransactionHistoryService
{
    /** @var array<int, string> Transaction category ID to label mapping */
    public const CATEGORIES = [
        1 => 'Waiver Pool Moves',
        2 => 'Trades',
        3 => 'Contract Extensions',
        8 => 'Free Agency',
        10 => 'Rookie Extension',
        14 => 'Position Changes',
    ];

    /** @var array<int, string> Month number to name mapping */
    public const MONTH_NAMES = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    private TransactionHistoryRepositoryInterface $repository;

    public function __construct(TransactionHistoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Extract and validate filter values from query parameters.
     *
     * Converts GET values to nullable ints: 0 or invalid values become null (no filter).
     *
     * @param array<string, string> $queryParams Raw $_GET parameters
     * @return array{categoryId: int|null, year: int|null, month: int|null}
     */
    public function extractFilters(array $queryParams): array
    {
        $category = isset($queryParams['cat']) ? (int) $queryParams['cat'] : 0;
        $year = isset($queryParams['year']) ? (int) $queryParams['year'] : 0;
        $month = isset($queryParams['month']) ? (int) $queryParams['month'] : 0;

        return [
            'categoryId' => ($category > 0 && isset(self::CATEGORIES[$category])) ? $category : null,
            'year' => ($year > 0) ? $year : null,
            'month' => ($month > 0 && $month <= 12) ? $month : null,
        ];
    }

    /**
     * Assemble all data needed to render the page.
     *
     * @param array<string, string> $queryParams Raw $_GET parameters
     * @return PageData
     */
    public function getPageData(array $queryParams): array
    {
        $filters = $this->extractFilters($queryParams);

        return [
            'transactions' => $this->repository->getTransactions(
                $filters['categoryId'],
                $filters['year'],
                $filters['month']
            ),
            'categories' => self::CATEGORIES,
            'availableYears' => $this->repository->getAvailableYears(),
            'monthNames' => self::MONTH_NAMES,
            'selectedCategory' => isset($queryParams['cat']) ? (int) $queryParams['cat'] : 0,
            'selectedYear' => isset($queryParams['year']) ? (int) $queryParams['year'] : 0,
            'selectedMonth' => isset($queryParams['month']) ? (int) $queryParams['month'] : 0,
        ];
    }
}
