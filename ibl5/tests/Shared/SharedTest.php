<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shared\Contracts\SharedRepositoryInterface;

/**
 * SharedTest - Tests for Shared service class
 *
 * Verifies Shared wrapper correctly delegates to SharedRepository
 * using prepared statements.
 */
class SharedTest extends TestCase
{
    private \Shared $shared;
    private \PHPUnit\Framework\MockObject\MockObject $mockRepository;

    protected function setUp(): void
    {
        // Create mock repository for testing Shared wrapper
        $this->mockRepository = $this->createMock(SharedRepositoryInterface::class);
        
        // Create Shared with null db and injected mock repository
        $this->shared = new \Shared(null, $this->mockRepository);
    }

    /**
     * Tests that getNumberOfTitles delegates to repository
     *
     * @test
     */
    public function testGetNumberOfTitlesDelegates(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('getNumberOfTitles')
            ->with('Lakers', 'Championship')
            ->willReturn(5);

        $result = $this->shared->getNumberOfTitles('Lakers', 'Championship');
        $this->assertEquals(5, $result);
    }

    /**
     * Tests that getCurrentOwnerOfDraftPick delegates with int conversion
     *
     * @test
     */
    public function testGetCurrentOwnerOfDraftPickDelegates(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('getCurrentOwnerOfDraftPick')
            ->with(2024, 1, 'Test Team')
            ->willReturn('New Team');

        $result = $this->shared->getCurrentOwnerOfDraftPick(2024, 1, 'Test Team');
        $this->assertEquals('New Team', $result);
    }

    /**
     * Tests getCurrentOwnerOfDraftPick with string parameters (backward compatibility)
     *
     * @test
     */
    public function testGetCurrentOwnerOfDraftPickStringParams(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('getCurrentOwnerOfDraftPick')
            ->with(2024, 1, 'Test Team')
            ->willReturn('Owner');

        // Pass strings - they should be converted to ints
        $result = $this->shared->getCurrentOwnerOfDraftPick('2024', '1', 'Test Team');
        $this->assertEquals('Owner', $result);
    }

    /**
     * Tests that isFreeAgencyModuleActive delegates to repository
     *
     * @test
     */
    public function testIsFreeAgencyModuleActiveDelegates(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('isFreeAgencyModuleActive')
            ->willReturn(1);

        $result = $this->shared->isFreeAgencyModuleActive();
        $this->assertEquals(1, $result);
    }

    /**
     * Tests isFreeAgencyModuleActive when module is inactive
     *
     * @test
     */
    public function testIsFreeAgencyModuleInactive(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('isFreeAgencyModuleActive')
            ->willReturn(0);

        $result = $this->shared->isFreeAgencyModuleActive();
        $this->assertEquals(0, $result);
    }

    /**
     * Tests isFreeAgencyModuleActive when module not found
     *
     * @test
     */
    public function testIsFreeAgencyModuleNotFound(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('isFreeAgencyModuleActive')
            ->willReturn(null);

        $result = $this->shared->isFreeAgencyModuleActive();
        $this->assertNull($result);
    }

    /**
     * Tests resetSimContractExtensionAttempts delegates and handles output
     *
     * @test
     */
    public function testResetSimContractExtensionAttemptsDelegates(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('resetSimContractExtensionAttempts');

        // Should not throw exception
        ob_start();
        $this->shared->resetSimContractExtensionAttempts();
        $output = ob_get_clean();
        
        // Verify output is generated
        $this->assertStringContainsString('Resetting sim contract extension attempts', $output);
        $this->assertStringContainsString('been reset', $output);
    }

    /**
     * Tests resetSimContractExtensionAttempts error handling
     *
     * @test
     */
    public function testResetSimContractExtensionAttemptsHandlesError(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('resetSimContractExtensionAttempts')
            ->willThrowException(new \RuntimeException('Database error', 1002));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        ob_start();
        $this->shared->resetSimContractExtensionAttempts();
        ob_get_clean();
    }
}
