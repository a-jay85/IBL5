<?php

declare(strict_types=1);

namespace Tests\CapSpace;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use CapSpace\CapSpaceRepository;
use CapSpace\Contracts\CapSpaceRepositoryInterface;

/**
 * CapSpaceRepositoryTest - Tests for CapSpaceRepository
 *
 * Note: Repository tests requiring database mocking are complex with BaseMysqliRepository.
 * These tests verify the interface implementation.
 *
 * @covers \CapSpace\CapSpaceRepository
 */
#[AllowMockObjectsWithoutExpectations]
class CapSpaceRepositoryTest extends TestCase
{
    public function testImplementsCapSpaceRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new CapSpaceRepository($mockDb);

        $this->assertInstanceOf(CapSpaceRepositoryInterface::class, $repository);
    }
}
