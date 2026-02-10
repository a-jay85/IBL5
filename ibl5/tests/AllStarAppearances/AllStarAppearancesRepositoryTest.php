<?php

declare(strict_types=1);

namespace Tests\AllStarAppearances;

use AllStarAppearances\AllStarAppearancesRepository;
use AllStarAppearances\Contracts\AllStarAppearancesRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AllStarAppearances\AllStarAppearancesRepository
 */
#[AllowMockObjectsWithoutExpectations]
class AllStarAppearancesRepositoryTest extends TestCase
{
    public function testImplementsRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new AllStarAppearancesRepository($mockDb);

        $this->assertInstanceOf(AllStarAppearancesRepositoryInterface::class, $repository);
    }
}
