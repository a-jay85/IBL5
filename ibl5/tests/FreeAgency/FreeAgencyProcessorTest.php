<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyProcessor;
use FreeAgency\Contracts\FreeAgencyDemandCalculatorInterface;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use Player\Player;

/**
 * Capturing repository stub — records what was passed to saveOffer().
 */
class CapturingRepository implements FreeAgencyRepositoryInterface
{
    /** @var array<string, mixed>|null */
    public ?array $lastSavedOffer = null;
    public bool $saveReturn = true;
    public bool $pendingMleExists = false;
    public bool $pendingLleExists = false;

    public function getExistingOffer(int $teamid, int $pid): ?array
    {
        return null;
    }

    public function deleteOffer(int $teamid, int $pid): int
    {
        return 0;
    }

    public function saveOffer(array $offerData): bool
    {
        $this->lastSavedOffer = $offerData;
        return $this->saveReturn;
    }

    public function getAllPlayersExcludingTeam(int $teamId): array
    {
        return [];
    }

    public function isPlayerAlreadySigned(int $playerId): bool
    {
        return false;
    }

    public function hasPendingMleOffer(int $teamid, int $excludePid): bool
    {
        return $this->pendingMleExists;
    }

    public function hasPendingLleOffer(int $teamid, int $excludePid): bool
    {
        return $this->pendingLleExists;
    }
}

/**
 * Stub calculator that returns known modifier/random/perceivedValue.
 */
class StubDemandCalculator implements FreeAgencyDemandCalculatorInterface
{
    private float $modifier;
    private int $random;

    public function __construct(float $modifier = 1.0, int $random = 0)
    {
        $this->modifier = $modifier;
        $this->random = $random;
    }

    public function setRandomFactor(?int $factor): void
    {
        // no-op for stub
    }

    /**
     * @return array{modifier: float, random: int, perceivedValue: float}
     */
    public function calculatePerceivedValue(
        int $offerAverage,
        string $teamName,
        Player $player,
        int $yearsInOffer
    ): array {
        $modRandom = (100 + $this->random) / 100;
        return [
            'modifier' => $this->modifier,
            'random' => $this->random,
            'perceivedValue' => $offerAverage * $this->modifier * $modRandom,
        ];
    }
}

/**
 * Tests for FreeAgencyProcessor — verifying modifier and random are stored correctly.
 *
 * The original freeagentoffer.php stored:
 *   modifier = float (~0.8-1.2), the combined 5-factor modifier
 *   random = int (-5 to +5), the random variance
 *   perceivedValue = float, offerAvg * modifier * ((100 + random) / 100)
 *
 * Copilot's refactor broke this by:
 *   modifier = (int)($perceivedValue / $offerAverage)  — cast to int, wrong formula
 *   random = 0  — hardcoded
 *
 * These tests use DI stubs to verify exact values passed to the repository.
 */
class FreeAgencyProcessorTest extends TestCase
{
    private \MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->injectGlobalMockDb();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function injectGlobalMockDb(): void
    {
        $mockDb = $this->mockDb;

        $GLOBALS['mysqli_db'] = new class ($mockDb) {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            public function prepare(string $query): \MockPreparedStatement
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            public function query(string $query): mixed
            {
                return $this->mockDb->sql_query($query);
            }

            public function real_escape_string(string $value): string
            {
                return addslashes($value);
            }
        };
    }

    // ================================================================
    // MODIFIER AND RANDOM STORAGE (THE CRITICAL BUG)
    // ================================================================

    public function testSaveOfferPassesFloatModifierToRepository(): void
    {
        $capturingRepo = new CapturingRepository();
        $calculator = new StubDemandCalculator(modifier: 1.15, random: 3);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost());

        $this->assertNotNull($capturingRepo->lastSavedOffer, 'Offer should have been saved');
        $this->assertIsFloat($capturingRepo->lastSavedOffer['modifier']);
        $this->assertEqualsWithDelta(1.15, $capturingRepo->lastSavedOffer['modifier'], 0.001);
    }

    public function testSaveOfferPassesActualRandomToRepository(): void
    {
        $capturingRepo = new CapturingRepository();
        $calculator = new StubDemandCalculator(modifier: 1.0, random: 3);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost());

        $this->assertNotNull($capturingRepo->lastSavedOffer);
        $this->assertSame(3, $capturingRepo->lastSavedOffer['random']);
    }

    public function testSaveOfferRandomIsNotHardcodedToZero(): void
    {
        $capturingRepo = new CapturingRepository();
        $calculator = new StubDemandCalculator(modifier: 1.0, random: -5);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost());

        $this->assertNotNull($capturingRepo->lastSavedOffer);
        $this->assertSame(-5, $capturingRepo->lastSavedOffer['random']);
    }

    public function testSaveOfferModifierIsNotIntegerTruncated(): void
    {
        $capturingRepo = new CapturingRepository();
        // 0.95 would become 0 with (int) cast
        $calculator = new StubDemandCalculator(modifier: 0.95, random: 0);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost());

        $this->assertNotNull($capturingRepo->lastSavedOffer);
        $this->assertEqualsWithDelta(0.95, $capturingRepo->lastSavedOffer['modifier'], 0.001);
    }

    public function testSaveOfferPerceivedValueMatchesFormula(): void
    {
        $capturingRepo = new CapturingRepository();
        $calculator = new StubDemandCalculator(modifier: 1.1, random: 3);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost());

        $this->assertNotNull($capturingRepo->lastSavedOffer);
        $offer = $capturingRepo->lastSavedOffer;

        // Offer average = 500 (single year offer of 500)
        $expectedPV = 500 * 1.1 * ((100 + 3) / 100);
        $this->assertEqualsWithDelta($expectedPV, $offer['perceivedValue'], 0.01);
    }

    public function testSaveOfferWithNeutralModifierAndZeroRandom(): void
    {
        $capturingRepo = new CapturingRepository();
        $calculator = new StubDemandCalculator(modifier: 1.0, random: 0);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost());

        $this->assertNotNull($capturingRepo->lastSavedOffer);
        $offer = $capturingRepo->lastSavedOffer;

        // perceivedValue should equal offerAverage exactly
        $this->assertEqualsWithDelta(500.0, $offer['perceivedValue'], 0.01);
        $this->assertEqualsWithDelta(1.0, $offer['modifier'], 0.001);
        $this->assertSame(0, $offer['random']);
    }

    // ================================================================
    // CONSTRUCTOR COMPATIBILITY
    // ================================================================

    public function testProcessorAcceptsDatabaseOnlyInConstructor(): void
    {
        $processor = new FreeAgencyProcessor($this->mockDb);
        $this->assertInstanceOf(FreeAgencyProcessor::class, $processor);
    }

    public function testProcessorAcceptsOptionalDIParams(): void
    {
        $calculator = new StubDemandCalculator();
        $repository = new CapturingRepository();

        $processor = new FreeAgencyProcessor($this->mockDb, $calculator, $repository);
        $this->assertInstanceOf(FreeAgencyProcessor::class, $processor);
    }

    // ================================================================
    // OFFER DELETION
    // ================================================================

    public function testDeleteOffersReturnsSuccess(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor = new FreeAgencyProcessor($this->mockDb);
        $result = $processor->deleteOffers('Test Team', 1);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function testDeleteOffersExecutesDeleteQuery(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor = new FreeAgencyProcessor($this->mockDb);
        $processor->deleteOffers('Test Team', 1);

        $queries = $this->mockDb->getExecutedQueries();
        $deleteQueries = array_filter($queries, static fn (string $q): bool => stripos($q, 'DELETE') !== false);
        $this->assertNotEmpty($deleteQueries);
    }

    // ================================================================
    // PENDING MLE/LLE REJECTION (ONE-AT-A-TIME RULE)
    // ================================================================

    public function testRejectsMLEOfferWhenRepositoryReportsPendingMLEOffer(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData([
            'has_mle' => 1,
            'has_lle' => 1,
        ])]);

        $capturingRepo = new CapturingRepository();
        $capturingRepo->pendingMleExists = true;

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            new StubDemandCalculator(),
            $capturingRepo,
        );

        $result = $processor->processOfferSubmission(array_merge($this->buildValidPost(), [
            'offerType' => 1, // 1-year MLE
        ]));

        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertStringContainsString('pending Mid-Level Exception offer', $result['message']);
        $this->assertNull($capturingRepo->lastSavedOffer, 'Second pending MLE must not be saved');
    }

    public function testRejectsLLEOfferWhenRepositoryReportsPendingLLEOffer(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData([
            'has_mle' => 1,
            'has_lle' => 1,
        ])]);

        $capturingRepo = new CapturingRepository();
        $capturingRepo->pendingLleExists = true;

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            new StubDemandCalculator(),
            $capturingRepo,
        );

        $result = $processor->processOfferSubmission(array_merge($this->buildValidPost(), [
            'offerType' => 7, // LLE
        ]));

        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertStringContainsString('pending Lower-Level Exception offer', $result['message']);
        $this->assertNull($capturingRepo->lastSavedOffer, 'Second pending LLE must not be saved');
    }

    // ================================================================
    // ALREADY-SIGNED REJECTION
    // ================================================================

    public function testRejectsOfferWhenPlayerAlreadySigned(): void
    {
        $this->mockDb->setMockData([array_merge($this->getCompletePlayerData(), [
            'cy' => 0,
            'salary_yr1' => 500, // signed this FA period
        ])]);

        $capturingRepo = new CapturingRepository();
        // Override isPlayerAlreadySigned to return true
        $signingRepo = new class extends CapturingRepository {
            public function isPlayerAlreadySigned(int $playerId): bool
            {
                return true;
            }
        };

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            new StubDemandCalculator(),
            $signingRepo,
        );

        $result = $processor->processOfferSubmission($this->buildValidPost());

        $this->assertFalse($result['success']);
        $this->assertSame('already_signed', $result['type']);
        $this->assertNull($signingRepo->lastSavedOffer, 'Should not save offer for signed player');
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * @return array<string, mixed>
     */
    private function buildValidPost(): array
    {
        return [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offeryear1' => 500,
            'offeryear2' => 0,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function getCompletePlayerData(array $overrides = []): array
    {
        return array_merge([
            'pid' => 1,
            'name' => 'Test Player',
            'firstname' => 'Test',
            'lastname' => 'Player',
            'nickname' => '',
            'teamname' => 'Free Agent',
            'teamid' => 0,
            'pos' => 'G',
            'position' => 'G',
            'age' => 25,
            'ordinal' => 1,
            'height' => 75,
            'weight' => 200,
            'htft' => 6,
            'htin' => 3,
            'wt' => 200,
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
            'retired' => 0,
            'injured' => 0,
            'signed' => 0,
            'droptime' => 0,
            'fa_loyalty' => 50,
            'fa_playing_time' => 50,
            'fa_play_for_winner' => 50,
            'fa_tradition' => 50,
            'fa_security' => 50,
            'loyalty' => 3,
            'playing_time' => 3,
            'winner' => 3,
            'tradition' => 3,
            'security' => 3,
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
            'r_ass' => 50,
            'r_tvr' => 50,
            'r_low' => 50,
            'r_def' => 50,
            'r_dis' => 50,
            'r_pss' => 50,
            'r_hnb' => 50,
            'r_ins' => 50,
            'oo' => 50,
            'od' => 50,
            'r_drive_off' => 50,
            'dd' => 50,
            'po' => 50,
            'pd' => 50,
            'r_trans_off' => 50,
            'td' => 50,
            'clutch' => 50,
            'consistency' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'ovr' => 75,
            'draftyear' => 2020,
            'draftround' => 1,
            'draftpickno' => 10,
            'draftedby' => 'Test Team',
            'draftedbycurrentname' => 'Test Team',
            'college' => 'Test University',
            // Team info fields
            'team_name' => 'Test Team',
            'team_city' => 'Test City',
            'teamid' => 1,
            'color1' => '#000000',
            'color2' => '#FFFFFF',
            'arena' => 'Test Arena',
            'capacity' => 20000,
            'owner_name' => 'Test Owner',
            'owner_email' => 'test@test.com',
            'discord_id' => null,
            'used_extension_this_chunk' => 0,
            'used_extension_this_season' => 0,
            'Salary_Total' => 5000,
            'Salary_Cap' => 8250,
            'Tax_Line' => 10000,
            'has_mle' => 0,
            'has_lle' => 0,
            'contract_wins' => 41,
            'contract_losses' => 41,
            'contract_avg_w' => 500,
            'contract_avg_l' => 500,
            'next_year_salary' => 0,
            'money_committed_at_position' => 0,
            // Season settings
            'freeAgencyNotificationsState' => 'Off',
            'Current Season Phase' => 'Free Agency',
        ], $overrides);
    }
}
