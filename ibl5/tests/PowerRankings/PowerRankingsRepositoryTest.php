<?php

declare(strict_types=1);

namespace Tests\PowerRankings;

use PHPUnit\Framework\TestCase;
use PowerRankings\PowerRankingsRepository;
use PowerRankings\Contracts\PowerRankingsRepositoryInterface;

/**
 * PowerRankingsRepositoryTest - Tests for PowerRankingsRepository
 *
 * Note: Repository tests requiring database mocking are complex with BaseMysqliRepository.
 * These tests verify the interface implementation.
 *
 * @covers \PowerRankings\PowerRankingsRepository
 */
class PowerRankingsRepositoryTest extends TestCase
{
    public function testImplementsPowerRankingsRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new PowerRankingsRepository($mockDb);

        $this->assertInstanceOf(PowerRankingsRepositoryInterface::class, $repository);
    }
}
