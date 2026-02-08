<?php

declare(strict_types=1);

namespace Tests\Api\Pagination;

use Api\Pagination\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    /** @var array<int, string> */
    private array $allowedColumns = ['name', 'ppg', 'age', 'position'];

    public function testDefaultValues(): void
    {
        $paginator = new Paginator([], 'name', $this->allowedColumns);

        $this->assertSame(1, $paginator->getPage());
        $this->assertSame(25, $paginator->getPerPage());
        $this->assertSame('name', $paginator->getSort());
        $this->assertSame('asc', $paginator->getOrder());
    }

    public function testCustomPageAndPerPage(): void
    {
        $paginator = new Paginator(['page' => '3', 'per_page' => '50'], 'name', $this->allowedColumns);

        $this->assertSame(3, $paginator->getPage());
        $this->assertSame(50, $paginator->getPerPage());
    }

    public function testPerPageClampsToMax100(): void
    {
        $paginator = new Paginator(['per_page' => '500'], 'name', $this->allowedColumns);

        $this->assertSame(100, $paginator->getPerPage());
    }

    public function testPerPageMinimumIs1(): void
    {
        $paginator = new Paginator(['per_page' => '0'], 'name', $this->allowedColumns);

        $this->assertSame(1, $paginator->getPerPage());
    }

    public function testNegativePerPageBecomesOne(): void
    {
        $paginator = new Paginator(['per_page' => '-5'], 'name', $this->allowedColumns);

        $this->assertSame(1, $paginator->getPerPage());
    }

    public function testPageMinimumIs1(): void
    {
        $paginator = new Paginator(['page' => '0'], 'name', $this->allowedColumns);

        $this->assertSame(1, $paginator->getPage());
    }

    public function testNegativePageBecomesOne(): void
    {
        $paginator = new Paginator(['page' => '-3'], 'name', $this->allowedColumns);

        $this->assertSame(1, $paginator->getPage());
    }

    public function testValidSortColumn(): void
    {
        $paginator = new Paginator(['sort' => 'ppg'], 'name', $this->allowedColumns);

        $this->assertSame('ppg', $paginator->getSort());
    }

    public function testInvalidSortColumnFallsBackToDefault(): void
    {
        $paginator = new Paginator(['sort' => 'sql_injection'], 'name', $this->allowedColumns);

        $this->assertSame('name', $paginator->getSort());
    }

    public function testDescOrder(): void
    {
        $paginator = new Paginator(['order' => 'desc'], 'name', $this->allowedColumns);

        $this->assertSame('desc', $paginator->getOrder());
    }

    public function testInvalidOrderDefaultsToAsc(): void
    {
        $paginator = new Paginator(['order' => 'random'], 'name', $this->allowedColumns);

        $this->assertSame('asc', $paginator->getOrder());
    }

    public function testOrderIsCaseInsensitive(): void
    {
        $paginator = new Paginator(['order' => 'DESC'], 'name', $this->allowedColumns);

        $this->assertSame('desc', $paginator->getOrder());
    }

    public function testGetOffset(): void
    {
        $paginator = new Paginator(['page' => '3', 'per_page' => '10'], 'name', $this->allowedColumns);

        $this->assertSame(20, $paginator->getOffset());
    }

    public function testGetOffsetFirstPage(): void
    {
        $paginator = new Paginator(['page' => '1', 'per_page' => '25'], 'name', $this->allowedColumns);

        $this->assertSame(0, $paginator->getOffset());
    }

    public function testGetLimit(): void
    {
        $paginator = new Paginator(['per_page' => '10'], 'name', $this->allowedColumns);

        $this->assertSame(10, $paginator->getLimit());
    }

    public function testGetOrderByClause(): void
    {
        $paginator = new Paginator(['sort' => 'ppg', 'order' => 'desc'], 'name', $this->allowedColumns);

        $this->assertSame('ppg DESC', $paginator->getOrderByClause());
    }

    public function testGetMetaWithTotal(): void
    {
        $paginator = new Paginator(['page' => '2', 'per_page' => '10', 'sort' => 'age', 'order' => 'desc'], 'name', $this->allowedColumns);
        $meta = $paginator->getMeta(55);

        $this->assertSame(2, $meta['page']);
        $this->assertSame(10, $meta['per_page']);
        $this->assertSame(55, $meta['total']);
        $this->assertSame(6, $meta['total_pages']);
        $this->assertSame('age', $meta['sort']);
        $this->assertSame('desc', $meta['order']);
    }

    public function testGetMetaZeroTotal(): void
    {
        $paginator = new Paginator([], 'name', $this->allowedColumns);
        $meta = $paginator->getMeta(0);

        $this->assertSame(0, $meta['total_pages']);
    }

    public function testGetMetaTotalPagesExactDivision(): void
    {
        $paginator = new Paginator(['per_page' => '10'], 'name', $this->allowedColumns);
        $meta = $paginator->getMeta(30);

        $this->assertSame(3, $meta['total_pages']);
    }

    public function testGetMetaTotalPagesWithRemainder(): void
    {
        $paginator = new Paginator(['per_page' => '10'], 'name', $this->allowedColumns);
        $meta = $paginator->getMeta(31);

        $this->assertSame(4, $meta['total_pages']);
    }
}
