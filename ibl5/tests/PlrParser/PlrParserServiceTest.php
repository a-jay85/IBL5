<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserRepositoryInterface;
use PlrParser\PlrImportMode;
use PlrParser\PlrLineParser;
use PlrParser\PlrParserService;
use Season\Season;

class PlrParserServiceTest extends TestCase
{
    private PlrParserService $service;

    /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private PlrParserRepositoryInterface $stubRepository;

    /** @var \Services\CommonMysqliRepository&\PHPUnit\Framework\MockObject\Stub */
    private \Services\CommonMysqliRepository $stubCommonRepo;

    /** @var Season&\PHPUnit\Framework\MockObject\Stub */
    private Season $stubSeason;

    protected function setUp(): void
    {
        $this->stubRepository = $this->createStub(PlrParserRepositoryInterface::class);

        $this->stubCommonRepo = $this->createStub(\Services\CommonMysqliRepository::class);
        $this->stubCommonRepo->method('getTeamnameFromTeamID')->willReturn('Test Team');

        $this->stubSeason = $this->createStub(Season::class);
        $this->stubSeason->endingYear = 2026;

        $this->service = new PlrParserService(
            $this->stubRepository,
            $this->stubCommonRepo,
            $this->stubSeason,
        );
    }

    public function testCalculateFoulBaselineWithValidFile(): void
    {
        $tmpFile = $this->createTempPlrLine(realLifeMIN: 1000, realLifePF: 100);

        $result = $this->service->calculateFoulBaseline($tmpFile);

        $this->assertSame(0.1, $result);
        unlink($tmpFile);
    }

    public function testCalculateFoulBaselineWithZeroPF(): void
    {
        $tmpFile = $this->createTempPlrLine(realLifeMIN: 1000, realLifePF: 0);

        $result = $this->service->calculateFoulBaseline($tmpFile);

        $this->assertSame(0.0, $result);
        unlink($tmpFile);
    }

    public function testCalculateFoulBaselineWithNonexistentFile(): void
    {
        $result = $this->service->calculateFoulBaseline('/tmp/nonexistent_plr_file.plr');

        $this->assertSame(0.0, $result);
    }

    public function testParsePlrLineReturnsNullForPidZero(): void
    {
        // pid field (offset 38, length 6) = 000000
        $line = str_repeat(' ', 700);
        $line = substr_replace($line, '   1', 0, 4);    // ordinal = 1
        $line = substr_replace($line, '000000', 38, 6);  // pid = 0

        $result = PlrLineParser::parse($line);

        $this->assertNull($result);
    }

    public function testParsePlrLineReturnsNullForOrdinalAbove1440(): void
    {
        $line = str_repeat(' ', 700);
        $line = substr_replace($line, '1441', 0, 4);    // ordinal = 1441
        $line = substr_replace($line, '000001', 38, 6);  // pid = 1

        $result = PlrLineParser::parse($line);

        $this->assertNull($result);
    }

    public function testParsePlrLineExtractsFields(): void
    {
        $line = $this->buildPlrLine([
            'ordinal' => 1,
            'name' => 'John Smith',
            'age' => 25,
            'pid' => 12345,
            'tid' => 5,
            'peak' => 28,
            'pos' => 'PG',
        ]);

        $result = PlrLineParser::parse($line);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['ordinal']);
        $this->assertSame('John Smith', $result['name']);
        $this->assertSame(25, $result['age']);
        $this->assertSame(12345, $result['pid']);
        $this->assertSame(5, $result['tid']);
        $this->assertSame(28, $result['peak']);
        $this->assertSame('PG', $result['pos']);
    }

    public function testParsePlrLineConvertsCP1252ToUTF8(): void
    {
        // Create a line with an accented character in CP1252 encoding
        $line = str_repeat(' ', 700);
        $line = substr_replace($line, '   1', 0, 4);    // ordinal = 1
        $line = substr_replace($line, '000001', 38, 6);  // pid = 1

        // Insert CP1252 name with é (0xE9 in CP1252)
        $cp1252Name = str_pad("Ren\xE9 Test", 32);
        $line = substr_replace($line, $cp1252Name, 4, 32);

        $result = PlrLineParser::parse($line);

        $this->assertNotNull($result);
        $this->assertSame('René Test', $result['name']);
    }

    public function testComputeDerivedFieldsFGM(): void
    {
        $raw = $this->buildRawParsedData(['season2GM' => 100, 'season3GM' => 50]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(150, $derived['seasonFGM']);
    }

    public function testComputeDerivedFieldsFGA(): void
    {
        $raw = $this->buildRawParsedData(['season2GA' => 200, 'season3GA' => 100]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(300, $derived['seasonFGA']);
    }

    public function testComputeDerivedFieldsREB(): void
    {
        $raw = $this->buildRawParsedData(['seasonORB' => 30, 'seasonDRB' => 70]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(100, $derived['seasonREB']);
    }

    public function testComputeDerivedFieldsPTS(): void
    {
        $raw = $this->buildRawParsedData([
            'season2GM' => 100,
            'season3GM' => 50,
            'seasonFTM' => 75,
        ]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        // PTS = 2GM*2 + FTM + 3GM*3 = 200 + 75 + 150 = 425
        $this->assertSame(425, $derived['seasonPTS']);
    }

    public function testComputeDerivedFieldsCareerComposites(): void
    {
        $raw = $this->buildRawParsedData([
            'career2GM' => 500,
            'career3GM' => 200,
            'career2GA' => 1000,
            'career3GA' => 500,
            'careerFTM' => 300,
            'careerORB' => 100,
            'careerDRB' => 400,
        ]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(700, $derived['careerFGM']);
        $this->assertSame(1500, $derived['careerFGA']);
        $this->assertSame(500, $derived['careerREB']);
        // PTS = 500*2 + 300 + 200*3 = 1000 + 300 + 600 = 1900
        $this->assertSame(1900, $derived['careerPTS']);
    }

    public function testComputeDerivedFieldsSalaryForContractYear0(): void
    {
        $raw = $this->buildRawParsedData([
            'currentContractYear' => 0,
            'contractYear1' => 500,
        ]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(500, $derived['currentSeasonSalary']);
    }

    public function testComputeDerivedFieldsSalaryForContractYear7(): void
    {
        $raw = $this->buildRawParsedData([
            'currentContractYear' => 7,
            'contractYear1' => 500,
        ]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(0, $derived['currentSeasonSalary']);
    }

    public function testComputeDerivedFieldsSalaryForNormalContractYear(): void
    {
        $raw = $this->buildRawParsedData([
            'currentContractYear' => 2,
            'contractYear2' => 750,
        ]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(750, $derived['currentSeasonSalary']);
    }

    public function testComputeDerivedFieldsHeightConversion(): void
    {
        $raw = $this->buildRawParsedData(['heightInches' => 79]); // 6'7"

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(6, $derived['heightFT']);
        $this->assertSame(7, $derived['heightIN']);
    }

    public function testComputeDerivedFieldsDraftYear(): void
    {
        $raw = $this->buildRawParsedData(['exp' => 5]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        // draftYear = endingYear (2026) - exp (5) = 2021
        $this->assertSame(2021, $derived['draftYear']);
    }

    public function testComputeDerivedFieldsFoulRating(): void
    {
        $raw = $this->buildRawParsedData([
            'realLifePF' => 100,
            'realLifeMIN' => 2000,
        ]);
        // personalFoulsPerMinute = 100/2000 = 0.05
        // maxFoulRatio = 0.1
        // ratingFOUL = 100 - round(0.05/0.1 * 100) = 100 - 50 = 50
        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(50, $derived['ratingFOUL']);
    }

    public function testComputeDerivedFieldsFoulRatingWithZeroFouls(): void
    {
        $raw = $this->buildRawParsedData([
            'realLifePF' => 0,
            'realLifeMIN' => 2000,
        ]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(100, $derived['ratingFOUL']);
    }

    public function testComputeDerivedFieldsFoulRatingWithZeroMaxRatio(): void
    {
        $raw = $this->buildRawParsedData([
            'realLifePF' => 100,
            'realLifeMIN' => 2000,
        ]);

        $derived = $this->service->computeDerivedFields($raw, 0.0);

        $this->assertSame(0, $derived['ratingFOUL']);
    }

    public function testParsePlrLineAcceptsOrdinalExactly1440(): void
    {
        $line = $this->buildPlrLine(['ordinal' => 1440, 'pid' => 12345]);

        $result = PlrLineParser::parse($line);

        $this->assertNotNull($result);
        $this->assertSame(1440, $result['ordinal']);
    }

    #[DataProvider('contractYearSalaryProvider')]
    public function testComputeDerivedFieldsSalaryForContractYears3Through6(int $contractYear, int $expectedSalary): void
    {
        $raw = $this->buildRawParsedData([
            'currentContractYear' => $contractYear,
            'contractYear3' => 600,
            'contractYear4' => 650,
            'contractYear5' => 700,
            'contractYear6' => 750,
        ]);

        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame($expectedSalary, $derived['currentSeasonSalary']);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function contractYearSalaryProvider(): array
    {
        return [
            'contract year 3' => [3, 600],
            'contract year 4' => [4, 650],
            'contract year 5' => [5, 700],
            'contract year 6' => [6, 750],
        ];
    }

    public function testProcessPlrFileWithValidFile(): void
    {
        $tmpFile = $this->createFullPlrFile();

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $mockRepo */
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->once())->method('upsertPlayer');

        $service = new PlrParserService($mockRepo, $this->stubCommonRepo, $this->stubSeason);
        $result = $service->processPlrFile($tmpFile);

        $this->assertSame(1, $result->playersUpserted);
        unlink($tmpFile);
    }

    public function testProcessPlrFileSkipsPidZero(): void
    {
        $tmpFile = $this->createTempPlrLine(pid: 0);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $mockRepo */
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->never())->method('upsertPlayer');

        $service = new PlrParserService($mockRepo, $this->stubCommonRepo, $this->stubSeason);
        $result = $service->processPlrFile($tmpFile);

        $this->assertSame(0, $result->playersUpserted);
        unlink($tmpFile);
    }

    /**
     * Build a minimal PLR line with specific field values.
     *
     * @param array<string, int|string> $overrides Field overrides
     * @return string Fixed-width PLR line
     */
    private function buildPlrLine(array $overrides = []): string
    {
        $line = str_repeat(' ', 700);

        $ordinal = $overrides['ordinal'] ?? 1;
        $line = substr_replace($line, str_pad((string) $ordinal, 4, ' ', STR_PAD_LEFT), 0, 4);

        $name = $overrides['name'] ?? 'Test Player';
        $line = substr_replace($line, str_pad((string) $name, 32), 4, 32);

        $age = $overrides['age'] ?? 25;
        $line = substr_replace($line, str_pad((string) $age, 2, ' ', STR_PAD_LEFT), 36, 2);

        $pid = $overrides['pid'] ?? 1;
        $line = substr_replace($line, str_pad((string) $pid, 6, '0', STR_PAD_LEFT), 38, 6);

        $tid = $overrides['tid'] ?? 1;
        $line = substr_replace($line, str_pad((string) $tid, 2, ' ', STR_PAD_LEFT), 44, 2);

        $peak = $overrides['peak'] ?? 28;
        $line = substr_replace($line, str_pad((string) $peak, 4, ' ', STR_PAD_LEFT), 46, 4);

        $pos = $overrides['pos'] ?? 'PG';
        $line = substr_replace($line, str_pad((string) $pos, 2), 50, 2);

        return $line;
    }

    /**
     * Build raw parsed data array with defaults for computeDerivedFields testing.
     *
     * @param array<string, int|string> $overrides Field overrides
     * @return array<string, int|string> Raw parsed data
     */
    private function buildRawParsedData(array $overrides = []): array
    {
        $defaults = [
            'ordinal' => 1,
            'name' => 'Test Player',
            'age' => 25,
            'pid' => 1,
            'tid' => 1,
            'peak' => 28,
            'pos' => 'PG',
            'realLifeGP' => 82,
            'realLifeMIN' => 2000,
            'realLifeFGM' => 400,
            'realLifeFGA' => 800,
            'realLifeFTM' => 200,
            'realLifeFTA' => 250,
            'realLife3GM' => 100,
            'realLife3GA' => 300,
            'realLifeORB' => 50,
            'realLifeDRB' => 200,
            'realLifeAST' => 300,
            'realLifeSTL' => 80,
            'realLifeTVR' => 150,
            'realLifeBLK' => 30,
            'realLifePF' => 100,
            'unk_112' => 0, 'unk_114' => 0, 'unk_116' => 0, 'unk_118' => 0,
            'unk_120' => 0, 'unk_122' => 0, 'unk_124' => 0, 'unk_126' => 0,
            'clutch' => 50,
            'consistency' => 50,
            'PGDepth' => 1,
            'SGDepth' => 0,
            'SFDepth' => 0,
            'PFDepth' => 0,
            'CDepth' => 0,
            'canPlayInGame' => 1,
            'unk_138' => 0,
            'injuryDaysLeft' => 0,
            'seasonGamesStarted' => 40,
            'seasonGamesPlayed' => 40,
            'seasonMIN' => 1200,
            'season2GM' => 200,
            'season2GA' => 400,
            'seasonFTM' => 100,
            'seasonFTA' => 120,
            'season3GM' => 50,
            'season3GA' => 150,
            'seasonORB' => 20,
            'seasonDRB' => 80,
            'seasonAST' => 150,
            'seasonSTL' => 40,
            'seasonTVR' => 75,
            'seasonBLK' => 15,
            'seasonPF' => 50,
            'playoffSeasonGP' => 0, 'playoffSeasonMIN' => 0,
            'playoffSeason2GM' => 0, 'playoffSeason2GA' => 0,
            'playoffSeasonFTM' => 0, 'playoffSeasonFTA' => 0,
            'playoffSeason3GM' => 0, 'playoffSeason3GA' => 0,
            'playoffSeasonORB' => 0, 'playoffSeasonDRB' => 0,
            'playoffSeasonAST' => 0, 'playoffSeasonSTL' => 0,
            'playoffSeasonTVR' => 0, 'playoffSeasonBLK' => 0,
            'playoffSeasonPF' => 0,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'coach' => 50,
            'loyalty' => 50,
            'playingTime' => 50,
            'playForWinner' => 50,
            'tradition' => 50,
            'security' => 50,
            'exp' => 5,
            'bird' => 3,
            'currentContractYear' => 2,
            'totalContractYears' => 4,
            'unk_294' => 0, 'unk_296' => 0,
            'contractYear1' => 500,
            'contractYear2' => 550,
            'contractYear3' => 600,
            'contractYear4' => 650,
            'contractYear5' => 0,
            'contractYear6' => 0,
            'unk_322' => 0, 'unk_324' => 0,
            'draftRound' => 1,
            'draftPickNumber' => 15,
            'freeAgentSigningFlag' => 0,
            'unk_331' => 0, 'unk_333' => 0, 'unk_335' => 0, 'unk_337' => 0, 'unk_339' => 0,
            'seasonHighPTS' => 35,
            'seasonHighREB' => 12,
            'seasonHighAST' => 10,
            'seasonHighSTL' => 5,
            'seasonHighBLK' => 4,
            'seasonHighDoubleDoubles' => 8,
            'seasonHighTripleDoubles' => 1,
            'seasonPlayoffHighPTS' => 0,
            'seasonPlayoffHighREB' => 0,
            'seasonPlayoffHighAST' => 0,
            'seasonPlayoffHighSTL' => 0,
            'seasonPlayoffHighBLK' => 0,
            'careerSeasonHighPTS' => 40,
            'careerSeasonHighREB' => 15,
            'careerSeasonHighAST' => 12,
            'careerSeasonHighSTL' => 6,
            'careerSeasonHighBLK' => 5,
            'careerSeasonHighDoubleDoubles' => 20,
            'careerSeasonHighTripleDoubles' => 3,
            'careerPlayoffHighPTS' => 30,
            'careerPlayoffHighREB' => 10,
            'careerPlayoffHighAST' => 8,
            'careerPlayoffHighSTL' => 4,
            'careerPlayoffHighBLK' => 3,
            'careerGP' => 400,
            'careerMIN' => 12000,
            'career2GM' => 2000,
            'career2GA' => 4000,
            'careerFTM' => 1000,
            'careerFTA' => 1200,
            'career3GM' => 500,
            'career3GA' => 1500,
            'careerORB' => 300,
            'careerDRB' => 1200,
            'careerAST' => 1500,
            'careerSTL' => 400,
            'careerTVR' => 750,
            'careerBLK' => 150,
            'careerPF' => 600,
            'unk_512' => 0, 'unk_514' => 0, 'unk_516' => 0, 'unk_518' => 0,
            'unk_520' => 0, 'unk_522' => 0, 'unk_524' => 0, 'unk_526' => 0,
            'unk_528' => 0, 'unk_530' => 0, 'unk_532' => 0, 'unk_534' => 0,
            'unk_536' => 0, 'unk_538' => 0, 'unk_540' => 0, 'unk_542' => 0,
            'unk_544' => 0, 'unk_546' => 0, 'unk_548' => 0,
            'heightInches' => 75,
            'weight' => 195,
            'rating2GA' => 500,
            'rating2GP' => 480,
            'ratingFTA' => 400,
            'ratingFTP' => 850,
            'rating3GA' => 300,
            'rating3GP' => 380,
            'ratingORB' => 200,
            'ratingDRB' => 500,
            'ratingAST' => 600,
            'ratingSTL' => 350,
            'ratingTVR' => 400,
            'ratingBLK' => 150,
            'ratingOO' => 50,
            'ratingDO' => 50,
            'ratingPO' => 50,
            'ratingTO' => 50,
            'ratingOD' => 50,
            'ratingDD' => 50,
            'ratingPD' => 50,
            'ratingTD' => 50,
        ];

        /** @var array<string, int|string> $merged */
        $merged = array_merge($defaults, $overrides);
        return $merged;
    }

    /**
     * Create a temp PLR file with one line having specified field values.
     *
     * @return string Path to temp file
     */
    private function createTempPlrLine(
        int $ordinal = 1,
        int $pid = 1,
        int $realLifeMIN = 1000,
        int $realLifePF = 100,
    ): string {
        $line = str_repeat(' ', 700);
        $line = substr_replace($line, str_pad((string) $ordinal, 4, ' ', STR_PAD_LEFT), 0, 4);
        $line = substr_replace($line, str_pad('Test Player', 32), 4, 32);
        $line = substr_replace($line, str_pad((string) $pid, 6, '0', STR_PAD_LEFT), 38, 6);
        $line = substr_replace($line, str_pad((string) $realLifeMIN, 4, ' ', STR_PAD_LEFT), 56, 4);
        $line = substr_replace($line, str_pad((string) $realLifePF, 4, ' ', STR_PAD_LEFT), 108, 4);

        $tmpFile = tempnam(sys_get_temp_dir(), 'plr_test_');
        if ($tmpFile === false) {
            throw new \RuntimeException('Failed to create temp file');
        }
        file_put_contents($tmpFile, $line . "\n");
        return $tmpFile;
    }

    public function testComputeDerivedFieldsWithExplicitEndingYear(): void
    {
        $raw = $this->buildRawParsedData(['exp' => 3]);

        $derived = $this->service->computeDerivedFields($raw, 0.1, 2000);

        // draftYear = endingYear (2000) - exp (3) = 1997
        $this->assertSame(1997, $derived['draftYear']);
    }

    public function testComputeDerivedFieldsWithExplicitEndingYearDoesNotAffectDefault(): void
    {
        $raw = $this->buildRawParsedData(['exp' => 3]);

        // Without override, uses Season.endingYear = 2026
        $derived = $this->service->computeDerivedFields($raw, 0.1);

        $this->assertSame(2023, $derived['draftYear']);
    }

    public function testProcessPlrFileForYearSnapshotMode(): void
    {
        $tmpFile = $this->createFullPlrFile();

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $mockRepo */
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->once())->method('upsertSnapshot');
        $mockRepo->expects($this->never())->method('upsertPlayer');

        $service = new PlrParserService($mockRepo, $this->stubCommonRepo, $this->stubSeason);
        $result = $service->processPlrFileForYear(
            $tmpFile,
            2001,
            PlrImportMode::Snapshot,
            'end-of-season',
            '00-01_36_finals',
        );

        $this->assertSame(1, $result->playersUpserted);
        unlink($tmpFile);
    }

    public function testSnapshotDataIncludesAllFieldCategories(): void
    {
        $tmpFile = $this->createFullPlrFile();

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $mockRepo */
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('upsertSnapshot')
            ->with($this->callback(static function (array $data): bool {
                // Season stats
                return array_key_exists('stats_gs', $data)
                    && array_key_exists('stats_pts', $data)
                    // Playoff stats
                    && array_key_exists('po_stats_gm', $data)
                    // Career stats
                    && array_key_exists('car_gm', $data)
                    && array_key_exists('car_pts', $data)
                    // Season highs
                    && array_key_exists('sh_pts', $data)
                    // Real-life stats
                    && array_key_exists('rl_gp', $data)
                    // Preferences
                    && array_key_exists('coach', $data)
                    && array_key_exists('winner', $data)
                    // Draft info
                    && array_key_exists('draftround', $data)
                    // Derived
                    && array_key_exists('salary', $data)
                    && array_key_exists('draftyear', $data)
                    // Unknown gaps
                    && array_key_exists('unk_112', $data)
                    && array_key_exists('unk_548', $data);
            }));

        $service = new PlrParserService($mockRepo, $this->stubCommonRepo, $this->stubSeason);
        $result = $service->processPlrFileForYear(
            $tmpFile,
            2001,
            PlrImportMode::Snapshot,
            'end-of-season',
            '00-01_36_finals',
        );

        $this->assertSame(1, $result->playersUpserted);
        unlink($tmpFile);
    }

    public function testProcessPlrFileForYearLiveMode(): void
    {
        $tmpFile = $this->createFullPlrFile();

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $mockRepo */
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->once())->method('upsertPlayer');
        $mockRepo->expects($this->never())->method('upsertSnapshot');

        $service = new PlrParserService($mockRepo, $this->stubCommonRepo, $this->stubSeason);
        $result = $service->processPlrFileForYear($tmpFile, 2001, PlrImportMode::Live);

        $this->assertSame(1, $result->playersUpserted);
        unlink($tmpFile);
    }

    /**
     * Create a temp PLR file with one valid player line (all fields populated).
     *
     * @return string Path to temp file
     */
    private function createFullPlrFile(): string
    {
        // Build a complete PLR line with valid data
        $line = str_repeat('0', 700);

        // Key fields to ensure the line passes validation
        $line = substr_replace($line, '   1', 0, 4);      // ordinal = 1
        $line = substr_replace($line, str_pad('Test Player', 32), 4, 32);
        $line = substr_replace($line, '25', 36, 2);         // age
        $line = substr_replace($line, '000001', 38, 6);     // pid = 1
        $line = substr_replace($line, ' 1', 44, 2);         // tid = 1
        $line = substr_replace($line, '  28', 46, 4);       // peak
        $line = substr_replace($line, 'PG', 50, 2);         // pos
        $line = substr_replace($line, '1000', 56, 4);       // realLifeMIN
        $line = substr_replace($line, ' 100', 108, 4);      // realLifePF
        $line = substr_replace($line, ' 2', 290, 2);        // currentContractYear
        $line = substr_replace($line, ' 4', 292, 2);        // totalContractYears
        $line = substr_replace($line, ' 500', 298, 4);      // contractYear1
        $line = substr_replace($line, ' 550', 302, 4);      // contractYear2
        $line = substr_replace($line, ' 5', 286, 2);        // exp
        $line = substr_replace($line, '75', 550, 2);         // heightInches
        $line = substr_replace($line, '195', 552, 3);        // weight

        $tmpFile = tempnam(sys_get_temp_dir(), 'plr_full_');
        if ($tmpFile === false) {
            throw new \RuntimeException('Failed to create temp file');
        }
        file_put_contents($tmpFile, $line . "\n");
        return $tmpFile;
    }
}
