<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use TransactionHistory\TransactionHistoryRepository;

/**
 * Tests TransactionHistoryRepository against real MariaDB.
 *
 * Note: nuke_stories uses MyISAM, so inserts are NOT rolled back by transaction.
 * Tests only read seed data — no writes to MyISAM tables.
 */
class TransactionHistoryRepositoryTest extends DatabaseTestCase
{
    private TransactionHistoryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TransactionHistoryRepository($this->db);
    }

    public function testGetAvailableYearsReturnsDistinctYears(): void
    {
        $years = $this->repo->getAvailableYears();

        self::assertNotEmpty($years);
        // Seed has entries from 2024 and 2023
        self::assertContains(2024, $years);
        self::assertContains(2023, $years);
        // Should be descending
        self::assertGreaterThanOrEqual($years[1], $years[0]);
    }

    public function testGetTransactionsReturnsAllWithNoFilters(): void
    {
        $transactions = $this->repo->getTransactions(null, null, null);

        self::assertNotEmpty($transactions);
        self::assertArrayHasKey('sid', $transactions[0]);
        self::assertArrayHasKey('catid', $transactions[0]);
        self::assertArrayHasKey('title', $transactions[0]);
        self::assertArrayHasKey('time', $transactions[0]);
    }

    public function testGetTransactionsFiltersByCategory(): void
    {
        $transactions = $this->repo->getTransactions(1, null, null);

        self::assertNotEmpty($transactions);
        foreach ($transactions as $row) {
            // nuke_stories.catid is INT — native types enabled, returns int
            self::assertSame(1, $row['catid']);
        }
    }

    public function testGetTransactionsFiltersByYear(): void
    {
        $transactions = $this->repo->getTransactions(null, 2024, null);

        self::assertNotEmpty($transactions);
        foreach ($transactions as $row) {
            self::assertStringStartsWith('2024-', $row['time']);
        }
    }

    public function testGetTransactionsFiltersByYearAndMonth(): void
    {
        $transactions = $this->repo->getTransactions(null, 2024, 3);

        self::assertNotEmpty($transactions);
        foreach ($transactions as $row) {
            self::assertStringStartsWith('2024-03-', $row['time']);
        }
    }

    public function testGetTransactionsReturnsEmptyForNoMatches(): void
    {
        $transactions = $this->repo->getTransactions(null, 1900, null);

        self::assertSame([], $transactions);
    }
}
