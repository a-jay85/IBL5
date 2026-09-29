<?php

declare(strict_types=1);

namespace Tests\CapSpace;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use CapSpace\CapSpaceService;
use CapSpace\Contracts\CapSpaceRepositoryInterface;
use Team\Contracts\TeamCapCalculatorInterface;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Team;
use Season\Season;
use Team\TeamCapCalculator;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;
use League\League;
use Tests\WideUnit\Mocks\TestDataFactory;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Testable subclass that exposes protected methods for testing
 */
class TestableCapSpaceService extends CapSpaceService
{
    /**
     * @return array{team: Team, teamId: int, teamName: string, teamCity: string, color1: string, color2: string, availableSalary: array{year1: int, year2: int, year3: int, year4: int, year5: int, year6: int}, positionSalaries: array<string, int>, freeAgencySlots: int, has_mle: bool, has_lle: bool}
     */
    public function publicProcessTeamCapData(Team $team, Season $season): array
    {
        return $this->processTeamCapData($team, $season);
    }
}

/**
 * CapSpaceServiceTest - Tests for CapSpaceService business logic
 *
 * @covers \CapSpace\CapSpaceService
 */
#[AllowMockObjectsWithoutExpectations]
class CapSpaceServiceTest extends TestCase
{
    /** @var CapSpaceRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private CapSpaceRepositoryInterface $mockRepository;

    /** @var object&\PHPUnit\Framework\MockObject\MockObject */
    private object $mockDb;

    /** @var TeamQueryRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private TeamQueryRepositoryInterface $mockTeamQueryRepo;

    /** @var TeamCapCalculatorInterface&\PHPUnit\Framework\MockObject\MockObject */
    private TeamCapCalculatorInterface $mockTeamCapCalculator;

    private TestableCapSpaceService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(CapSpaceRepositoryInterface::class);
        $this->mockDb = $this->createMock(\mysqli::class);
        $this->mockTeamQueryRepo = $this->createMock(TeamQueryRepositoryInterface::class);
        $this->mockTeamCapCalculator = $this->createMock(TeamCapCalculatorInterface::class);
        $this->service = new TestableCapSpaceService($this->mockRepository, $this->mockDb, $this->mockTeamQueryRepo, $this->mockTeamCapCalculator);
    }

    /**
     * Test that MLE/LLE integer values are correctly converted to booleans
     *
     * Regression test for bug where $team->has_mle === '1' always returned false
     * because database stores integers, not strings.
     */
    public function testMleAndLleFlagsAreCorrectlyConvertedFromIntegersToBoolean(): void
    {
        // Create a mock Team object with integer MLE/LLE values (as stored in database)
        $mockTeam = $this->createMockTeamWithMleLle(1, 1);

        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        $this->setupTeamQueryRepoDefaults();

        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);

        // Verify boolean conversion works correctly with integer 1
        $this->assertIsBool($result['has_mle'], 'has_mle should be a boolean');
        $this->assertIsBool($result['has_lle'], 'has_lle should be a boolean');
        $this->assertTrue($result['has_mle'], 'has_mle should be true when team has MLE=1');
        $this->assertTrue($result['has_lle'], 'has_lle should be true when team has LLE=1');
    }

    public function testMleAndLleFlagsHandleIntegerZeroCorrectly(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(0, 0);

        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        $this->setupTeamQueryRepoDefaults();

        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);

        $this->assertFalse($result['has_mle'], 'has_mle should be false when team has MLE=0');
        $this->assertFalse($result['has_lle'], 'has_lle should be false when team has LLE=0');
    }

    public function testMleAndLleFlagsHandleMixedStates(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(1, 0);

        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        $this->setupTeamQueryRepoDefaults();

        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);

        $this->assertTrue($result['has_mle'], 'has_mle should be true when team has MLE=1');
        $this->assertFalse($result['has_lle'], 'has_lle should be false when team has LLE=0');
    }

    public function testGetDisplayYearsForRegularSeason(): void
    {
        $mockSeason = $this->createMockSeason('Regular Season', 2024, 2025);

        $result = $this->service->getDisplayYears($mockSeason);

        $this->assertSame(2024, $result['beginningYear']);
        $this->assertSame(2025, $result['endingYear']);
    }

    public function testGetDisplayYearsForFreeAgency(): void
    {
        $mockSeason = $this->createMockSeason('Free Agency', 2024, 2025);

        $result = $this->service->getDisplayYears($mockSeason);

        $this->assertSame(2025, $result['beginningYear']);
        $this->assertSame(2026, $result['endingYear']);
    }

    public function testGetDisplayYearsForPlayoffs(): void
    {
        $mockSeason = $this->createMockSeason('Playoffs', 2024, 2025);

        $result = $this->service->getDisplayYears($mockSeason);

        $this->assertSame(2024, $result['beginningYear']);
        $this->assertSame(2025, $result['endingYear']);
    }

    public function testGetDisplayYearsForDraftPhaseShiftsYearsForward(): void
    {
        $mockSeason = $this->createMockSeason('Draft', 2024, 2025);

        $result = $this->service->getDisplayYears($mockSeason);

        // Draft is an offseason phase — years shift forward by 1
        $this->assertSame(2025, $result['beginningYear']);
        $this->assertSame(2026, $result['endingYear']);
    }

    public function testFreeAgencySlotsCalculation(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(1, 1);

        $mockSeason = $this->createMockSeason();

        // Mock 5 players under contract (15 total slots - 5 = 10 FA slots)
        $contractedPlayers = array_fill(0, 5, ['cy' => 2024, 'cyt' => 2025]);
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn($contractedPlayers);
        $this->setupTeamQueryRepoDefaults();

        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);

        $this->assertSame(10, $result['freeAgencySlots']);
    }

    public function testAvailableSalaryStructure(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(1, 1);

        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        $this->setupTeamQueryRepoDefaults();

        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);

        // Should have availableSalary for 6 years
        $this->assertArrayHasKey('availableSalary', $result);
        $this->assertCount(6, $result['availableSalary']);
        $this->assertArrayHasKey('year1', $result['availableSalary']);
        $this->assertArrayHasKey('year6', $result['availableSalary']);
    }

    public function testPositionSalariesStructure(): void
    {
        $mockTeam = $this->createMockTeamWithMleLle(1, 1);

        $mockSeason = $this->createMockSeason();
        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);
        $this->setupTeamQueryRepoDefaults();

        $result = $this->service->publicProcessTeamCapData($mockTeam, $mockSeason);

        $this->assertArrayHasKey('positionSalaries', $result);
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        foreach ($positions as $position) {
            $this->assertArrayHasKey($position, $result['positionSalaries']);
        }
    }

    /**
     * Set up default mock returns for TeamQueryRepository methods
     */
    private function setupTeamQueryRepoDefaults(): void
    {
        $this->mockTeamCapCalculator->method('getSalaryCapArray')->willReturn([
            'year1' => 0,
            'year2' => 0,
            'year3' => 0,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0,
        ]);
        $this->mockTeamQueryRepo->method('getAllPlayersUnderContract')->willReturn([]);
        $this->mockTeamCapCalculator->method('getTotalNextSeasonSalaries')->willReturn(0);
    }

    /**
     * Pins the intentional basis split between the cap-space columns.
     * availableSalary and getDisplayYears() shift on isOffseasonPhase() (Draft|FA).
     * positionSalaries comes from getNextSeasonSalary(), which shifts on
     * advancesContractYears() (Playoffs|Draft|FA). With cy = raw contract year:
     *   Regular Season: year1 = cy,   position = cy+1 -> year2 column
     *   Playoffs:       year1 = cy,   position = cy+2 -> year3 column
     *   Draft / FA:     year1 = cy+1, position = cy+2 -> year2 column
     * The Playoffs row is the only phase where the two predicates disagree.
     */
    #[DataProvider('phaseBasisProvider')]
    public function testPositionSalaryBasisMatchesAvailableSalaryColumnPerPhase(
        string $phase,
        string $column,
        int $pgSalary,
        int $displayBeginningYear,
    ): void {
        $season = $this->phaseSeason($phase);
        $service = $this->buildRealCalculatorService($season);

        $result = $service->publicProcessTeamCapData($this->createMockTeamWithMleLle(1, 1), $season);

        $this->assertSame($pgSalary, $result['positionSalaries']['PG']);
        $this->assertSame(
            League::HARD_CAP_MAX - $result['positionSalaries']['PG'],
            $result['availableSalary'][$column]
        );
        $this->assertSame($displayBeginningYear, $service->getDisplayYears($season)['beginningYear']);
        $this->assertSame(0, $result['positionSalaries']['SG']);
        $this->assertSame(0, $result['positionSalaries']['C']);
    }

    public function testPlayoffsPositionSalaryDoesNotUseRegularSeasonBasis(): void
    {
        $season = $this->phaseSeason('Playoffs');
        $service = $this->buildRealCalculatorService($season);

        $result = $service->publicProcessTeamCapData($this->createMockTeamWithMleLle(1, 1), $season);

        $this->assertNotSame(
            League::HARD_CAP_MAX - $result['positionSalaries']['PG'],
            $result['availableSalary']['year2']
        );
        $this->assertSame(League::HARD_CAP_MAX - 1100, $result['availableSalary']['year1']);
    }

    /**
     * @return array<string, array{string, string, int, int}>
     */
    public static function phaseBasisProvider(): array
    {
        return [
            'Regular Season' => ['Regular Season', 'year2', 1200, 2024],
            'Playoffs' => ['Playoffs', 'year3', 1300, 2024],
            'Draft' => ['Draft', 'year2', 1300, 2025],
            'Free Agency' => ['Free Agency', 'year2', 1300, 2025],
        ];
    }

    private function phaseSeason(string $phase): Season
    {
        return $this->createMockSeason($phase, 2024, 2025);
    }

    /**
     * Build a service wired to a real TeamCapCalculator over one PG player, so the
     * phase-aware cap math runs for real. The same Season is injected into the
     * calculator here and passed to processTeamCapData(), as production does.
     */
    private function buildRealCalculatorService(Season $season): TestableCapSpaceService
    {
        $row = TestDataFactory::createPlayer([
            'pos' => 'PG',
            'cy' => 1,
            'cyt' => 6,
            'salary_yr1' => 1100,
            'salary_yr2' => 1200,
            'salary_yr3' => 1300,
            'salary_yr4' => 1400,
            'salary_yr5' => 1500,
            'salary_yr6' => 1600,
        ]);

        $teamRepo = self::createStub(TeamQueryRepositoryInterface::class);
        $teamRepo->method('getRosterUnderContractOrderedByName')->willReturn([$row]);
        $teamRepo->method('getAllPlayersUnderContract')->willReturn([$row]);

        $cash = self::createStub(BuyoutLedgerRepositoryInterface::class);
        // A zero-salary cash row makes getSalaryCapArray() emit all six year keys in every
        // phase: the player alone reaches only year5 once the offseason shifts cy by +1.
        $cash->method('getTeamCashForSalary')->willReturn([[
            'cy' => $season->isOffseasonPhase() ? 0 : 1,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ]]);

        $db = self::createStub(\mysqli::class);
        $calc = new TeamCapCalculator($db, $teamRepo, $cash, $season);

        $this->mockRepository->method('getPlayersUnderContractAfterSeason')->willReturn([]);

        return new TestableCapSpaceService($this->mockRepository, $db, $teamRepo, $calc);
    }

    /**
     * Create a mock Team object with specific MLE/LLE values
     *
     * @param int $hasMLE MLE flag (0 or 1)
     * @param int $hasLLE LLE flag (0 or 1)
     * @return Team&\PHPUnit\Framework\MockObject\MockObject Mock Team
     */
    private function createMockTeamWithMleLle(int $hasMLE, int $hasLLE): Team
    {
        $mockTeam = $this->createMock(Team::class);
        $mockTeam->teamid = 1;
        $mockTeam->name = 'Test Team';
        $mockTeam->city = 'Test City';
        $mockTeam->color1 = '000000';
        $mockTeam->color2 = 'FFFFFF';
        $mockTeam->has_mle = $hasMLE;
        $mockTeam->has_lle = $hasLLE;

        return $mockTeam;
    }

    /**
     * Create a mock Season object
     *
     * @param string $phase Season phase
     * @param int $beginningYear Starting year
     * @param int $endingYear Ending year
     * @return Season&\PHPUnit\Framework\MockObject\MockObject Mock Season
     */
    private function createMockSeason(string $phase = 'Regular Season', int $beginningYear = 2024, int $endingYear = 2025): Season
    {
        $mockSeason = $this->createMock(Season::class);
        $mockSeason->phase = $phase;
        $mockSeason->beginningYear = $beginningYear;
        $mockSeason->endingYear = $endingYear;
        $mockSeason->method('isOffseasonPhase')->willReturn(
            $phase === 'Draft' || $phase === 'Free Agency'
        );
        // advancesContractYears() is deliberately wider than isOffseasonPhase():
        // it also covers Playoffs (see Season::advancesContractYears docblock).
        $mockSeason->method('advancesContractYears')->willReturn(
            $phase === 'Playoffs' || $phase === 'Draft' || $phase === 'Free Agency'
        );
        return $mockSeason;
    }
}
