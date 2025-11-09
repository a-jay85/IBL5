<?php

use PHPUnit\Framework\TestCase;
use Negotiation\NegotiationProcessor;

/**
 * Tests for NegotiationProcessor
 * 
 * Note: Full integration testing of the processor requires a complete database mock
 * that properly handles Player::withPlayerID queries. Since the component classes
 * (NegotiationValidator, NegotiationDemandCalculator, NegotiationViewHelper) are 
 * thoroughly tested independently, these tests focus on verifying the processor
 * can be instantiated and has the correct public interface.
 * 
 * Detailed validation logic is tested in NegotiationValidatorTest.
 * Detailed demand calculation is tested in NegotiationDemandCalculatorTest.
 * Detailed view rendering is tested in NegotiationViewHelperTest.
 */
class NegotiationProcessorTest extends TestCase
{
    private $mockDb;
    private $processor;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->processor = new NegotiationProcessor($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->processor = null;
        $this->mockDb = null;
    }

    /**
     * @group processor
     * @group instantiation
     */
    public function testProcessorCanBeInstantiated()
    {
        // Assert
        $this->assertInstanceOf(NegotiationProcessor::class, $this->processor);
    }

    /**
     * @group processor
     * @group interface
     */
    public function testProcessorHasProcessNegotiationMethod()
    {
        // Assert
        $this->assertTrue(method_exists($this->processor, 'processNegotiation'));
    }

    /**
     * @group processor
     * @group integration
     */
    public function testProcessNegotiationReturnsString()
    {
        // Arrange - Empty mock will result in "Player not found" error
        $this->mockDb->setMockData([]);

        // Act
        $result = $this->processor->processNegotiation(1, 'Test Team', 'nuke');

        // Assert - Should return a string (error message)
        $this->assertIsString($result);
        $this->assertStringContainsString('Player not found', $result);
    }
}
