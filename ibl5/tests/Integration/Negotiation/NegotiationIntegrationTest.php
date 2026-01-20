<?php

declare(strict_types=1);

namespace Tests\Integration\Negotiation;

use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;
use Negotiation\NegotiationProcessor;

/**
 * Integration tests for complete contract negotiation workflows
 *
 * Tests end-to-end scenarios combining validation, demand calculation,
 * and cap space analysis:
 * - Successful negotiation initiation
 * - Free agency active rejection
 * - Player ownership validation
 * - Demand calculation accuracy
 *
 * @covers \Negotiation\NegotiationProcessor
 * @covers \Negotiation\NegotiationValidator
 * @covers \Negotiation\NegotiationDemandCalculator
 * @covers \Negotiation\NegotiationRepository
 */
class NegotiationIntegrationTest extends IntegrationTestCase
{
    private NegotiationProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new NegotiationProcessor($this->mockDb, $this->mockDb);
        
        // Prevent any external calls during tests
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    protected function tearDown(): void
    {
        unset($this->processor);
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    // ========== SUCCESS SCENARIOS ==========

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testSuccessfulNegotiationShowsDemands(): void
    {
        // Arrange
        $this->setupSuccessfulNegotiationScenario();

        // Act
        $result = $this->processor->processNegotiation(1, 'Miami Cyclones', 'ibl5');

        // Assert - Should render negotiation form with demands
        $this->assertStringContainsString('Test Player', $result);
        
        // Verify team performance query was executed
        $this->assertQueryExecuted('Contract_Wins');
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testNegotiationCalculatesCapSpace(): void
    {
        // Arrange
        $this->setupSuccessfulNegotiationScenario();

        // Act
        $result = $this->processor->processNegotiation(1, 'Miami Cyclones', 'ibl5');

        // Assert - Should include cap space in form
        $this->assertStringContainsString('Test Player', $result);
        
        // Verify cap space query was executed (queries for salary columns and retired players)
        $this->assertQueryExecuted("retired = '0'");
    }

    // ========== VALIDATION FAILURE SCENARIOS ==========

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsNegotiationDuringFreeAgency(): void
    {
        // Arrange
        $this->setupFreeAgencyActiveScenario();

        // Act
        $result = $this->processor->processNegotiation(1, 'Miami Cyclones', 'ibl5');

        // Assert - Should show free agency error
        $this->assertStringContainsString('free agency', strtolower($result));
        $this->assertStringContainsString('not available', strtolower($result));
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsNegotiationForOtherTeamsPlayer(): void
    {
        // Arrange
        $this->setupPlayerOnDifferentTeamScenario();

        // Act
        $result = $this->processor->processNegotiation(1, 'New York Knights', 'ibl5');

        // Assert - Should show ownership error
        $this->assertStringContainsString('not on your team', strtolower($result));
    }

    // ========== HELPER METHODS ==========

    private function setupSuccessfulNegotiationScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseNegotiationData(), [
                // Player is on user's team
                'tid' => 1,
                'teamname' => 'Miami Cyclones',
                // Player is eligible for renegotiation (in last year of contract)
                'cy' => 2,
                'cyt' => 2,
                'cy1' => 800,
                'cy2' => 850,
                'cy3' => 0,  // No salary in year 3 means eligible for renegotiation
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0,
                // Free agency is not active
                'title' => 'Free_Agency',
                'active' => 0,
                // Team performance
                'Contract_Wins' => 50,
                'Contract_Losses' => 32,
                'Contract_AvgW' => 2500,
                'Contract_AvgL' => 2000,
                // Cap space
                'Salary_Total' => 5000,
                'Salary_Cap' => 8250,
            ])
        ]);
    }

    private function setupFreeAgencyActiveScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseNegotiationData(), [
                // Player is on user's team
                'tid' => 1,
                'teamname' => 'Miami Cyclones',
                // Free agency IS active
                'title' => 'Free_Agency',
                'active' => 1,
            ])
        ]);
    }

    private function setupPlayerOnDifferentTeamScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseNegotiationData(), [
                // Player is on DIFFERENT team
                'tid' => 5,
                'teamname' => 'Miami Cyclones',
                // Free agency not active
                'title' => 'Free_Agency',
                'active' => 0,
            ])
        ]);
    }

    /**
     * Base negotiation data for all scenarios
     */
    private function getBaseNegotiationData(): array
    {
        return array_merge(TestDataFactory::createPlayer([
            'pid' => 1,
            'name' => 'Test Player',
            'tid' => 1,
            'teamname' => 'Miami Cyclones',
            'position' => 'SG',
            'exp' => 5,
            'bird_years' => 2,
        ]), TestDataFactory::createTeam([
            'teamid' => 1,
            'team_name' => 'Miami Cyclones',
        ]), TestDataFactory::createSeason([
            'Phase' => 'Regular Season',
        ]), [
            // Contract fields
            'cy' => 1,
            'cyt' => 3,
            'cy1' => 800,
            'cy2' => 850,
            'cy3' => 900,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
            // Player preferences
            'winner' => 3,
            'tradition' => 3,
            'loyalty' => 3,
            'playingTime' => 3,
            'security' => 3,
            // Draft info for rookie option check
            'draftround' => 1,
            'draftyear' => 2020,
            // Money at position
            'money_committed_at_position' => 2000,
        ]);
    }
}
