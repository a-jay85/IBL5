<?php

declare(strict_types=1);

namespace Tests\WideUnit\Negotiation;

use Tests\WideUnit\WideUnitTestCase;
use Tests\WideUnit\Mocks\TestDataFactory;
use Negotiation\NegotiationDemandCalculator;
use Negotiation\NegotiationRepository;
use Negotiation\NegotiationService;
use Negotiation\NegotiationValidator;
use Repositories\Contracts\SalaryCapRepositoryInterface;

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
 * @covers \Negotiation\NegotiationService
 * @covers \Negotiation\NegotiationValidator
 * @covers \Negotiation\NegotiationDemandCalculator
 * @covers \Negotiation\NegotiationRepository
 */
class NegotiationWideUnitTest extends WideUnitTestCase
{
    private NegotiationService $service;
    private \Season\Season $mockSeason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSeason = self::createStub(\Season\Season::class);
        $this->mockSeason->phase = 'Regular Season';
        $this->mockSeason->endingYear = 2026;
        $this->mockSeason->beginningYear = 2025;
        $db = $this->mockDb;
        $commonRepo = self::createStub(SalaryCapRepositoryInterface::class);
        $this->service = new NegotiationService(
            $db,
            new NegotiationRepository($db, $commonRepo),
            new NegotiationValidator($db, $this->mockSeason),
            new NegotiationDemandCalculator($db, $commonRepo),
        );

        // Prevent any external calls during tests
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    protected function tearDown(): void
    {
        unset($this->service);
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
        $result = $this->service->processNegotiation(1, 'Miami Cyclones', 'ibl5');

        // Assert - Should render negotiation form with demands
        $this->assertStringContainsString('Test Player', $result);
        
        // Verify team performance query was executed
        $this->assertQueryExecuted('contract_wins');
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
        $result = $this->service->processNegotiation(1, 'Miami Cyclones', 'ibl5');

        // Assert - Should include cap space in form
        $this->assertStringContainsString('Test Player', $result);
        
        // Verify cap space query was executed (queries vw_current_salary view)
        $this->assertQueryExecuted("vw_current_salary");
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
        $result = $this->service->processNegotiation(1, 'Miami Cyclones', 'ibl5');

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
        $result = $this->service->processNegotiation(1, 'New York Knights', 'ibl5');

        // Assert - Should show ownership error
        $this->assertStringContainsString('not on your team', strtolower($result));
    }

    // ========== HELPER METHODS ==========

    private function setupSuccessfulNegotiationScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseNegotiationData(), [
                // Player is on user's team
                'teamid' => 1,
                'teamname' => 'Miami Cyclones',
                // Player is eligible for renegotiation (in last year of contract)
                'cy' => 2,
                'cyt' => 2,
                'salary_yr1' => 800,
                'salary_yr2' => 850,
                'salary_yr3' => 0,  // No salary in year 3 means eligible for renegotiation
                'salary_yr4' => 0,
                'salary_yr5' => 0,
                'salary_yr6' => 0,
                // Team performance
                'contract_wins' => 50,
                'contract_losses' => 32,
                'contract_avg_w' => 2500,
                'contract_avg_l' => 2000,
                // Cap space
                'Salary_Total' => 5000,
                'Salary_Cap' => 8250,
            ])
        ]);
    }

    private function setupFreeAgencyActiveScenario(): void
    {
        $this->mockSeason->phase = 'Free Agency';
        $this->mockDb->setMockData([
            array_merge($this->getBaseNegotiationData(), [
                // Player is on user's team
                'teamid' => 1,
                'teamname' => 'Miami Cyclones',
            ])
        ]);
    }

    private function setupPlayerOnDifferentTeamScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseNegotiationData(), [
                // Player is on DIFFERENT team
                'teamid' => 5,
                'teamname' => 'Miami Cyclones',
            ])
        ]);
    }

    /**
     * Base negotiation data for all scenarios
     *
     * @return array<string, mixed>
     */
    private function getBaseNegotiationData(): array
    {
        return array_merge(TestDataFactory::createPlayer([
            'pid' => 1,
            'name' => 'Test Player',
            'teamid' => 1,
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
            'salary_yr1' => 800,
            'salary_yr2' => 850,
            'salary_yr3' => 900,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            // Player preferences
            'winner' => 3,
            'tradition' => 3,
            'loyalty' => 3,
            'playing_time' => 3,
            'security' => 3,
            // Draft info for rookie option check
            'draftround' => 1,
            'draftyear' => 2020,
            // Money at position
            'money_committed_at_position' => 2000,
        ]);
    }
}
