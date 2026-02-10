<?php

declare(strict_types=1);

namespace Tests\ContractList;

use ContractList\ContractListRepository;
use ContractList\Contracts\ContractListRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ContractList\ContractListRepository
 */
#[AllowMockObjectsWithoutExpectations]
class ContractListRepositoryTest extends TestCase
{
    public function testImplementsRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new ContractListRepository($mockDb);

        $this->assertInstanceOf(ContractListRepositoryInterface::class, $repository);
    }
}
