<?php

declare(strict_types=1);

namespace Tests\FranchiseHistory;

use PHPUnit\Framework\TestCase;
use FranchiseHistory\FranchiseHistoryRepository;
use FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface;

/**
 * FranchiseHistoryRepositoryTest - Tests for FranchiseHistoryRepository
 *
 * Note: Repository tests requiring database mocking are complex with BaseMysqliRepository.
 * These tests verify the interface implementation.
 *
 * @covers \FranchiseHistory\FranchiseHistoryRepository
 */
class FranchiseHistoryRepositoryTest extends TestCase
{
    public function testImplementsFranchiseHistoryRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new FranchiseHistoryRepository($mockDb);

        $this->assertInstanceOf(FranchiseHistoryRepositoryInterface::class, $repository);
    }
}
