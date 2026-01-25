<?php

declare(strict_types=1);

namespace Tests\DraftPickLocator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use DraftPickLocator\DraftPickLocatorRepository;
use DraftPickLocator\Contracts\DraftPickLocatorRepositoryInterface;

/**
 * DraftPickLocatorRepositoryTest - Tests for DraftPickLocatorRepository
 *
 * Note: Repository tests requiring database mocking are complex with BaseMysqliRepository.
 * These tests verify the interface implementation.
 *
 * @covers \DraftPickLocator\DraftPickLocatorRepository
 */
#[AllowMockObjectsWithoutExpectations]
class DraftPickLocatorRepositoryTest extends TestCase
{
    public function testImplementsDraftPickLocatorRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new DraftPickLocatorRepository($mockDb);

        $this->assertInstanceOf(DraftPickLocatorRepositoryInterface::class, $repository);
    }
}
