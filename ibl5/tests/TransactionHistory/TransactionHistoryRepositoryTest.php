<?php

declare(strict_types=1);

namespace Tests\TransactionHistory;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use TransactionHistory\Contracts\TransactionHistoryRepositoryInterface;
use TransactionHistory\TransactionHistoryRepository;

/**
 * @covers \TransactionHistory\TransactionHistoryRepository
 */
#[AllowMockObjectsWithoutExpectations]
class TransactionHistoryRepositoryTest extends TestCase
{
    public function testImplementsRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new TransactionHistoryRepository($mockDb);

        $this->assertInstanceOf(TransactionHistoryRepositoryInterface::class, $repository);
    }
}
