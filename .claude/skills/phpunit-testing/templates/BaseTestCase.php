<?php

declare(strict_types=1);

namespace Tests\ModuleName;

use PHPUnit\Framework\TestCase;
use ModuleName\ModuleService;
use ModuleName\Contracts\ModuleRepositoryInterface;

/**
 * Base test case template for IBL5 PHPUnit tests
 *
 * Demonstrates:
 * - Mock object setup with PHPDoc annotations
 * - Arrange/Act/Assert pattern
 * - Data providers for parameterized tests
 * - Behavior-focused testing through public APIs
 */
class ModuleServiceTest extends TestCase
{
    /** @var ModuleRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private ModuleRepositoryInterface $mockRepository;

    private ModuleService $service;

    protected function setUp(): void
    {
        // Create mock with proper PHPDoc annotation for IDE support
        $this->mockRepository = $this->createMock(ModuleRepositoryInterface::class);
        $this->service = new ModuleService($this->mockRepository);
    }

    // =========================================
    // Basic Success Case
    // =========================================

    public function testGetByIdReturnsRecordWhenFound(): void
    {
        // Arrange
        $expectedRecord = ['id' => 1, 'name' => 'Test Player', 'value' => 100];
        
        $this->mockRepository
            ->method('findById')
            ->with(1)
            ->willReturn($expectedRecord);

        // Act
        $result = $this->service->getById(1);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('Test Player', $result['name']);
        $this->assertEquals(100, $result['value']);
    }

    // =========================================
    // Not Found Case
    // =========================================

    public function testGetByIdReturnsNullWhenNotFound(): void
    {
        // Arrange
        $this->mockRepository
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        // Act
        $result = $this->service->getById(999);

        // Assert
        $this->assertNull($result);
    }

    // =========================================
    // Data Provider for Multiple Cases
    // =========================================

    /**
     * @dataProvider invalidInputProvider
     */
    public function testUpdateRejectsInvalidData(array $invalidData, string $expectedError): void
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedError);
        
        $this->service->update(1, $invalidData);
    }

    public static function invalidInputProvider(): array
    {
        return [
            'empty name' => [
                ['name' => '', 'value' => 100],
                'Name is required'
            ],
            'missing name' => [
                ['value' => 100],
                'Name is required'
            ],
            'null name' => [
                ['name' => null, 'value' => 100],
                'Name is required'
            ],
        ];
    }

    // =========================================
    // Collection/Array Result
    // =========================================

    public function testGetByTeamReturnsAllRecords(): void
    {
        // Arrange
        $expectedRecords = [
            ['id' => 1, 'name' => 'Player 1', 'tid' => 5],
            ['id' => 2, 'name' => 'Player 2', 'tid' => 5],
            ['id' => 3, 'name' => 'Player 3', 'tid' => 5],
        ];
        
        $this->mockRepository
            ->method('findByTeam')
            ->with(5)
            ->willReturn($expectedRecords);

        // Act
        $result = $this->service->getByTeam(5);

        // Assert
        $this->assertCount(3, $result);
        $this->assertEquals('Player 1', $result[0]['name']);
    }

    public function testGetByTeamReturnsEmptyArrayWhenNoRecords(): void
    {
        // Arrange
        $this->mockRepository
            ->method('findByTeam')
            ->with(999)
            ->willReturn([]);

        // Act
        $result = $this->service->getByTeam(999);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================
    // Update Success Case
    // =========================================

    public function testUpdateReturnsTrueWhenSuccessful(): void
    {
        // Arrange
        $validData = ['name' => 'Updated Name', 'value' => 200];
        
        $this->mockRepository
            ->method('update')
            ->with(1, $validData)
            ->willReturn(1); // 1 row affected

        // Act
        $result = $this->service->update(1, $validData);

        // Assert
        $this->assertTrue($result);
    }

    public function testUpdateReturnsFalseWhenNoRowsAffected(): void
    {
        // Arrange
        $validData = ['name' => 'Updated Name', 'value' => 200];
        
        $this->mockRepository
            ->method('update')
            ->with(999, $validData)
            ->willReturn(0); // 0 rows affected (ID not found)

        // Act
        $result = $this->service->update(999, $validData);

        // Assert
        $this->assertFalse($result);
    }
}
