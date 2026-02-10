<?php

declare(strict_types=1);

namespace Tests\GMContactList;

use GMContactList\Contracts\GMContactListRepositoryInterface;
use GMContactList\GMContactListRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GMContactList\GMContactListRepository
 */
#[AllowMockObjectsWithoutExpectations]
class GMContactListRepositoryTest extends TestCase
{
    public function testImplementsRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new GMContactListRepository($mockDb);

        $this->assertInstanceOf(GMContactListRepositoryInterface::class, $repository);
    }
}
