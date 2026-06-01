<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use PHPUnit\Framework\TestCase;
use Negotiation\NegotiationService;
use Negotiation\NegotiationRepository;
use Negotiation\NegotiationValidator;
use Negotiation\NegotiationDemandCalculator;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * NegotiationServiceTest - Tests for the negotiation workflow service
 *
 * Tests:
 * - Service instantiation
 * - Player loading and error handling
 * - Validation flow behavior
 */
class NegotiationServiceTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testServiceCanBeInstantiated(): void
    {
        $service = $this->buildService();

        $this->assertInstanceOf(NegotiationService::class, $service);
    }

    // ============================================
    // PROCESS NEGOTIATION ERROR HANDLING TESTS
    // ============================================

    public function testProcessNegotiationReturnsErrorForInvalidPlayer(): void
    {
        $service = $this->buildService();

        // Empty mock data means player won't be found
        $this->mockDb->setMockData([]);

        $result = $service->processNegotiation(999, 'Test Team', 'prefix');

        $this->assertStringContainsString('not found', $result);
    }

    public function testProcessNegotiationReturnsHtmlOutput(): void
    {
        $mockSeason = self::createStub(\Season\Season::class);
        $mockSeason->phase = 'Regular Season';
        $mockSeason->endingYear = 2026;
        $mockSeason->beginningYear = 2025;
        $service = $this->buildService(season: $mockSeason);

        // Setup complete player data
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $result = $service->processNegotiation(1, 'Test Team', 'prefix');

        $this->assertIsString($result);
        // Result should contain HTML markup
        $this->assertMatchesRegularExpression('/<.*>/', $result);
    }

    // ============================================
    // INTERFACE COMPLIANCE TESTS
    // ============================================

    public function testServiceImplementsCorrectInterface(): void
    {
        $service = $this->buildService();

        $this->assertInstanceOf(
            \Negotiation\Contracts\NegotiationServiceInterface::class,
            $service
        );
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function buildService(?\Season\Season $season = null): NegotiationService
    {
        $commonRepo = self::createStub(SalaryCapRepositoryInterface::class);
        return new NegotiationService(
            $this->mockDb,
            new NegotiationRepository($this->mockDb, $commonRepo),
            new NegotiationValidator($this->mockDb, $season),
            new NegotiationDemandCalculator($this->mockDb, $commonRepo),
        );
    }

    /**
     * Get complete player data with all required fields
     */
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function getCompletePlayerData(array $overrides = []): array
    {
        return array_merge([
            // Basic fields
            'pid' => 1,
            'name' => 'Test Player',
            'firstname' => 'Test',
            'lastname' => 'Player',
            'nickname' => '',
            'teamname' => 'Test Team',
            'teamid' => 1,
            'pos' => 'G',
            'position' => 'G',
            'age' => 25,
            'ordinal' => 1,
            // Physical attributes
            'height' => 75,
            'weight' => 200,
            'htft' => 6,
            'htin' => 3,
            'wt' => 200,
            // Contract fields
            'cy' => 0,
            'cyt' => 0,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'exp' => 3,
            'bird' => 0,
            'bird_years' => 0,
            // Status fields
            'retired' => 0,
            'injured' => 0,
            'signed' => 0,
            'droptime' => 0,
            // Free agency preferences
            'loyalty' => 50,
            'playing_time' => 50,
            'winner' => 50,
            'tradition' => 50,
            'security' => 50,
            // Base rating fields
            'r_fga' => 50,
            'r_fgp' => 50,
            'r_fta' => 50,
            'r_ftp' => 50,
            'r_3ga' => 50,
            'r_3gp' => 50,
            'r_orb' => 50,
            'r_drb' => 50,
            'r_ast' => 50,
            'r_stl' => 50,
            'r_tvr' => 50,
            'r_blk' => 50,
            'r_foul' => 50,
            // Position-based ratings
            'oo' => 50,
            'od' => 50,
            'r_drive_off' => 50,
            'dd' => 50,
            'po' => 50,
            'pd' => 50,
            'r_trans_off' => 50,
            'td' => 50,
            // Other ratings
            'clutch' => 50,
            'consistency' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'ovr' => 75,
            // Draft fields
            'draftyear' => 2020,
            'draftround' => 1,
            'draftpickno' => 10,
            'draftedby' => 'Test Team',
            'draftedbycurrentname' => 'Test Team',
            'college' => 'Test University',
            // Season info
            'Phase' => 'Regular Season',
            'Beginning_Year' => 2024,
            'Ending_Year' => 2025,
        ], $overrides);
    }
}
