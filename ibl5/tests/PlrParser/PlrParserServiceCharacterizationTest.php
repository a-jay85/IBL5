<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserRepositoryInterface;
use PlrParser\PlrImportMode;
use PlrParser\PlrParserService;
use Season\Season;

/**
 * Characterization tests for PlrParserService.
 *
 * These tests pin the current output shape so that refactors (e.g., merging
 * processPlrData and processPlrDataForYear) preserve exact behavior. Each test
 * is labelled with the invariant it guards.
 */
class PlrParserServiceCharacterizationTest extends TestCase
{
    /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private PlrParserRepositoryInterface $stubRepository;

    /** @var Season&\PHPUnit\Framework\MockObject\Stub */
    private Season $stubSeason;

    protected function setUp(): void
    {
        $this->stubRepository = self::createStub(PlrParserRepositoryInterface::class);

        $this->stubSeason = self::createStub(Season::class);
        $this->stubSeason->endingYear = 2026;
    }

    /**
     * Helper: build service with optional overrides for repo and season.
     */
    private function buildService(
        ?PlrParserRepositoryInterface $repo = null,
        ?Season $season = null,
    ): PlrParserService {
        return new PlrParserService(
            $repo ?? $this->stubRepository,
            $season ?? $this->stubSeason,
        );
    }

    /**
     * Build the standard characterization fixture.
     *
     * Single-player line with known field values:
     * - ordinal=1 (offset 0, 4 bytes) — passes ≤1440 guard
     * - pid=1 (offset 38, 6 bytes) — passes pid≠0 guard
     * - realLifeMIN=1000 (offset 56, 4 bytes)
     * - realLifePF=100 (offset 108, 4 bytes) → single-player: player IS max foul ratio → ratingFOUL=0
     * - exp=$exp (offset 286, 2 bytes) → draftYear = endingYear - $exp
     * - currentContractYear=2 (offset 290, 2 bytes), contractYear2=550 (offset 302, 4 bytes) → currentSeasonSalary=550
     * - heightInches=75 (offset 550, 2 bytes) → heightFT=6, heightIN=3
     */
    private function buildCharacterizationFixture(int $exp = 5): string
    {
        $line = str_repeat('0', 700);
        $line = substr_replace($line, '   1', 0, 4);    // ordinal = 1
        $line = substr_replace($line, '000001', 38, 6); // pid = 1
        $line = substr_replace($line, '1000', 56, 4);   // realLifeMIN = 1000
        $line = substr_replace($line, ' 100', 108, 4);  // realLifePF = 100
        $line = substr_replace($line, str_pad((string) $exp, 2, ' ', STR_PAD_LEFT), 286, 2); // exp
        $line = substr_replace($line, ' 2', 290, 2);    // currentContractYear = 2
        $line = substr_replace($line, ' 550', 302, 4);  // contractYear2 = 550
        $line = substr_replace($line, '75', 550, 2);    // heightInches = 75
        return $line . "\r\n";
    }

    /**
     * Build a 700-byte digit line where byte i is ((i * 7 + 3) % 10), then
     * overwrite the guard slots so the single-player derive path accepts it.
     * Neighbouring fixed-width fields get different digits, so a snapshot key
     * mapped to the wrong source field changes the asserted payload.
     */
    private function buildDistinctValueLine(): string
    {
        $line = '';
        for ($i = 0; $i < 700; $i++) {
            $line .= (string) (($i * 7 + 3) % 10);
        }
        $line = substr_replace($line, '   7', 0, 4);    // ordinal = 7
        $line = substr_replace($line, '000042', 38, 6); // pid = 42
        $line = substr_replace($line, '1000', 56, 4);   // realLifeMIN = 1000
        $line = substr_replace($line, ' 100', 108, 4);  // realLifePF = 100
        $line = substr_replace($line, ' 5', 286, 2);    // exp = 5
        $line = substr_replace($line, ' 2', 290, 2);    // currentContractYear = 2
        $line = substr_replace($line, ' 550', 302, 4);  // contractYear2 = 550
        $line = substr_replace($line, '75', 550, 2);    // heightInches = 75
        return $line . "\r\n";
    }

    /**
     * Engine-fidelity pin: processPlrData uses Season.endingYear.
     */
    public function testProcessPlrDataDraftYearUsesSeasonEndingYear(): void
    {
        $capturedArg = null;
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('upsertPlayer')
            ->willReturnCallback(static function (array $arg) use (&$capturedArg): int {
                $capturedArg = $arg;
                return 1;
            });

        $service = $this->buildService(repo: $mockRepo);
        $service->processPlrData($this->buildCharacterizationFixture(exp: 5));

        $this->assertIsArray($capturedArg);
        $this->assertSame(2021, $capturedArg['draftYear']); // 2026 - 5
    }

    /**
     * Engine-fidelity pin: processPlrDataForYear uses the explicit year.
     */
    public function testProcessPlrDataForYearLiveDraftYearUsesExplicitYear(): void
    {
        $capturedArg = null;
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('upsertPlayer')
            ->willReturnCallback(static function (array $arg) use (&$capturedArg): int {
                $capturedArg = $arg;
                return 1;
            });

        $service = $this->buildService(repo: $mockRepo);
        $service->processPlrDataForYear(
            $this->buildCharacterizationFixture(exp: 5),
            2001,
            PlrImportMode::Live,
        );

        $this->assertIsArray($capturedArg);
        $this->assertSame(1996, $capturedArg['draftYear']); // 2001 - 5
    }

    /**
     * Structural invariant: the two paths produce different draftYears.
     * This is the behavior the merge must preserve.
     */
    public function testBothPathsProduceDifferentDraftYear(): void
    {
        $data = $this->buildCharacterizationFixture(exp: 5);

        $capturedLive = null;
        $mockRepoLive = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepoLive->expects($this->once())
            ->method('upsertPlayer')
            ->willReturnCallback(static function (array $arg) use (&$capturedLive): int {
                $capturedLive = $arg;
                return 1;
            });
        $this->buildService(repo: $mockRepoLive)->processPlrData($data);

        $capturedForYear = null;
        $mockRepoForYear = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepoForYear->expects($this->once())
            ->method('upsertPlayer')
            ->willReturnCallback(static function (array $arg) use (&$capturedForYear): int {
                $capturedForYear = $arg;
                return 1;
            });
        $this->buildService(repo: $mockRepoForYear)->processPlrDataForYear($data, 2001, PlrImportMode::Live);

        $this->assertSame(2021, $capturedLive['draftYear']);    // Season(2026) - 5
        $this->assertSame(1996, $capturedForYear['draftYear']); // 2001 - 5
        $this->assertNotSame($capturedLive['draftYear'], $capturedForYear['draftYear']);
    }

    /**
     * Discriminating test: green on lazy-read code; fails under an eager-read merge
     * (which reads $this->season->endingYear before the loop → Error on uninitialized property).
     *
     * Season stub is created WITHOUT setting endingYear — uninitialized typed property
     * throws Error on access. A pid=0 line parses to null, so the loop body (which
     * reads endingYear via computeDerivedFields) is never entered.
     */
    public function testProcessPlrDataWithNoParsedLinesDoesNotTouchSeason(): void
    {
        $stubSeasonNoYear = self::createStub(Season::class);
        // DO NOT set $stubSeasonNoYear->endingYear — access would throw Error

        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->never())->method('upsertPlayer');

        $service = $this->buildService(repo: $mockRepo, season: $stubSeasonNoYear);

        // pid=0 line: PlrLineParser::parse() returns null → loop body skipped
        $line = str_repeat('0', 700);
        $line = substr_replace($line, '   1', 0, 4);    // ordinal = 1
        $line = substr_replace($line, '000000', 38, 6); // pid = 0
        $data = $line . "\r\n";

        $result = $service->processPlrData($data);

        $this->assertSame(0, $result->playersUpserted);
    }

    /**
     * CAPTURE PROCEDURE: for full-array tests, add `echo var_export($capturedArg, return: true);`
     * after the call, run once to capture, paste into `assertSame(<literal>, $capturedArg)`, remove echo.
     *
     * Pins the complete derived payload passed to upsertPlayer via processPlrData.
     */
    public function testProcessPlrDataCapturesFullDerivedPayload(): void
    {
        $capturedArg = null;
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('upsertPlayer')
            ->willReturnCallback(static function (array $arg) use (&$capturedArg): int {
                $capturedArg = $arg;
                return 1;
            });

        $service = $this->buildService(repo: $mockRepo);
        $service->processPlrData($this->buildCharacterizationFixture());

        $this->assertSame([
            'ordinal' => 1,
            'name' => '00000000000000000000000000000000',
            'age' => 0,
            'pid' => 1,
            'teamid' => 0,
            'peak' => 0,
            'pos' => '00',
            'realLifeGP' => 0,
            'realLifeMIN' => 1000,
            'realLifeFGM' => 0,
            'realLifeFGA' => 0,
            'realLifeFTM' => 0,
            'realLifeFTA' => 0,
            'realLife3GM' => 0,
            'realLife3GA' => 0,
            'realLifeORB' => 0,
            'realLifeDRB' => 0,
            'realLifeAST' => 0,
            'realLifeSTL' => 0,
            'realLifeTVR' => 0,
            'realLifeBLK' => 0,
            'realLifePF' => 100,
            'unk_112' => 0,
            'unk_114' => 0,
            'unk_116' => 0,
            'unk_118' => 0,
            'unk_120' => 0,
            'unk_122' => 0,
            'unk_124' => 0,
            'unk_126' => 0,
            'clutch' => 0,
            'consistency' => 0,
            'PGDepth' => 0,
            'SGDepth' => 0,
            'SFDepth' => 0,
            'PFDepth' => 0,
            'CDepth' => 0,
            'canPlayInGame' => 0,
            'unk_138' => 0,
            'injuryDaysLeft' => 0,
            'seasonGamesStarted' => 0,
            'seasonGamesPlayed' => 0,
            'seasonMIN' => 0,
            'season2GM' => 0,
            'season2GA' => 0,
            'seasonFTM' => 0,
            'seasonFTA' => 0,
            'season3GM' => 0,
            'season3GA' => 0,
            'seasonORB' => 0,
            'seasonDRB' => 0,
            'seasonAST' => 0,
            'seasonSTL' => 0,
            'seasonTVR' => 0,
            'seasonBLK' => 0,
            'seasonPF' => 0,
            'playoffSeasonGP' => 0,
            'playoffSeasonMIN' => 0,
            'playoffSeason2GM' => 0,
            'playoffSeason2GA' => 0,
            'playoffSeasonFTM' => 0,
            'playoffSeasonFTA' => 0,
            'playoffSeason3GM' => 0,
            'playoffSeason3GA' => 0,
            'playoffSeasonORB' => 0,
            'playoffSeasonDRB' => 0,
            'playoffSeasonAST' => 0,
            'playoffSeasonSTL' => 0,
            'playoffSeasonTVR' => 0,
            'playoffSeasonBLK' => 0,
            'playoffSeasonPF' => 0,
            'talent' => 0,
            'skill' => 0,
            'intangibles' => 0,
            'coach' => 0,
            'loyalty' => 0,
            'playingTime' => 0,
            'playForWinner' => 0,
            'tradition' => 0,
            'security' => 0,
            'exp' => 5,
            'bird' => 0,
            'currentContractYear' => 2,
            'totalContractYears' => 0,
            'unk_294' => 0,
            'unk_296' => 0,
            'contractYear1' => 0,
            'contractYear2' => 550,
            'contractYear3' => 0,
            'contractYear4' => 0,
            'contractYear5' => 0,
            'contractYear6' => 0,
            'unk_322' => 0,
            'unk_324' => 0,
            'draftRound' => 0,
            'draftPickNumber' => 0,
            'freeAgentSigningFlag' => 0,
            'unk_331' => 0,
            'unk_333' => 0,
            'unk_335' => 0,
            'unk_337' => 0,
            'unk_339' => 0,
            'seasonHighPTS' => 0,
            'seasonHighREB' => 0,
            'seasonHighAST' => 0,
            'seasonHighSTL' => 0,
            'seasonHighBLK' => 0,
            'seasonHighDoubleDoubles' => 0,
            'seasonHighTripleDoubles' => 0,
            'seasonPlayoffHighPTS' => 0,
            'seasonPlayoffHighREB' => 0,
            'seasonPlayoffHighAST' => 0,
            'seasonPlayoffHighSTL' => 0,
            'seasonPlayoffHighBLK' => 0,
            'careerSeasonHighPTS' => 0,
            'careerSeasonHighREB' => 0,
            'careerSeasonHighAST' => 0,
            'careerSeasonHighSTL' => 0,
            'careerSeasonHighBLK' => 0,
            'careerSeasonHighDoubleDoubles' => 0,
            'careerSeasonHighTripleDoubles' => 0,
            'careerPlayoffHighPTS' => 0,
            'careerPlayoffHighREB' => 0,
            'careerPlayoffHighAST' => 0,
            'careerPlayoffHighSTL' => 0,
            'careerPlayoffHighBLK' => 0,
            'careerGP' => 0,
            'careerMIN' => 0,
            'career2GM' => 0,
            'career2GA' => 0,
            'careerFTM' => 0,
            'careerFTA' => 0,
            'career3GM' => 0,
            'career3GA' => 0,
            'careerORB' => 0,
            'careerDRB' => 0,
            'careerAST' => 0,
            'careerSTL' => 0,
            'careerTVR' => 0,
            'careerBLK' => 0,
            'careerPF' => 0,
            'unk_512' => 0,
            'unk_514' => 0,
            'unk_516' => 0,
            'unk_518' => 0,
            'unk_520' => 0,
            'unk_522' => 0,
            'unk_524' => 0,
            'unk_526' => 0,
            'unk_528' => 0,
            'unk_530' => 0,
            'unk_532' => 0,
            'unk_534' => 0,
            'unk_536' => 0,
            'unk_538' => 0,
            'unk_540' => 0,
            'unk_542' => 0,
            'unk_544' => 0,
            'unk_546' => 0,
            'unk_548' => 0,
            'heightInches' => 75,
            'weight' => 0,
            'rating2GA' => 0,
            'rating2GP' => 0,
            'ratingFTA' => 0,
            'ratingFTP' => 0,
            'rating3GA' => 0,
            'rating3GP' => 0,
            'ratingORB' => 0,
            'ratingDRB' => 0,
            'ratingAST' => 0,
            'ratingSTL' => 0,
            'ratingTVR' => 0,
            'ratingBLK' => 0,
            'ratingOO' => 0,
            'ratingDO' => 0,
            'ratingPO' => 0,
            'ratingTO' => 0,
            'ratingOD' => 0,
            'ratingDD' => 0,
            'ratingPD' => 0,
            'ratingTD' => 0,
            'seasonFGM' => 0,
            'seasonFGA' => 0,
            'seasonREB' => 0,
            'seasonPTS' => 0,
            'careerFGM' => 0,
            'careerFGA' => 0,
            'careerREB' => 0,
            'careerPTS' => 0,
            'currentSeasonSalary' => 550,
            'heightFT' => 6,
            'heightIN' => 3,
            'draftYear' => 2021,
            'ratingFOUL' => 0,
        ], $capturedArg);
    }

    /**
     * CAPTURE PROCEDURE: for full-array tests, add `echo var_export($capturedArg, return: true);`
     * after the call, run once to capture, paste into `assertSame(<literal>, $capturedArg)`, remove echo.
     *
     * Pins the complete snapshot payload passed to upsertSnapshot via processPlrDataForYear in Snapshot mode.
     * Additionally asserts: draftyear=1996, season_year=2001, snapshot_phase='end-of-season'.
     */
    public function testProcessPlrDataForYearSnapshotCapturesFullSnapshotPayload(): void
    {
        $capturedArg = null;
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('upsertSnapshot')
            ->willReturnCallback(static function (array $arg) use (&$capturedArg): int {
                $capturedArg = $arg;
                return 1;
            });

        $service = $this->buildService(repo: $mockRepo);
        $service->processPlrDataForYear(
            $this->buildCharacterizationFixture(exp: 5),
            2001,
            PlrImportMode::Snapshot,
            'end-of-season',
            'archive-2001',
        );

        $this->assertSame([
            'pid' => 1,
            'name' => '00000000000000000000000000000000',
            'season_year' => 2001,
            'snapshot_phase' => 'end-of-season',
            'source_archive' => 'archive-2001',
            'ordinal' => 1,
            'teamid' => 0,
            'age' => 0,
            'pos' => '00',
            'peak' => 0,
            'htft' => 6,
            'htin' => 3,
            'wt' => 0,
            'oo' => 0,
            'od' => 0,
            'r_drive_off' => 0,
            'dd' => 0,
            'po' => 0,
            'pd' => 0,
            'r_trans_off' => 0,
            'td' => 0,
            'r_fga' => 0,
            'r_fgp' => 0,
            'r_fta' => 0,
            'r_ftp' => 0,
            'r_3ga' => 0,
            'r_3gp' => 0,
            'r_orb' => 0,
            'r_drb' => 0,
            'r_ast' => 0,
            'r_stl' => 0,
            'r_tvr' => 0,
            'r_blk' => 0,
            'r_foul' => 0,
            'talent' => 0,
            'skill' => 0,
            'intangibles' => 0,
            'clutch' => 0,
            'consistency' => 0,
            'exp' => 5,
            'bird' => 0,
            'cy' => 2,
            'cyt' => 0,
            'salary_yr1' => 0,
            'salary_yr2' => 550,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'pg_depth' => 0,
            'sg_depth' => 0,
            'sf_depth' => 0,
            'pf_depth' => 0,
            'c_depth' => 0,
            'stats_gs' => 0,
            'stats_gm' => 0,
            'stats_min' => 0,
            'stats_fgm' => 0,
            'stats_fga' => 0,
            'stats_ftm' => 0,
            'stats_fta' => 0,
            'stats_3gm' => 0,
            'stats_3ga' => 0,
            'stats_orb' => 0,
            'stats_drb' => 0,
            'stats_ast' => 0,
            'stats_stl' => 0,
            'stats_tvr' => 0,
            'stats_blk' => 0,
            'stats_pf' => 0,
            'stats_reb' => 0,
            'stats_pts' => 0,
            'po_stats_gm' => 0,
            'po_stats_min' => 0,
            'po_stats_2gm' => 0,
            'po_stats_2ga' => 0,
            'po_stats_ftm' => 0,
            'po_stats_fta' => 0,
            'po_stats_3gm' => 0,
            'po_stats_3ga' => 0,
            'po_stats_orb' => 0,
            'po_stats_drb' => 0,
            'po_stats_ast' => 0,
            'po_stats_stl' => 0,
            'po_stats_tvr' => 0,
            'po_stats_blk' => 0,
            'po_stats_pf' => 0,
            'car_gm' => 0,
            'car_min' => 0,
            'car_fgm' => 0,
            'car_fga' => 0,
            'car_ftm' => 0,
            'car_fta' => 0,
            'car_3gm' => 0,
            'car_3ga' => 0,
            'car_orb' => 0,
            'car_drb' => 0,
            'car_reb' => 0,
            'car_ast' => 0,
            'car_stl' => 0,
            'car_tvr' => 0,
            'car_blk' => 0,
            'car_pf' => 0,
            'car_pts' => 0,
            'sh_pts' => 0,
            'sh_reb' => 0,
            'sh_ast' => 0,
            'sh_stl' => 0,
            'sh_blk' => 0,
            's_dd' => 0,
            's_td' => 0,
            'sp_pts' => 0,
            'sp_reb' => 0,
            'sp_ast' => 0,
            'sp_stl' => 0,
            'sp_blk' => 0,
            'ch_pts' => 0,
            'ch_reb' => 0,
            'ch_ast' => 0,
            'ch_stl' => 0,
            'ch_blk' => 0,
            'c_dd' => 0,
            'c_td' => 0,
            'cp_pts' => 0,
            'cp_reb' => 0,
            'cp_ast' => 0,
            'cp_stl' => 0,
            'cp_blk' => 0,
            'rl_gp' => 0,
            'rl_min' => 1000,
            'rl_fgm' => 0,
            'rl_fga' => 0,
            'rl_ftm' => 0,
            'rl_fta' => 0,
            'rl_3gm' => 0,
            'rl_3ga' => 0,
            'rl_orb' => 0,
            'rl_drb' => 0,
            'rl_ast' => 0,
            'rl_stl' => 0,
            'rl_tvr' => 0,
            'rl_blk' => 0,
            'rl_pf' => 100,
            'coach' => 0,
            'loyalty' => 0,
            'playing_time' => 0,
            'winner' => 0,
            'tradition' => 0,
            'security' => 0,
            'draftround' => 0,
            'draftpickno' => 0,
            'fa_signing_flag' => 0,
            'dc_can_play_in_game' => 0,
            'injured' => 0,
            'draftyear' => 1996,
            'salary' => 550,
            'unk_112' => 0,
            'unk_114' => 0,
            'unk_116' => 0,
            'unk_118' => 0,
            'unk_120' => 0,
            'unk_122' => 0,
            'unk_124' => 0,
            'unk_126' => 0,
            'unk_138' => 0,
            'unk_294' => 0,
            'unk_296' => 0,
            'unk_322' => 0,
            'unk_324' => 0,
            'unk_331' => 0,
            'unk_333' => 0,
            'unk_335' => 0,
            'unk_337' => 0,
            'unk_339' => 0,
            'unk_512' => 0,
            'unk_514' => 0,
            'unk_516' => 0,
            'unk_518' => 0,
            'unk_520' => 0,
            'unk_522' => 0,
            'unk_524' => 0,
            'unk_526' => 0,
            'unk_528' => 0,
            'unk_530' => 0,
            'unk_532' => 0,
            'unk_534' => 0,
            'unk_536' => 0,
            'unk_538' => 0,
            'unk_540' => 0,
            'unk_542' => 0,
            'unk_544' => 0,
            'unk_546' => 0,
            'unk_548' => 0,
        ], $capturedArg);
        // draftyear=1996, season_year=2001, snapshot_phase='end-of-season' are
        // pinned by the full assertSame literal above (lines 'draftyear' => 1996,
        // 'season_year' => 2001, 'snapshot_phase' => 'end-of-season').
    }

    /**
     * Distinct-value companion to the zero-fixture test above: every snapshot
     * key is pinned to its own source field, so a section extraction that maps
     * a key to a neighbouring source fails here.
     */
    public function testSnapshotPayloadPinsEveryFieldWithDistinctValues(): void
    {
        $capturedArg = null;
        $mockRepo = $this->createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('upsertSnapshot')
            ->willReturnCallback(static function (array $arg) use (&$capturedArg): int {
                $capturedArg = $arg;
                return 1;
            });

        $service = $this->buildService(repo: $mockRepo);
        $service->processPlrDataForYear(
            $this->buildDistinctValueLine(),
            2001,
            PlrImportMode::Snapshot,
            'end-of-season',
            'archive-2001',
        );

        $this->assertIsArray($capturedArg);
        $this->assertSame(
        [
            'pid' => 42,
            'name' => '18529630741852963074185296307418',
            'season_year' => 2001,
            'snapshot_phase' => 'end-of-season',
            'source_archive' => 'archive-2001',
            'ordinal' => 7,
            'teamid' => 18,
            'age' => 52,
            'pos' => '30',
            'peak' => 5296,
            'htft' => 6,
            'htin' => 3,
            'wt' => 741,
            'oo' => 7,
            'od' => 63,
            'r_drive_off' => 41,
            'dd' => 7,
            'po' => 85,
            'pd' => 41,
            'r_trans_off' => 29,
            'td' => 85,
            'r_fga' => 852,
            'r_fgp' => 963,
            'r_fta' => 74,
            'r_ftp' => 185,
            'r_3ga' => 296,
            'r_3gp' => 307,
            'r_orb' => 418,
            'r_drb' => 529,
            'r_ast' => 630,
            'r_stl' => 741,
            'r_tvr' => 852,
            'r_blk' => 963,
            'r_foul' => 0,
            'talent' => 96,
            'skill' => 30,
            'intangibles' => 74,
            'clutch' => 96,
            'consistency' => 30,
            'exp' => 5,
            'bird' => 96,
            'cy' => 2,
            'cyt' => 74,
            'salary_yr1' => 9630,
            'salary_yr2' => 550,
            'salary_yr3' => 5296,
            'salary_yr4' => 3074,
            'salary_yr5' => 1852,
            'salary_yr6' => 9630,
            'pg_depth' => 7,
            'sg_depth' => 4,
            'sf_depth' => 1,
            'pf_depth' => 8,
            'c_depth' => 5,
            'stats_gs' => 1852,
            'stats_gm' => 9630,
            'stats_min' => 7418,
            'stats_fgm' => 12714,
            'stats_fga' => 8370,
            'stats_ftm' => 1852,
            'stats_fta' => 9630,
            'stats_3gm' => 7418,
            'stats_3ga' => 5296,
            'stats_orb' => 3074,
            'stats_drb' => 1852,
            'stats_ast' => 9630,
            'stats_stl' => 7418,
            'stats_tvr' => 5296,
            'stats_blk' => 3074,
            'stats_pf' => 1852,
            'stats_reb' => 4926,
            'stats_pts' => 34698,
            'po_stats_gm' => 9630,
            'po_stats_min' => 7418,
            'po_stats_2gm' => 5296,
            'po_stats_2ga' => 3074,
            'po_stats_ftm' => 1852,
            'po_stats_fta' => 9630,
            'po_stats_3gm' => 7418,
            'po_stats_3ga' => 5296,
            'po_stats_orb' => 3074,
            'po_stats_drb' => 1852,
            'po_stats_ast' => 9630,
            'po_stats_stl' => 7418,
            'po_stats_tvr' => 5296,
            'po_stats_blk' => 3074,
            'po_stats_pf' => 1852,
            'car_gm' => 29630,
            'car_min' => 74185,
            'car_fgm' => 59260,
            'car_fga' => 148370,
            'car_ftm' => 29630,
            'car_fta' => 74185,
            'car_3gm' => 29630,
            'car_3ga' => 74185,
            'car_orb' => 29630,
            'car_drb' => 74185,
            'car_reb' => 103815,
            'car_ast' => 29630,
            'car_stl' => 74185,
            'car_tvr' => 29630,
            'car_blk' => 74185,
            'car_pf' => 29630,
            'car_pts' => 177780,
            'sh_pts' => 7,
            'sh_reb' => 41,
            'sh_ast' => 85,
            'sh_stl' => 29,
            'sh_blk' => 63,
            's_dd' => 7,
            's_td' => 41,
            'sp_pts' => 85,
            'sp_reb' => 29,
            'sp_ast' => 63,
            'sp_stl' => 7,
            'sp_blk' => 41,
            'ch_pts' => 852963,
            'ch_reb' => 74185,
            'ch_ast' => 296307,
            'ch_stl' => 418529,
            'ch_blk' => 630741,
            'c_dd' => 852963,
            'c_td' => 74185,
            'cp_pts' => 296307,
            'cp_reb' => 418529,
            'cp_ast' => 630741,
            'cp_stl' => 852963,
            'cp_blk' => 74185,
            'rl_gp' => 7418,
            'rl_min' => 1000,
            'rl_fgm' => 3074,
            'rl_fga' => 1852,
            'rl_ftm' => 9630,
            'rl_fta' => 7418,
            'rl_3gm' => 5296,
            'rl_3ga' => 3074,
            'rl_orb' => 1852,
            'rl_drb' => 9630,
            'rl_ast' => 7418,
            'rl_stl' => 5296,
            'rl_tvr' => 3074,
            'rl_blk' => 1852,
            'rl_pf' => 100,
            'coach' => 18,
            'loyalty' => 52,
            'playing_time' => 96,
            'winner' => 30,
            'tradition' => 74,
            'security' => 18,
            'draftround' => 52,
            'draftpickno' => 96,
            'fa_signing_flag' => 3,
            'dc_can_play_in_game' => 2,
            'injured' => 3074,
            'draftyear' => 1996,
            'salary' => 550,
            'unk_112' => 74,
            'unk_114' => 18,
            'unk_116' => 52,
            'unk_118' => 96,
            'unk_120' => 30,
            'unk_122' => 74,
            'unk_124' => 18,
            'unk_126' => 52,
            'unk_138' => 96,
            'unk_294' => 18,
            'unk_296' => 52,
            'unk_322' => 74,
            'unk_324' => 18,
            'unk_331' => 7,
            'unk_333' => 41,
            'unk_335' => 85,
            'unk_337' => 29,
            'unk_339' => 63,
            'unk_512' => 74,
            'unk_514' => 18,
            'unk_516' => 52,
            'unk_518' => 96,
            'unk_520' => 30,
            'unk_522' => 74,
            'unk_524' => 18,
            'unk_526' => 52,
            'unk_528' => 96,
            'unk_530' => 30,
            'unk_532' => 74,
            'unk_534' => 18,
            'unk_536' => 52,
            'unk_538' => 96,
            'unk_540' => 30,
            'unk_542' => 74,
            'unk_544' => 18,
            'unk_546' => 52,
            'unk_548' => 96,
        ],
            $capturedArg,
        );
    }

    /**
     * Pins the foul baseline message to full precision.
     *
     * Kills the "remove addMessage" mutant AND the formatWithDecimals(…, 6) literal-6 mutant.
     */
    public function testProcessPlrDataResultIncludesFoulBaselineMessage(): void
    {
        $service = $this->buildService();
        $result = $service->processPlrData($this->buildCharacterizationFixture());

        $this->assertSame(
            ['Foul baseline calculated (max ratio: 0.100000)'],
            $result->messages,
        );
    }
}
