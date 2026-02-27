<?php

declare(strict_types=1);

namespace Tests\AwardHistory;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use AwardHistory\AwardHistoryService;
use AwardHistory\AwardHistoryValidator;
use AwardHistory\AwardHistoryRepository;
use AwardHistory\Contracts\AwardHistoryValidatorInterface;
use AwardHistory\Contracts\AwardHistoryRepositoryInterface;

/**
 * Tests for AwardHistoryService
 *
 * Verifies business logic for player awards search including validation
 * orchestration and result transformation.
 */
#[AllowMockObjectsWithoutExpectations]
final class AwardHistoryServiceTest extends TestCase
{
    /** @var AwardHistoryValidatorInterface&\PHPUnit\Framework\MockObject\MockObject */
    private AwardHistoryValidatorInterface $mockValidator;
    
    /** @var AwardHistoryRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private AwardHistoryRepositoryInterface $mockRepository;
    
    private AwardHistoryService $service;

    protected function setUp(): void
    {
        $this->mockValidator = $this->createMock(AwardHistoryValidatorInterface::class);
        $this->mockRepository = $this->createMock(AwardHistoryRepositoryInterface::class);
        
        $this->service = new AwardHistoryService($this->mockValidator, $this->mockRepository);
    }

    // ==================== search Tests ====================

    public function testSearchReturnsArrayWithRequiredKeys(): void
    {
        $this->mockValidator->method('validateSearchParams')
            ->willReturn(['name' => null, 'award' => null, 'year' => null, 'sortby' => 3]);
        
        $this->mockRepository->method('searchAwards')
            ->willReturn(['results' => [], 'count' => 0]);

        $result = $this->service->search(['aw_name' => 'Test']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('awards', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('params', $result);
    }

    public function testSearchWithEmptyParamsReturnsEmptyResults(): void
    {
        $this->mockValidator->method('validateSearchParams')
            ->willReturn(['name' => null, 'award' => null, 'year' => null, 'sortby' => 3]);

        $result = $this->service->search([]);

        $this->assertEmpty($result['awards']);
        $this->assertEquals(0, $result['count']);
    }

    public function testSearchWithValidParamsCallsRepository(): void
    {
        $validatedParams = ['name' => 'Johnson', 'award' => 'MVP', 'year' => 2025, 'sortby' => 1];
        $expectedResults = [
            ['year' => 2025, 'Award' => 'MVP', 'name' => 'Johnson'],
        ];

        $this->mockValidator->method('validateSearchParams')
            ->willReturn($validatedParams);
        
        $this->mockRepository->expects($this->once())
            ->method('searchAwards')
            ->with($validatedParams)
            ->willReturn(['results' => $expectedResults, 'count' => 1]);

        $rawParams = ['aw_name' => 'Johnson', 'aw_Award' => 'MVP', 'aw_year' => '2025', 'aw_sortby' => '1'];
        $result = $this->service->search($rawParams);

        $this->assertEquals(1, $result['count']);
        $this->assertCount(1, $result['awards']);
    }

    public function testSearchReturnsParamsForFormRepopulation(): void
    {
        $validatedParams = ['name' => 'Smith', 'award' => null, 'year' => 2024, 'sortby' => 3];

        $this->mockValidator->method('validateSearchParams')
            ->willReturn($validatedParams);
        
        $this->mockRepository->method('searchAwards')
            ->willReturn(['results' => [], 'count' => 0]);

        $result = $this->service->search(['aw_name' => 'Smith', 'aw_year' => '2024']);

        $this->assertEquals($validatedParams, $result['params']);
    }

    public function testSearchPassesRawParamsToValidator(): void
    {
        $rawParams = ['aw_name' => 'Test', 'aw_Award' => 'ROY'];

        $this->mockValidator->expects($this->once())
            ->method('validateSearchParams')
            ->with($rawParams)
            ->willReturn(['name' => 'Test', 'award' => 'ROY', 'year' => null, 'sortby' => 3]);
        
        $this->mockRepository->method('searchAwards')
            ->willReturn(['results' => [], 'count' => 0]);

        $this->service->search($rawParams);
    }

    // ==================== getSortOptions Tests ====================

    public function testGetSortOptionsReturnsAllOptions(): void
    {
        $options = $this->service->getSortOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey(1, $options);
        $this->assertArrayHasKey(2, $options);
        $this->assertArrayHasKey(3, $options);
        $this->assertEquals('Name', $options[1]);
        $this->assertEquals('Award Name', $options[2]);
        $this->assertEquals('Year', $options[3]);
    }
}
