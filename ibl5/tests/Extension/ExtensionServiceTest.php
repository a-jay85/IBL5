<?php

declare(strict_types=1);

namespace Tests\Extension;

use PHPUnit\Framework\TestCase;
use Extension\ExtensionService;
use Extension\Contracts\ExtensionProcessorInterface;
use Extension\Contracts\ExtensionRepositoryInterface;
use Extension\Contracts\ExtensionValidatorInterface;
use Extension\Contracts\ExtensionOfferEvaluatorInterface;
use Player\Player;
use Team\Team;

/**
 * ExtensionServiceTest - Tests for ExtensionService
 *
 * Uses DI with interface stubs for isolated unit testing.
 *
 * @covers \Extension\ExtensionService
 */
class ExtensionServiceTest extends TestCase
{
    private \MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $service = new ExtensionService($this->mockDb);

        $this->assertInstanceOf(ExtensionService::class, $service);
    }

    public function testImplementsProcessorInterface(): void
    {
        $service = new ExtensionService($this->mockDb);

        $this->assertInstanceOf(ExtensionProcessorInterface::class, $service);
    }

    // ============================================
    // ERROR HANDLING TESTS
    // ============================================

    public function testReturnsErrorWhenPlayerNotFound(): void
    {
        $service = new ExtensionService($this->mockDb);
        $extensionData = [
            'offer' => ['year1' => 1000, 'year2' => 1100, 'year3' => 1200, 'year4' => 0, 'year5' => 0],
            'playerID' => 99999,
        ];

        $result = $service->processExtension($extensionData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Player not found', $result['error']);
    }

    public function testReturnsErrorWhenTeamNotFound(): void
    {
        $this->mockDb->setMockData([$this->getFullMockData(['teamname' => null])]);

        $service = new ExtensionService($this->mockDb);
        $extensionData = [
            'playerID' => 1,
            'offer' => ['year1' => 1000, 'year2' => 1100, 'year3' => 1200, 'year4' => 0, 'year5' => 0],
        ];

        $result = $service->processExtension($extensionData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Team not found', $result['error']);
    }

    // ============================================
    // VALIDATION ROUTING
    // ============================================

    public function testValidationErrorRoutesBackCorrectly(): void
    {
        $this->mockDb->setMockData([$this->getFullMockData()]);

        $service = new ExtensionService($this->mockDb);
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerID' => 1,
            'offer' => ['year1' => 0, 'year2' => 1100, 'year3' => 1200, 'year4' => 0, 'year5' => 0],
        ];

        $result = $service->processExtension($extensionData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Year1', $result['error']);
    }

    // ============================================
    // RESULT STRUCTURE TESTS
    // ============================================

    public function testAcceptedExtensionResultStructure(): void
    {
        $this->mockDb->setMockData([$this->getFullMockData([
            'loyalty' => 5,
            'contract_wins' => 60,
            'contract_losses' => 22,
        ])]);

        $service = new ExtensionService($this->mockDb);
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerID' => 1,
            'offer' => ['year1' => 1000, 'year2' => 1100, 'year3' => 1200, 'year4' => 0, 'year5' => 0],
        ];

        $result = $service->processExtension($extensionData);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['accepted']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('offerValue', $result);
        $this->assertArrayHasKey('demandValue', $result);
        $this->assertArrayHasKey('modifier', $result);
        $this->assertArrayHasKey('extensionYears', $result);
        $this->assertArrayHasKey('offerInMillions', $result);
        $this->assertArrayHasKey('offerDetails', $result);
        $this->assertArrayHasKey('discordNotificationSent', $result);
        $this->assertArrayHasKey('discordChannel', $result);
        $this->assertSame(3, $result['extensionYears']);
    }

    public function testRejectedExtensionResultStructure(): void
    {
        $this->mockDb->setMockData([$this->getFullMockData([
            'loyalty' => 1,
            'winner' => 5,
            'tradition' => 5,
            'contract_wins' => 20,
            'contract_losses' => 62,
            'contract_avg_w' => 1200,
            'contract_avg_l' => 3800,
        ])]);

        $service = new ExtensionService($this->mockDb);
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerID' => 1,
            'offer' => ['year1' => 900, 'year2' => 950, 'year3' => 1000, 'year4' => 0, 'year5' => 0],
        ];

        $result = $service->processExtension($extensionData);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['accepted']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('refusalMessage', $result);
        $this->assertArrayHasKey('offerValue', $result);
        $this->assertArrayHasKey('demandValue', $result);
        $this->assertArrayHasKey('modifier', $result);
        $this->assertArrayHasKey('extensionYears', $result);
        $this->assertSame('refuses', $result['refusalMessage']);
    }

    // ============================================
    // EXTENSION YEAR COUNTING
    // ============================================

    public function testFourYearExtensionSetsCorrectYears(): void
    {
        $this->mockDb->setMockData([$this->getFullMockData([
            'loyalty' => 5,
            'contract_wins' => 60,
            'contract_losses' => 22,
        ])]);

        $service = new ExtensionService($this->mockDb);
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerID' => 1,
            'offer' => ['year1' => 1000, 'year2' => 1050, 'year3' => 1063, 'year4' => 1063, 'year5' => 0],
        ];

        $result = $service->processExtension($extensionData);

        $this->assertTrue($result['success']);
        $this->assertSame(4, $result['extensionYears']);
    }

    // ============================================
    // DEMAND NORMALIZATION
    // ============================================

    public function testNullDemandsDefaultsTo85PercentOfOfferAvg(): void
    {
        $this->mockDb->setMockData([$this->getFullMockData([
            'loyalty' => 5,
            'contract_wins' => 60,
            'contract_losses' => 22,
        ])]);

        $service = new ExtensionService($this->mockDb);
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerID' => 1,
            'offer' => ['year1' => 1000, 'year2' => 1100, 'year3' => 1200, 'year4' => 0, 'year5' => 0],
            'demands' => null,
        ];

        $result = $service->processExtension($extensionData);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['accepted']);
        $this->assertIsFloat($result['demandValue']);
        // Demand avg = 1100 * 0.85 = 935
        $this->assertGreaterThan(900.0, $result['demandValue']);
        $this->assertLessThan(970.0, $result['demandValue']);
    }

    public function testTotalYearsDemandsFormatIsNormalized(): void
    {
        $this->mockDb->setMockData([$this->getFullMockData([
            'loyalty' => 1,
            'winner' => 5,
            'contract_wins' => 15,
            'contract_losses' => 67,
            'contract_avg_w' => 1000,
            'contract_avg_l' => 4000,
        ])]);

        $service = new ExtensionService($this->mockDb);
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerID' => 1,
            'offer' => ['year1' => 900, 'year2' => 950, 'year3' => 1000, 'year4' => 0, 'year5' => 0],
            'demands' => ['total' => 6000, 'years' => 3],
        ];

        $result = $service->processExtension($extensionData);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['accepted']);
        $this->assertEqualsWithDelta(2000.0, $result['demandValue'], 1.0);
    }

    // ============================================
    // BACKWARD COMPATIBILITY
    // ============================================

    public function testExtensionProcessorAliasWorks(): void
    {
        $processor = new \Extension\ExtensionProcessor($this->mockDb);

        $this->assertInstanceOf(ExtensionService::class, $processor);
        $this->assertInstanceOf(ExtensionProcessorInterface::class, $processor);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFullMockData(array $overrides = []): array
    {
        return array_merge([
            'pid' => 1, 'ordinal' => 1,
            'name' => 'Test Player', 'nickname' => 'Tester',
            'age' => 25, 'teamid' => 1, 'teamname' => 'Miami Cyclones', 'pos' => 'SF',
            'r_fga' => 50, 'r_fgp' => 50, 'r_fta' => 50, 'r_ftp' => 50,
            'r_3ga' => 50, 'r_3gp' => 50, 'r_orb' => 50, 'r_drb' => 50,
            'r_ast' => 50, 'r_stl' => 50, 'r_tvr' => 50, 'r_blk' => 50,
            'r_foul' => 50,
            'oo' => 50, 'od' => 50, 'r_drive_off' => 50, 'dd' => 50,
            'po' => 50, 'pd' => 50, 'r_trans_off' => 50, 'td' => 50,
            'clutch' => 50, 'consistency' => 50,
            'talent' => 50, 'skill' => 50, 'intangibles' => 50,
            'draftyear' => 2018, 'draftround' => 1, 'draftpickno' => 15,
            'draftedby' => 'Miami Cyclones', 'draftedbycurrentname' => 'Miami Cyclones',
            'college' => 'Test University',
            'htft' => 6, 'htin' => 8, 'wt' => 210,
            'injured' => 0, 'retired' => 0, 'droptime' => 0,
            'exp' => 5, 'bird' => 2,
            'cy' => 1, 'cyt' => 1,
            'cy1' => 800, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0,
            'winner' => 3, 'tradition' => 3, 'loyalty' => 3, 'playing_time' => 3, 'security' => 3,
            'teamid' => 1, 'team_city' => 'Miami', 'team_name' => 'Cyclones',
            'color1' => 'Blue', 'color2' => 'White',
            'arena' => 'Test Arena', 'capacity' => 20000,
            'owner_name' => 'Test Owner', 'owner_email' => 'owner@test.com',
            'discord_id' => '123456',
            'has_mle' => 0, 'has_lle' => 0, 'leagueRecord' => '0-0',
            'used_extension_this_season' => 0, 'used_extension_this_chunk' => 0,
            'contract_wins' => 50, 'contract_losses' => 32,
            'contract_avg_w' => 2500, 'contract_avg_l' => 2000,
            'money_committed_at_position' => 0,
            'catid' => 1, 'counter' => 10, 'topicid' => 5,
        ], $overrides);
    }
}
