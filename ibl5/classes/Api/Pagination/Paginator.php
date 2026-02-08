<?php

declare(strict_types=1);

namespace Api\Pagination;

class Paginator
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;
    private const DEFAULT_ORDER = 'asc';

    private int $page;
    private int $perPage;
    private string $sort;
    private string $order;

    /**
     * @param array<string, string> $query            Query string parameters
     * @param string $defaultSort                      Default sort column
     * @param array<int, string> $allowedSortColumns   Whitelist of allowed sort columns
     */
    public function __construct(array $query, string $defaultSort, array $allowedSortColumns)
    {
        $this->page = max(1, (int) ($query['page'] ?? self::DEFAULT_PAGE));
        $rawPerPage = (int) ($query['per_page'] ?? self::DEFAULT_PER_PAGE);
        $this->perPage = max(1, min($rawPerPage, self::MAX_PER_PAGE));

        $requestedSort = $query['sort'] ?? $defaultSort;
        $this->sort = in_array($requestedSort, $allowedSortColumns, true) ? $requestedSort : $defaultSort;

        $requestedOrder = strtolower($query['order'] ?? self::DEFAULT_ORDER);
        $this->order = $requestedOrder === 'desc' ? 'desc' : 'asc';
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function getOrder(): string
    {
        return $this->order;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function getLimit(): int
    {
        return $this->perPage;
    }

    /**
     * Get the SQL ORDER BY clause (without "ORDER BY" prefix).
     */
    public function getOrderByClause(): string
    {
        return $this->sort . ' ' . strtoupper($this->order);
    }

    /**
     * Build pagination metadata for the response envelope.
     *
     * @return array{page: int, per_page: int, total: int, total_pages: int, sort: string, order: string}
     */
    public function getMeta(int $total): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $total,
            'total_pages' => $total > 0 ? (int) ceil($total / $this->perPage) : 0,
            'sort' => $this->sort,
            'order' => $this->order,
        ];
    }
}
