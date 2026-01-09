<?php

declare(strict_types=1);

namespace Tests\CapInfo;

use PHPUnit\Framework\TestCase;
use CapInfo\CapInfoRepository;
use CapInfo\Contracts\CapInfoRepositoryInterface;

/**
 * CapInfoRepositoryTest - Tests for CapInfoRepository
 *
 * Note: Repository tests requiring database mocking are complex with BaseMysqliRepository.
 * These tests verify the interface implementation.
 *
 * @covers \CapInfo\CapInfoRepository
 */
class CapInfoRepositoryTest extends TestCase
{
    public function testImplementsCapInfoRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new CapInfoRepository($mockDb);

        $this->assertInstanceOf(CapInfoRepositoryInterface::class, $repository);
    }
}
