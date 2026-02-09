<?php

declare(strict_types=1);

namespace Tests\DraftHistory;

use DraftHistory\Contracts\DraftHistoryRepositoryInterface;
use DraftHistory\DraftHistoryRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DraftHistory\DraftHistoryRepository
 */
#[AllowMockObjectsWithoutExpectations]
class DraftHistoryRepositoryTest extends TestCase
{
    public function testImplementsRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new DraftHistoryRepository($mockDb);

        $this->assertInstanceOf(DraftHistoryRepositoryInterface::class, $repository);
    }

    public function testGetFirstDraftYearReturns1988(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new DraftHistoryRepository($mockDb);

        $this->assertSame(1988, $repository->getFirstDraftYear());
    }
}
