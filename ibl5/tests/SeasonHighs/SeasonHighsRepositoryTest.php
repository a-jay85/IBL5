<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;
use SeasonHighs\SeasonHighsRepository;

/**
 * @covers \SeasonHighs\SeasonHighsRepository
 */
#[AllowMockObjectsWithoutExpectations]
class SeasonHighsRepositoryTest extends TestCase
{
    public function testImplementsRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new SeasonHighsRepository($mockDb);

        $this->assertInstanceOf(SeasonHighsRepositoryInterface::class, $repository);
    }
}
