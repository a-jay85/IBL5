<?php

declare(strict_types=1);

namespace Tests\TransactionHistory;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use TransactionHistory\Contracts\TransactionHistoryRepositoryInterface;
use TransactionHistory\TransactionHistoryService;

/**
 * @covers \TransactionHistory\TransactionHistoryService
 */
#[AllowMockObjectsWithoutExpectations]
class TransactionHistoryServiceTest extends TestCase
{
    /** @var TransactionHistoryRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private TransactionHistoryRepositoryInterface $mockRepository;
    private TransactionHistoryService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(TransactionHistoryRepositoryInterface::class);
        $this->service = new TransactionHistoryService($this->mockRepository);
    }

    public function testExtractFiltersReturnsNullsForEmptyParams(): void
    {
        $result = $this->service->extractFilters([]);

        $this->assertNull($result['categoryId']);
        $this->assertNull($result['year']);
        $this->assertNull($result['month']);
    }

    public function testExtractFiltersReturnsCategoryWhenValid(): void
    {
        $result = $this->service->extractFilters(['cat' => '2']);

        $this->assertSame(2, $result['categoryId']);
    }

    public function testExtractFiltersReturnsNullForInvalidCategory(): void
    {
        $result = $this->service->extractFilters(['cat' => '99']);

        $this->assertNull($result['categoryId']);
    }

    public function testExtractFiltersReturnsYearWhenValid(): void
    {
        $result = $this->service->extractFilters(['year' => '2024']);

        $this->assertSame(2024, $result['year']);
    }

    public function testExtractFiltersReturnsNullForZeroYear(): void
    {
        $result = $this->service->extractFilters(['year' => '0']);

        $this->assertNull($result['year']);
    }

    public function testExtractFiltersReturnsMonthWhenValid(): void
    {
        $result = $this->service->extractFilters(['month' => '6']);

        $this->assertSame(6, $result['month']);
    }

    public function testExtractFiltersReturnsNullForInvalidMonth(): void
    {
        $result = $this->service->extractFilters(['month' => '13']);

        $this->assertNull($result['month']);
    }

    public function testGetPageDataReturnsExpectedStructure(): void
    {
        $this->mockRepository->method('getTransactions')->willReturn([]);
        $this->mockRepository->method('getAvailableYears')->willReturn([2024, 2023]);

        $result = $this->service->getPageData([]);

        $this->assertArrayHasKey('transactions', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('availableYears', $result);
        $this->assertArrayHasKey('monthNames', $result);
        $this->assertArrayHasKey('selectedCategory', $result);
        $this->assertArrayHasKey('selectedYear', $result);
        $this->assertArrayHasKey('selectedMonth', $result);
    }

    public function testCategoriesConstantHasSixEntries(): void
    {
        $this->assertCount(6, TransactionHistoryService::CATEGORIES);
    }

    public function testMonthNamesConstantHasTwelveEntries(): void
    {
        $this->assertCount(12, TransactionHistoryService::MONTH_NAMES);
    }
}
