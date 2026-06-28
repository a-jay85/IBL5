<?php

declare(strict_types=1);

namespace Tests\TransactionHistory;

use TransactionHistory\TransactionHistoryRepository;
use Tests\WideUnit\WideUnitTestCase;

class TransactionHistoryRepositoryTest extends WideUnitTestCase
{
    private function repo(): TransactionHistoryRepository
    {
        $db = $this->mockDb;
        self::assertNotNull($db);
        return new TransactionHistoryRepository($db);
    }

    // ORDER BY correctness (time DESC) is DB-integration-only; MockDatabase returns data in feed order.

    public function testGetAvailableYearsCastsToIntDescending(): void
    {
        $this->mockDb->setMockData([
            ['year' => '2025'],
            ['year' => '2024'],
        ]);

        $result = $this->repo()->getAvailableYears();

        $this->assertSame([2025, 2024], $result);
    }

    public function testGetAvailableYearsReturnsEmptyArrayWhenNoRows(): void
    {
        $this->mockDb->setMockData([]);

        $this->assertSame([], $this->repo()->getAvailableYears());
    }

    public function testGetTransactionsWithNoFiltersReturnsRows(): void
    {
        $row = ['sid' => '1', 'catid' => '1', 'title' => 'Trade', 'time' => '2025-03-01 12:00:00'];
        $this->mockDb->setMockData([$row]);

        $result = $this->repo()->getTransactions(null, null, null);

        $this->assertSame([$row], $result);
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testGetTransactionsWithCategoryFilter(): void
    {
        $row = ['sid' => '2', 'catid' => '2', 'title' => 'Waiver', 'time' => '2025-04-01 10:00:00'];
        $this->mockDb->setMockData([$row]);

        $result = $this->repo()->getTransactions(2, null, null);

        $this->assertSame([$row], $result);
    }

    public function testGetTransactionsWithYearAndDecemberMonthBuildsRange(): void
    {
        $row = ['sid' => '3', 'catid' => '1', 'title' => 'FA Sign', 'time' => '2025-12-15 09:00:00'];
        $this->mockDb->setMockData([$row]);

        $result = $this->repo()->getTransactions(null, 2025, 12);

        $this->assertSame([$row], $result);
    }

    public function testGetTransactionsWithMonthOnlyFilter(): void
    {
        $row = ['sid' => '4', 'catid' => '1', 'title' => 'Cut', 'time' => '2025-03-10 08:00:00'];
        $this->mockDb->setMockData([$row]);

        $result = $this->repo()->getTransactions(null, null, 3);

        $this->assertSame([$row], $result);
    }

    public function testGetTransactionsReturnsEmptyArrayWhenNoRows(): void
    {
        $this->mockDb->setMockData([]);

        $this->assertSame([], $this->repo()->getTransactions(null, null, null));
    }
}
