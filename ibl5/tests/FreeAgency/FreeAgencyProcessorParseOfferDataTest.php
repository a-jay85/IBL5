<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyCapCalculatorFactoryInterface;
use FreeAgency\Contracts\FreeAgencyCapCalculatorInterface;
use FreeAgency\FreeAgencyProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\MockPreparedStatement;

/**
 * Characterization pins for FreeAgencyProcessor::parseOfferData().
 *
 * parseOfferData() is private, so every pin runs through the public
 * processOfferSubmission() and reads what CapturingRepository received.
 */
final class FreeAgencyProcessorParseOfferDataTest extends TestCase
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
    // VETERAN MINIMUM
    // ================================================================

    /**
     * @return array<string, array{int|null, int}>
     */
    public static function vetMinimumExperienceProvider(): array
    {
        return [
            'exp 12' => [12, 103],
            'exp 10' => [10, 103],
            'exp 9' => [9, 100],
            'exp 3' => [3, 61],
            'exp 1' => [1, 35],
            'exp 0' => [0, 35],
            'exp null' => [null, 35],
        ];
    }

    #[DataProvider('vetMinimumExperienceProvider')]
    public function testVeteranMinimumOfferUsesExperienceLadder(?int $exp, int $expectedOffer1): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData(['exp' => $exp])]);
        $repo = new CapturingRepository();
        $processor = $this->buildProcessor($repo);

        $processor->processOfferSubmission(
            $this->basePost(['offerType' => 8, 'offeryear1' => 999, 'offeryear2' => 999]),
            'Test Team',
        );

        $this->assertSavedOffer($repo, [$expectedOffer1, 0, 0, 0, 0, 0], 8, 0, 0);
    }

    // ================================================================
    // LOWER-LEVEL EXCEPTION
    // ================================================================

    public function testLowerLevelExceptionOfferIsFixedAmount(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData(['has_lle' => 1])]);
        $repo = new CapturingRepository();
        $processor = $this->buildProcessor($repo);

        $processor->processOfferSubmission(
            $this->basePost(['offerType' => 7, 'offeryear1' => 999]),
            'Test Team',
        );

        $this->assertSavedOffer($repo, [145, 0, 0, 0, 0, 0], 7, 0, 1);
    }

    // ================================================================
    // MID-LEVEL EXCEPTION
    // ================================================================

    /**
     * @return array<string, array{int|string, list<int>}>
     */
    public static function mleOfferTypeProvider(): array
    {
        return [
            'one year' => [1, [450, 0, 0, 0, 0, 0]],
            'two years' => [2, [450, 495, 0, 0, 0, 0]],
            'three years' => [3, [450, 495, 540, 0, 0, 0]],
            'four years' => [4, [450, 495, 540, 585, 0, 0]],
            'five years' => [5, [450, 495, 540, 585, 630, 0]],
            'six years' => [6, [450, 495, 540, 585, 630, 675]],
            'numeric string three years' => ['3', [450, 495, 540, 0, 0, 0]],
        ];
    }

    /**
     * @param list<int> $expectedOffers
     */
    #[DataProvider('mleOfferTypeProvider')]
    public function testMidLevelExceptionOfferFillsYearsUpToOfferType(int|string $input, array $expectedOffers): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData(['has_mle' => 1])]);
        $repo = new CapturingRepository();
        $processor = $this->buildProcessor($repo);

        $processor->processOfferSubmission(
            $this->basePost(['offerType' => $input, 'offeryear1' => 999]),
            'Test Team',
        );

        $this->assertSavedOffer($repo, $expectedOffers, (int) $input, 1, 0);
    }

    // ================================================================
    // CUSTOM OFFER
    // ================================================================

    /**
     * @return array<string, array{array<int, mixed>, list<int>}>
     */
    public static function customOfferProvider(): array
    {
        return [
            'ints ascending' => [[500, 550, 600, 650, 700, 750], [500, 550, 600, 650, 700, 750]],
            'numeric strings' => [['500', '550', '600', '650', '700', '750'], [500, 550, 600, 650, 700, 750]],
            'decimals truncate' => [['500.9', '550.1', 600.7, '650.0', '700.5', '750.9'], [500, 550, 600, 650, 700, 750]],
            'surrounding whitespace' => [[' 500', '550 ', ' 600 ', 650, 700, 750], [500, 550, 600, 650, 700, 750]],
            'scientific notation' => [['5e2', '5.5e2', '6E2', 650, 700, 750], [500, 550, 600, 650, 700, 750]],
            'later years non-numeric collapse to zero' => [[500, 'abc', ['550'], null, '', ' '], [500, 0, 0, 0, 0, 0]],
            'later-year keys absent' => [[500], [500, 0, 0, 0, 0, 0]],
        ];
    }

    /**
     * @param array<int, mixed> $inputs
     * @param list<int> $expectedOffers
     */
    #[DataProvider('customOfferProvider')]
    public function testCustomOfferCoercesEachYearToInt(array $inputs, array $expectedOffers): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);
        $repo = new CapturingRepository();
        $processor = $this->buildProcessor($repo);

        $post = ['playerID' => 1, 'offerType' => 0];
        foreach ($inputs as $index => $value) {
            $post['offeryear' . ($index + 1)] = $value;
        }

        $processor->processOfferSubmission($post, 'Test Team');

        $this->assertSavedOffer($repo, $expectedOffers, 0, 0, 0);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidYearOneProvider(): array
    {
        return [
            'non-numeric string' => ['abc', 'Your offer in Year 1 was zero'],
            'array' => [['500'], 'Your offer in Year 1 was zero'],
            'null' => [null, 'Your offer in Year 1 was zero'],
            'empty string' => ['', 'Your offer in Year 1 was zero'],
            'zero' => [0, 'Your offer in Year 1 was zero'],
            'negative int' => [-5, 'Your offer in Year 1 was -5, but should be at least 61.'],
            'negative numeric string' => ['-61', 'Your offer in Year 1 was -61, but should be at least 61.'],
        ];
    }

    #[DataProvider('invalidYearOneProvider')]
    public function testCustomOfferWithInvalidYearOneIsRejectedBeforeSave(mixed $offerYear1, string $expectedMessage): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);
        $repo = new CapturingRepository();
        $processor = $this->buildProcessor($repo);

        $result = $processor->processOfferSubmission(
            $this->basePost(['offeryear1' => $offerYear1]),
            'Test Team',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertStringContainsString($expectedMessage, $result['message']);
        $this->assertNull($repo->lastSavedOffer);
    }

    // ================================================================
    // UNKNOWN / NON-NUMERIC / DECIMAL OFFER TYPE
    // ================================================================

    /**
     * @return array<string, array{int}>
     */
    public static function unknownOfferTypeProvider(): array
    {
        return [
            'nine' => [9],
            'minus one' => [-1],
            'hundred' => [100],
        ];
    }

    #[DataProvider('unknownOfferTypeProvider')]
    public function testUnknownOfferTypeTakesCustomBranchAndSkipsSoftCap(int $type): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);
        $repo = new CapturingRepository();
        $processor = $this->buildProcessor($repo, 499);

        $processor->processOfferSubmission($this->basePost(['offerType' => $type]), 'Test Team');

        $this->assertSavedOffer($repo, [500, 0, 0, 0, 0, 0], $type, 0, 0);
    }

    public function testCustomOfferTypeZeroIsSoftCapChecked(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);
        $repo = new CapturingRepository();
        $processor = $this->buildProcessor($repo, 499);

        $result = $processor->processOfferSubmission($this->basePost(['offerType' => 0]), 'Test Team');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('more than 499, the amount of soft cap space', $result['message']);
        $this->assertNull($repo->lastSavedOffer);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonNumericOfferTypeProvider(): array
    {
        return [
            'letters' => ['abc'],
            'empty string' => [''],
        ];
    }

    #[DataProvider('nonNumericOfferTypeProvider')]
    public function testNonNumericOfferTypeFallsBackToCustomOffer(string $type): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);
        $repo = new CapturingRepository();
        $processor = $this->buildProcessor($repo);

        $processor->processOfferSubmission($this->basePost(['offerType' => $type]), 'Test Team');

        $this->assertSavedOffer($repo, [500, 0, 0, 0, 0, 0], 0, 0, 0);
    }

    /**
     * @return array<string, array{string, array<string, mixed>, list<int>, int, int, int}>
     */
    public static function decimalOfferTypeProvider(): array
    {
        return [
            'decimal lower-level exception' => ['7.5', ['has_lle' => 1], [145, 0, 0, 0, 0, 0], 7, 0, 1],
            'decimal one-year mid-level exception' => ['1.9', ['has_mle' => 1], [450, 0, 0, 0, 0, 0], 1, 1, 0],
        ];
    }

    /**
     * @param array<string, mixed> $playerOverrides
     * @param list<int> $expectedOffers
     */
    #[DataProvider('decimalOfferTypeProvider')]
    public function testDecimalOfferTypeStringTruncatesToExceptionType(
        string $type,
        array $playerOverrides,
        array $expectedOffers,
        int $expectedOfferType,
        int $expectedMle,
        int $expectedLle,
    ): void {
        $this->mockDb->setMockData([$this->getCompletePlayerData($playerOverrides)]);
        $repo = new CapturingRepository();
        $processor = $this->buildProcessor($repo);

        $processor->processOfferSubmission(
            $this->basePost(['offerType' => $type, 'offeryear1' => 999]),
            'Test Team',
        );

        $this->assertSavedOffer($repo, $expectedOffers, $expectedOfferType, $expectedMle, $expectedLle);
    }

    // ================================================================
    // SECURITY SURFACE
    // ================================================================

    public function testProcessorPublicSurfaceCarriesNoActorIdentityCollaborator(): void
    {
        $class = new \ReflectionClass(FreeAgencyProcessor::class);
        $constructor = $class->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertCount(8, $constructor->getParameters());

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                $typeName = $type === null ? '' : (string) $type;
                $this->assertDoesNotMatchRegularExpression(
                    '/Auth|Session|Security|User|LoggedIn/i',
                    $typeName,
                    $method->getName() . '($' . $param->getName() . ') carries an actor-identity type',
                );
            }
        }

        $submission = $class->getMethod('processOfferSubmission');
        $names = array_map(
            static fn (\ReflectionParameter $param): string => $param->getName(),
            $submission->getParameters(),
        );
        $this->assertSame(['postData', 'verifiedTeamName'], $names);
    }

    // ================================================================
    // FIXTURE HELPERS
    // ================================================================

    private function buildProcessor(CapturingRepository $repo, int $softCapSpaceYear1 = 5000): FreeAgencyProcessor
    {
        /** @var FreeAgencyCapCalculatorInterface&\PHPUnit\Framework\MockObject\Stub $calcStub */
        $calcStub = self::createStub(FreeAgencyCapCalculatorInterface::class);
        $calcStub->method('calculateTeamCapMetrics')->willReturn([
            'totalSalaries' => [0 => 0],
            'softCapSpace'  => [0 => $softCapSpaceYear1],
            'hardCapSpace'  => [0 => 7000],
            'rosterSpots'   => [0 => 15],
        ]);

        /** @var FreeAgencyCapCalculatorFactoryInterface&\PHPUnit\Framework\MockObject\Stub $factoryStub */
        $factoryStub = self::createStub(FreeAgencyCapCalculatorFactoryInterface::class);
        $factoryStub->method('forTeam')->willReturn($calcStub);

        return new FreeAgencyProcessor(
            $this->mockDb,
            self::createStub(TeamIdentityRepositoryInterface::class),
            new StubDemandCalculator(),
            $repo,
            capCalculatorFactory: $factoryStub,
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function basePost(array $overrides = []): array
    {
        return array_merge([
            'playerID' => 1,
            'offeryear1' => 500,
            'offeryear2' => 0,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0,
        ], $overrides);
    }

    /**
     * @param list<int> $expectedOffers
     */
    private function assertSavedOffer(
        CapturingRepository $repo,
        array $expectedOffers,
        int $offerType,
        int $mle,
        int $lle,
    ): void {
        $saved = $repo->lastSavedOffer;
        $this->assertNotNull($saved);

        for ($i = 0; $i < 6; $i++) {
            $this->assertSame($expectedOffers[$i], $saved['offer' . ($i + 1)], 'offer' . ($i + 1));
        }

        $this->assertSame($offerType, $saved['offerType']);
        $this->assertSame($mle, $saved['mle']);
        $this->assertSame($lle, $saved['lle']);
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
