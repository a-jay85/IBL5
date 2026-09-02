<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyProcessor;
use FreeAgency\Contracts\FreeAgencyCapCalculatorFactoryInterface;
use FreeAgency\Contracts\FreeAgencyCapCalculatorInterface;
use FreeAgency\Contracts\FreeAgencyEntityLoaderInterface;
use FreeAgency\Contracts\FreeAgencyMarketDemandCalculatorInterface;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use Player\Player;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Team\Team;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\MockPreparedStatement;

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
class StubDemandCalculator implements FreeAgencyMarketDemandCalculatorInterface
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
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
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
            private MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            public function prepare(string $query): MockPreparedStatement
            {
                return new MockPreparedStatement($this->mockDb, $query);
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
            self::createStub(TeamIdentityRepositoryInterface::class),
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost(), 'Test Team');

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
            self::createStub(TeamIdentityRepositoryInterface::class),
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost(), 'Test Team');

        $this->assertNotNull($capturingRepo->lastSavedOffer);
        $this->assertSame(3, $capturingRepo->lastSavedOffer['random']);
    }

    public function testSaveOfferRandomIsNotHardcodedToZero(): void
    {
        $capturingRepo = new CapturingRepository();
        $calculator = new StubDemandCalculator(modifier: 1.0, random: -5);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost(), 'Test Team');

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
            self::createStub(TeamIdentityRepositoryInterface::class),
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost(), 'Test Team');

        $this->assertNotNull($capturingRepo->lastSavedOffer);
        $this->assertEqualsWithDelta(0.95, $capturingRepo->lastSavedOffer['modifier'], 0.001);
    }

    public function testSaveOfferPerceivedValueMatchesFormula(): void
    {
        $capturingRepo = new CapturingRepository();
        $calculator = new StubDemandCalculator(modifier: 1.1, random: 3);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost(), 'Test Team');

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
            self::createStub(TeamIdentityRepositoryInterface::class),
            $calculator,
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor->processOfferSubmission($this->buildValidPost(), 'Test Team');

        $this->assertNotNull($capturingRepo->lastSavedOffer);
        $offer = $capturingRepo->lastSavedOffer;

        // perceivedValue should equal offerAverage exactly
        $this->assertEqualsWithDelta(500.0, $offer['perceivedValue'], 0.01);
        $this->assertEqualsWithDelta(1.0, $offer['modifier'], 0.001);
        $this->assertSame(0, $offer['random']);
    }

    // ================================================================
    // IDOR — D-07: acting team comes from the verified session param,
    // never from POST. The processor looks the team up via
    // Team::initialize($db, $verifiedTeamName); the bound WHERE value is
    // recorded by the mock, so we assert the lookup used the verified name
    // and the POST-supplied name never reaches the database.
    // (lastSavedOffer['teamName'] is NOT a valid probe here: the mock row
    // hardcodes team_name='Test Team' for any lookup.)
    // ================================================================

    public function testProcessOfferSubmissionSavesUnderVerifiedTeamNotPostTeam(): void
    {
        $capturingRepo = new CapturingRepository();

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            new StubDemandCalculator(),
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        // Attacker tampers the hidden POST team field to a victim team.
        $post = array_merge($this->buildValidPost(), ['teamname' => 'Victim Team']);
        $processor->processOfferSubmission($post, 'Test Team');

        $queries = $this->mockDb->getExecutedQueries();

        // The verified session team is the one looked up.
        $verifiedLookup = array_filter(
            $queries,
            static fn (string $q): bool => stripos($q, 'ibl_team_info') !== false
                && strpos($q, "'Test Team'") !== false
        );
        $this->assertNotEmpty($verifiedLookup, 'Team lookup must use the verified session team name');

        // The POST-supplied team name must never reach the database.
        $victimReferences = array_filter(
            $queries,
            static fn (string $q): bool => strpos($q, 'Victim Team') !== false
        );
        $this->assertEmpty($victimReferences, 'POST-supplied team name must be discarded (IDOR D-07)');

        $this->assertNotNull($capturingRepo->lastSavedOffer, 'A valid offer should still be saved');
    }

    public function testProcessOfferSubmissionUsesVerifiedTeamWhenPostTeamAbsent(): void
    {
        $capturingRepo = new CapturingRepository();

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            new StubDemandCalculator(),
            $capturingRepo,
        );

        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        // POST carries no team field at all — the verified param drives the lookup.
        $post = $this->buildValidPost();
        unset($post['teamname']);
        $processor->processOfferSubmission($post, 'Test Team');

        $queries = $this->mockDb->getExecutedQueries();
        $verifiedLookup = array_filter(
            $queries,
            static fn (string $q): bool => stripos($q, 'ibl_team_info') !== false
                && strpos($q, "'Test Team'") !== false
        );
        $this->assertNotEmpty($verifiedLookup, 'Verified team must be used even when POST team is absent');
        $this->assertNotNull($capturingRepo->lastSavedOffer);
    }

    // ================================================================
    // OFFER DELETION
    // ================================================================

    public function testDeleteOffersReturnsSuccess(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor = new FreeAgencyProcessor($this->mockDb, self::createStub(TeamIdentityRepositoryInterface::class));
        $result = $processor->deleteOffers('Test Team', 1);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function testDeleteOffersExecutesDeleteQuery(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $processor = new FreeAgencyProcessor($this->mockDb, self::createStub(TeamIdentityRepositoryInterface::class));
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
            self::createStub(TeamIdentityRepositoryInterface::class),
            new StubDemandCalculator(),
            $capturingRepo,
        );

        $result = $processor->processOfferSubmission(array_merge($this->buildValidPost(), [
            'offerType' => 1, // 1-year MLE
        ]), 'Test Team');

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
            self::createStub(TeamIdentityRepositoryInterface::class),
            new StubDemandCalculator(),
            $capturingRepo,
        );

        $result = $processor->processOfferSubmission(array_merge($this->buildValidPost(), [
            'offerType' => 7, // LLE
        ]), 'Test Team');

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

        // Override isPlayerAlreadySigned to return true
        $signingRepo = new class extends CapturingRepository {
            public function isPlayerAlreadySigned(int $playerId): bool
            {
                return true;
            }
        };

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            new StubDemandCalculator(),
            $signingRepo,
        );

        $result = $processor->processOfferSubmission($this->buildValidPost(), 'Test Team');

        $this->assertFalse($result['success']);
        $this->assertSame('already_signed', $result['type']);
        $this->assertNull($signingRepo->lastSavedOffer, 'Should not save offer for signed player');
    }

    // ================================================================
    // DI CUT-OVER — Phase 6 new test cases
    // ================================================================

    public function testOverCapOfferRejectedViaInjectedCapCalculatorFactory(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $player = Player::withPlayerID($this->mockDb, 1);
        $team = Team::initialize($this->mockDb, 'Test Team');

        /** @var FreeAgencyCapCalculatorInterface&\PHPUnit\Framework\MockObject\Stub $calcStub */
        $calcStub = self::createStub(FreeAgencyCapCalculatorInterface::class);
        $calcStub->method('calculateTeamCapMetrics')->willReturn([
            'totalSalaries' => [0 => 8150],
            'softCapSpace'  => [0 => 100],
            'hardCapSpace'  => [0 => -1150],
            'rosterSpots'   => [0 => 1],
        ]);

        /** @var FreeAgencyCapCalculatorFactoryInterface&\PHPUnit\Framework\MockObject\Stub $factoryStub */
        $factoryStub = self::createStub(FreeAgencyCapCalculatorFactoryInterface::class);
        $factoryStub->method('forTeam')->willReturn($calcStub);

        /** @var FreeAgencyEntityLoaderInterface&\PHPUnit\Framework\MockObject\Stub $loaderStub */
        $loaderStub = self::createStub(FreeAgencyEntityLoaderInterface::class);
        $loaderStub->method('loadPlayer')->willReturn($player);
        $loaderStub->method('loadTeam')->willReturn($team);

        $capturingRepo = new CapturingRepository();

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            repository: $capturingRepo,
            entityLoader: $loaderStub,
            capCalculatorFactory: $factoryStub,
        );

        // offer1=500 exceeds softCapSpace[0]=100 → soft-cap validation rejects
        $result = $processor->processOfferSubmission($this->buildValidPost(), 'Test Team');

        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertStringContainsString('cap space', $result['message']);
        $this->assertNull($capturingRepo->lastSavedOffer, 'Over-cap offer must not be saved');
    }

    public function testNonNumericPlayerIdIsCoercedToZero(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $capturingRepo = new CapturingRepository();

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            repository: $capturingRepo,
        );

        // 'not-a-number' is not numeric → is_numeric() returns false → playerID coerced to 0
        $result = $processor->processOfferSubmission(
            ['playerID' => 'not-a-number', 'offerType' => 1, 'offeryear1' => 100],
            'Test Team'
        );

        $this->assertSame(0, $result['playerID']);
    }

    public function testUnknownTeamNameReachesValidationRejectionNotAFatal(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $player = Player::withPlayerID($this->mockDb, 1);

        // Build an unpopulated Team via array identifier — avoids the DB query that
        // would throw RuntimeException("Team not found: …") in the pre-DI code path.
        $emptyTeam = Team::initialize($this->mockDb, [
            'teamid'                    => 0,
            'team_name'                 => '',
            'team_city'                 => '',
            'color1'                    => '',
            'color2'                    => '',
            'arena'                     => '',
            'capacity'                  => 0,
            'owner_name'                => '',
            'owner_email'               => '',
            'discord_id'                => null,
        ]);

        /** @var FreeAgencyEntityLoaderInterface&\PHPUnit\Framework\MockObject\Stub $loaderStub */
        $loaderStub = self::createStub(FreeAgencyEntityLoaderInterface::class);
        $loaderStub->method('loadPlayer')->willReturn($player);
        $loaderStub->method('loadTeam')->willReturn($emptyTeam);

        // Stub the cap factory so the empty team's teamid=0 never reaches real DB queries.
        /** @var FreeAgencyCapCalculatorInterface&\PHPUnit\Framework\MockObject\Stub $calcStub */
        $calcStub = self::createStub(FreeAgencyCapCalculatorInterface::class);
        $calcStub->method('calculateTeamCapMetrics')->willReturn([
            'totalSalaries' => [0 => 0],
            'softCapSpace'  => [0 => 5000],
            'hardCapSpace'  => [0 => 7000],
            'rosterSpots'   => [0 => 15],
        ]);

        /** @var FreeAgencyCapCalculatorFactoryInterface&\PHPUnit\Framework\MockObject\Stub $factoryStub */
        $factoryStub = self::createStub(FreeAgencyCapCalculatorFactoryInterface::class);
        $factoryStub->method('forTeam')->willReturn($calcStub);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            repository: new CapturingRepository(),
            entityLoader: $loaderStub,
            capCalculatorFactory: $factoryStub,
        );

        // The unknown team is handled via the validation/save path — no RuntimeException thrown.
        $result = $processor->processOfferSubmission($this->buildValidPost(), 'Unknown Team');

        $this->assertIsArray($result);
    }

    // ================================================================
    // AUTHZ VERDICT — characterization of the AUTHORIZED path (Phase 2)
    // These pin the authorized half so a later over-broad gate cannot pass
    // silently. Both pass before and after the Phase 2 gate is added.
    // ================================================================

    public function testProcessOfferSubmissionLoadsPlayerForAuthorizedTeam(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $player = Player::withPlayerID($this->mockDb, 1);
        $team = Team::initialize($this->mockDb, 'Test Team');

        $loaderMock = $this->createMock(FreeAgencyEntityLoaderInterface::class);
        $loaderMock->expects($this->once())->method('loadPlayer')->with(7)->willReturn($player);
        $loaderMock->method('loadTeam')->willReturn($team);

        /** @var FreeAgencyCapCalculatorInterface&\PHPUnit\Framework\MockObject\Stub $calcStub */
        $calcStub = self::createStub(FreeAgencyCapCalculatorInterface::class);
        $calcStub->method('calculateTeamCapMetrics')->willReturn([
            'totalSalaries' => [0 => 0],
            'softCapSpace'  => [0 => 5000],
            'hardCapSpace'  => [0 => 7000],
            'rosterSpots'   => [0 => 15],
        ]);
        /** @var FreeAgencyCapCalculatorFactoryInterface&\PHPUnit\Framework\MockObject\Stub $factoryStub */
        $factoryStub = self::createStub(FreeAgencyCapCalculatorFactoryInterface::class);
        $factoryStub->method('forTeam')->willReturn($calcStub);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            new StubDemandCalculator(),
            new CapturingRepository(),
            entityLoader: $loaderMock,
            capCalculatorFactory: $factoryStub,
        );

        // An authorized (real) team must reach the entity loader — the gate is not over-broad.
        $processor->processOfferSubmission(
            ['playerID' => 7, 'offeryear1' => 500, 'offerType' => 0],
            'Chicago Bulls'
        );
    }

    public function testDeleteOffersDeletesForAuthorizedTeam(): void
    {
        $repoMock = $this->createMock(FreeAgencyRepositoryInterface::class);
        $repoMock->expects($this->once())->method('deleteOffer');

        $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTidFromTeamname')->willReturn(5);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            $commonRepo,
            repository: $repoMock,
        );

        $result = $processor->deleteOffers('Chicago Bulls', 42);

        $this->assertTrue($result['success']);
    }

    // ================================================================
    // AUTHZ VERDICT — refused AND no mutation (Phase 4)
    // The gate is the first statement of each public method; each test
    // asserts never() on the mutation method AND on the read that would
    // otherwise follow, so removing the gate turns the test red.
    // ================================================================

    public function testProcessOfferSubmissionRefusesNullTeamWithoutSavingOffer(): void
    {
        [$processor, $repository, $entityLoader, $commonRepo] = $this->buildProcessorWithMocks();
        $repository->expects($this->never())->method('saveOffer');
        $entityLoader->expects($this->never())->method('loadPlayer');
        $commonRepo->expects($this->never())->method('getTidFromTeamname');

        $result = $processor->processOfferSubmission(['playerID' => 42, 'offeryear1' => 5000000], null);

        $this->assertFalse($result['success']);
        $this->assertSame('unauthorized', $result['type']);
        $this->assertSame(0, $result['playerID']);
    }

    public function testProcessOfferSubmissionRefusesFreeAgentsPseudoTeamWithoutSavingOffer(): void
    {
        [$processor, $repository, $entityLoader, $commonRepo] = $this->buildProcessorWithMocks();
        $repository->expects($this->never())->method('saveOffer');
        $entityLoader->expects($this->never())->method('loadPlayer');
        $commonRepo->expects($this->never())->method('getTidFromTeamname');

        // Reference the constant, never a literal: renaming the pseudo-team must not un-gate this path.
        $result = $processor->processOfferSubmission(
            ['playerID' => 42, 'offeryear1' => 5000000],
            \League\League::FREE_AGENTS_TEAM_NAME
        );

        $this->assertFalse($result['success']);
        $this->assertSame('unauthorized', $result['type']);
        $this->assertSame(0, $result['playerID']);
    }

    public function testProcessOfferSubmissionRefusesEmptyTeamWithoutSavingOffer(): void
    {
        [$processor, $repository, $entityLoader, $commonRepo] = $this->buildProcessorWithMocks();
        $repository->expects($this->never())->method('saveOffer');
        $entityLoader->expects($this->never())->method('loadPlayer');
        $commonRepo->expects($this->never())->method('getTidFromTeamname');

        $result = $processor->processOfferSubmission(['playerID' => 42, 'offeryear1' => 5000000], '');

        $this->assertFalse($result['success']);
        $this->assertSame('unauthorized', $result['type']);
        $this->assertSame(0, $result['playerID']);
    }

    public function testProcessOfferSubmissionIgnoresTeamNameSuppliedInPostData(): void
    {
        [$processor, $repository, $entityLoader, $commonRepo] = $this->buildProcessorWithMocks();
        $repository->expects($this->never())->method('saveOffer');
        $entityLoader->expects($this->never())->method('loadPlayer');
        $commonRepo->expects($this->never())->method('getTidFromTeamname');

        // IDOR D-07: a spoofed team in the request body must not rescue a request whose
        // session-derived acting team is absent.
        $result = $processor->processOfferSubmission(
            ['playerID' => 42, 'teamName' => 'Chicago Bulls', 'team' => 'Chicago Bulls'],
            null
        );

        $this->assertFalse($result['success']);
        $this->assertSame('unauthorized', $result['type']);
    }

    public function testDeleteOffersRefusesNullTeamWithoutDeleting(): void
    {
        [$processor, $repository, $entityLoader, $commonRepo] = $this->buildProcessorWithMocks();
        $repository->expects($this->never())->method('deleteOffer');
        $commonRepo->expects($this->never())->method('getTidFromTeamname');
        $entityLoader->expects($this->never())->method('loadPlayer');

        $result = $processor->deleteOffers(null, 42);

        $this->assertFalse($result['success']);
        $this->assertIsString($result['error']);
        $this->assertNotSame('', $result['error']);
    }

    public function testDeleteOffersRefusesFreeAgentsPseudoTeamWithoutDeleting(): void
    {
        [$processor, $repository, $entityLoader, $commonRepo] = $this->buildProcessorWithMocks();
        $repository->expects($this->never())->method('deleteOffer');
        $commonRepo->expects($this->never())->method('getTidFromTeamname');
        $entityLoader->expects($this->never())->method('loadPlayer');

        $result = $processor->deleteOffers(\League\League::FREE_AGENTS_TEAM_NAME, 42);

        $this->assertFalse($result['success']);
        $this->assertIsString($result['error']);
        $this->assertNotSame('', $result['error']);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * @return array{0: FreeAgencyProcessor, 1: FreeAgencyRepositoryInterface&MockObject, 2: FreeAgencyEntityLoaderInterface&MockObject, 3: TeamIdentityRepositoryInterface&MockObject}
     */
    private function buildProcessorWithMocks(): array
    {
        $repository = $this->createMock(FreeAgencyRepositoryInterface::class);
        $entityLoader = $this->createMock(FreeAgencyEntityLoaderInterface::class);
        $commonRepo = $this->createMock(TeamIdentityRepositoryInterface::class);

        $processor = new FreeAgencyProcessor(
            $this->mockDb,
            $commonRepo,
            self::createStub(FreeAgencyMarketDemandCalculatorInterface::class),
            $repository,
            self::createStub(\Psr\Log\LoggerInterface::class),
            self::createStub(Season::class),
            $entityLoader,
            self::createStub(FreeAgencyCapCalculatorFactoryInterface::class),
        );

        return [$processor, $repository, $entityLoader, $commonRepo];
    }

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
