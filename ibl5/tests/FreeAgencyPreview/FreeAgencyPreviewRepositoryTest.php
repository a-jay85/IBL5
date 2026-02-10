<?php

declare(strict_types=1);

namespace Tests\FreeAgencyPreview;

use FreeAgencyPreview\Contracts\FreeAgencyPreviewRepositoryInterface;
use FreeAgencyPreview\FreeAgencyPreviewRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FreeAgencyPreview\FreeAgencyPreviewRepository
 */
#[AllowMockObjectsWithoutExpectations]
class FreeAgencyPreviewRepositoryTest extends TestCase
{
    public function testImplementsRepositoryInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $repository = new FreeAgencyPreviewRepository($mockDb);

        $this->assertInstanceOf(FreeAgencyPreviewRepositoryInterface::class, $repository);
    }
}
