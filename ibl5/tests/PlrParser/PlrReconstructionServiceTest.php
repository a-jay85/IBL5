<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PlrParser\PlrFieldSerializer;
use PlrParser\PlrFileWriter;
use PlrParser\PlrTeamRowLayout;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrBoxScoreRepositoryInterface;
use PlrParser\PlrReconstructionService;

/**
 * @covers \PlrParser\PlrReconstructionService
 */
class PlrReconstructionServiceTest extends TestCase
{
    private const LEBRON_PID = 5258;
    private const MCGRADY_PID = 1480;
    private const BENCHWARMER_PID = 9999;

    private string $baseFile = '';
    private string $outputFile = '';

    protected function tearDown(): void
    {
        foreach ([$this->baseFile, $this->outputFile] as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function testReconstructUpdatesSeasonStatsForPlayersWithBoxScores(): void
    {
        $baseRecord = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->zeroSeasonStats(),
        );
        $this->writeBaseFile([$baseRecord]);

        $repo = $this->stubRepo([
            self::LEBRON_PID => $this->statsArray(gp: 22, min: 788, twoGm: 201, twoGa: 380, ftm: 87, fta: 120, threeGm: 37, threeGa: 92, orb: 65, drb: 152, ast: 137, stl: 43, tov: 47, blk: 39, pf: 20),
        ]);

        $service = new PlrReconstructionService($repo);
        $result = $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $this->assertSame(1, $result->playersUpdated);
        $this->assertSame(0, $result->playersUnchanged);
        $this->assertFalse($result->hasErrors());

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(22, (int) trim(substr($actual, 148, 4)), 'seasonGamesPlayed');
        $this->assertSame(788, (int) trim(substr($actual, 152, 4)), 'seasonMIN');
        $this->assertSame(201, (int) trim(substr($actual, 156, 4)), 'season2GM');
        $this->assertSame(37, (int) trim(substr($actual, 172, 4)), 'season3GM');
    }

    public function testReconstructZeroesSeasonStatsForPidsMissingFromBoxScores(): void
    {
        $baseRecord = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::BENCHWARMER_PID,
            name: 'Unplayed Bench',
            seasonStats: $this->statsArray(gp: 5, min: 42, twoGm: 3, twoGa: 8, ftm: 1, fta: 2, threeGm: 0, threeGa: 1, orb: 2, drb: 3, ast: 1, stl: 0, tov: 1, blk: 0, pf: 4),
        );
        $this->writeBaseFile([$baseRecord]);

        $repo = $this->stubRepo([]);

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(0, (int) trim(substr($actual, 148, 4)), 'seasonGamesPlayed must zero');
        $this->assertSame(0, (int) trim(substr($actual, 152, 4)), 'seasonMIN must zero');
        $this->assertSame(0, (int) trim(substr($actual, 156, 4)), 'season2GM must zero');
    }

    public function testReconstructLeavesOrdinalAndIdentityBytesUntouched(): void
    {
        $base = $this->buildPlayerRecord(
            ordinal: 7,
            pid: self::MCGRADY_PID,
            name: 'Tracy McGrady',
            seasonStats: $this->zeroSeasonStats(),
        );
        $this->writeBaseFile([$base]);

        $repo = $this->stubRepo([
            self::MCGRADY_PID => $this->statsArray(gp: 23, min: 888, twoGm: 222, twoGa: 443, ftm: 41, fta: 47, threeGm: 35, threeGa: 84, orb: 67, drb: 85, ast: 59, stl: 40, tov: 40, blk: 16, pf: 37),
        ]);

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-13', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(7, (int) trim(substr($actual, 0, 4)), 'ordinal preserved');
        $this->assertSame(self::MCGRADY_PID, (int) trim(substr($actual, 38, 6)), 'pid preserved');
        $this->assertSame('Tracy McGrady', trim(substr($actual, 4, 32)), 'name preserved');
    }

    public function testReconstructPreservesRecordLength(): void
    {
        $base = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->zeroSeasonStats(),
        );
        $this->writeBaseFile([$base]);

        $repo = $this->stubRepo([
            self::LEBRON_PID => $this->statsArray(gp: 1, min: 1, twoGm: 1, twoGa: 1, ftm: 1, fta: 1, threeGm: 1, threeGa: 1, orb: 1, drb: 1, ast: 1, stl: 1, tov: 1, blk: 1, pf: 1),
        ]);

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $inputSize = filesize($this->baseFile);
        $outputSize = filesize($this->outputFile);
        $this->assertSame($inputSize, $outputSize);

        $line = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(PlrFileWriter::PLAYER_RECORD_LENGTH, strlen($line));
    }

    public function testReconstructSkipsRowsBeyondPlayerOrdinalRange(): void
    {
        $player = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->zeroSeasonStats(),
        );
        $teamRow = $this->buildPlayerRecord(
            ordinal: 1441,
            pid: 0,
            name: 'Celtics',
            seasonStats: $this->statsArray(gp: 100, min: 200, twoGm: 5, twoGa: 10, ftm: 1, fta: 2, threeGm: 0, threeGa: 0, orb: 1, drb: 1, ast: 1, stl: 0, tov: 0, blk: 0, pf: 1),
        );
        $this->writeBaseFile([$player, $teamRow]);

        $repo = $this->stubRepo([
            self::LEBRON_PID => $this->statsArray(gp: 9, min: 9, twoGm: 9, twoGa: 9, ftm: 9, fta: 9, threeGm: 9, threeGa: 9, orb: 9, drb: 9, ast: 9, stl: 9, tov: 9, blk: 9, pf: 9),
        ]);

        $service = new PlrReconstructionService($repo);
        $result = $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $this->assertSame(1, $result->playersUpdated, 'only the LeBron row should be touched');

        $teamLine = $this->readPlayerLine($this->outputFile, 1);
        $this->assertSame(100, (int) trim(substr($teamLine, 148, 4)), 'team row season stats must be preserved byte-for-byte');
    }

    public function testSeasonGamesStartedPureBenchBranchStaysZero(): void
    {
        $base = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->statsArray(gp: 5, min: 42, twoGm: 3, twoGa: 8, ftm: 1, fta: 2, threeGm: 0, threeGa: 1, orb: 2, drb: 3, ast: 1, stl: 0, tov: 1, blk: 0, pf: 4),
        );
        $this->writeBaseFile([$base]);

        $repo = $this->stubRepo([
            self::LEBRON_PID => $this->statsArray(gp: 10, min: 100, twoGm: 5, twoGa: 15, ftm: 2, fta: 3, threeGm: 1, threeGa: 2, orb: 3, drb: 4, ast: 2, stl: 1, tov: 2, blk: 1, pf: 6),
        ]);

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(0, (int) trim(substr($actual, 144, 4)), 'pure bench: gs stays 0');
        $this->assertSame(10, (int) trim(substr($actual, 148, 4)), 'gp still updates');
    }

    public function testSeasonGamesStartedPureStarterBranchMatchesGp(): void
    {
        $base = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->statsArray(gp: 20, min: 775, twoGm: 0, twoGa: 0, ftm: 0, fta: 0, threeGm: 0, threeGa: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0),
            gs: 20,
        );
        $this->writeBaseFile([$base]);

        $repo = $this->stubRepo([
            self::LEBRON_PID => $this->statsArray(gp: 26, min: 1004, twoGm: 0, twoGa: 0, ftm: 0, fta: 0, threeGm: 0, threeGa: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0),
        ]);

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(26, (int) trim(substr($actual, 144, 4)), 'pure starter: gs tracks new gp');
        $this->assertSame(26, (int) trim(substr($actual, 148, 4)));
    }

    public function testSeasonGamesStartedMixedBranchProRates(): void
    {
        // Base: 20 gp, 10 gs → 50% starter rate
        // New: 26 gp → 13 gs expected (round(26 * 0.5))
        $base = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'Swingman Smith',
            seasonStats: $this->statsArray(gp: 20, min: 500, twoGm: 0, twoGa: 0, ftm: 0, fta: 0, threeGm: 0, threeGa: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0),
            gs: 10,
        );
        $this->writeBaseFile([$base]);

        $repo = $this->stubRepo([
            self::LEBRON_PID => $this->statsArray(gp: 26, min: 650, twoGm: 0, twoGa: 0, ftm: 0, fta: 0, threeGm: 0, threeGa: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0),
        ]);

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(13, (int) trim(substr($actual, 144, 4)), 'mixed: pro-rate round(26 * 10/20) = 13');
    }

    public function testReconstructCountsPlayersUnchangedWhenBaseAlreadyMatches(): void
    {
        $matching = $this->statsArray(gp: 10, min: 360, twoGm: 40, twoGa: 80, ftm: 10, fta: 12, threeGm: 5, threeGa: 15, orb: 20, drb: 30, ast: 25, stl: 5, tov: 8, blk: 2, pf: 12);
        $base = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $matching,
            gs: 10,
            careerStats: $this->careerArray(gp: 10, min: 360, twoGm: 40, twoGa: 80, ftm: 10, fta: 12, threeGm: 5, threeGa: 15, orb: 20, drb: 30, ast: 25, stl: 5, tov: 8, blk: 2, pf: 12),
        );
        $this->writeBaseFile([$base]);

        $repo = $this->stubRepo([self::LEBRON_PID => $matching]);

        $service = new PlrReconstructionService($repo);
        $result = $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $this->assertSame(0, $result->playersUpdated);
        $this->assertSame(1, $result->playersUnchanged);
    }

    public function testCareerTotalsArePreservedFromBase(): void
    {
        // The .plr format freezes career totals at the start of each season — they don't
        // track mid-season deltas (verified empirically: sim05/sim07 real .plr files carry
        // identical career_gp / career_min). Reconstruction must preserve them from the base.
        $base = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->statsArray(gp: 10, min: 300, twoGm: 0, twoGa: 0, ftm: 0, fta: 0, threeGm: 0, threeGa: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0),
            gs: 10,
            careerStats: $this->careerArray(gp: 500, min: 15000, twoGm: 0, twoGa: 0, ftm: 0, fta: 0, threeGm: 0, threeGa: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0),
        );
        $this->writeBaseFile([$base]);

        $repo = $this->stubRepo([
            self::LEBRON_PID => $this->statsArray(gp: 15, min: 450, twoGm: 0, twoGa: 0, ftm: 0, fta: 0, threeGm: 0, threeGa: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0),
        ]);

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(500, (int) trim(substr($actual, 437, 5)), 'careerGP preserved at 500');
        $this->assertSame(15000, (int) trim(substr($actual, 442, 5)), 'careerMIN preserved at 15000');
    }

    public function testPlayoffStatsGetWrittenFromPlayoffAggregates(): void
    {
        $base = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->zeroSeasonStats(),
        );
        $this->writeBaseFile([$base]);

        $playoffStats = $this->statsArray(gp: 4, min: 160, twoGm: 25, twoGa: 50, ftm: 10, fta: 12, threeGm: 5, threeGa: 14, orb: 8, drb: 20, ast: 15, stl: 3, tov: 6, blk: 1, pf: 9);
        $repo = $this->stubRepo(regular: [], playoffs: [self::LEBRON_PID => $playoffStats]);

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2007-05-01', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(4, (int) trim(substr($actual, 208, 4)), 'playoffSeasonGP');
        $this->assertSame(160, (int) trim(substr($actual, 212, 4)), 'playoffSeasonMIN');
        $this->assertSame(25, (int) trim(substr($actual, 216, 4)), 'playoffSeason2GM');
        $this->assertSame(5, (int) trim(substr($actual, 232, 4)), 'playoffSeason3GM');
    }

    public function testSeasonHighsGetWrittenFromMaximums(): void
    {
        $base = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->zeroSeasonStats(),
        );
        $this->writeBaseFile([$base]);

        $repo = $this->stubRepo(
            regular: [self::LEBRON_PID => $this->statsArray(gp: 22, min: 788, twoGm: 201, twoGa: 380, ftm: 87, fta: 120, threeGm: 37, threeGa: 92, orb: 65, drb: 152, ast: 137, stl: 43, tov: 47, blk: 39, pf: 20)],
            regularHighs: [self::LEBRON_PID => ['high_pts' => 42, 'high_reb' => 12, 'high_ast' => 14, 'high_stl' => 6, 'high_blk' => 4, 'doubles' => 7, 'triples' => 2]],
        );

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(42, (int) trim(substr($actual, 341, 2)), 'seasonHighPTS');
        $this->assertSame(12, (int) trim(substr($actual, 343, 2)), 'seasonHighREB');
        $this->assertSame(14, (int) trim(substr($actual, 345, 2)), 'seasonHighAST');
        $this->assertSame(2, (int) trim(substr($actual, 353, 2)), 'seasonHighTripleDoubles');
        $this->assertSame(42, (int) trim(substr($actual, 365, 6)), 'careerSeasonHighPTS updated');
    }

    public function testCareerBestHighsAreMonotonic(): void
    {
        // Base career-best pts = 50, new season high = 30 → career best stays 50
        $base = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->zeroSeasonStats(),
            careerBestPts: 50,
        );
        $this->writeBaseFile([$base]);

        $repo = $this->stubRepo(
            regular: [self::LEBRON_PID => $this->statsArray(gp: 10, min: 300, twoGm: 0, twoGa: 0, ftm: 0, fta: 0, threeGm: 0, threeGa: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0)],
            regularHighs: [self::LEBRON_PID => ['high_pts' => 30, 'high_reb' => 0, 'high_ast' => 0, 'high_stl' => 0, 'high_blk' => 0, 'doubles' => 0, 'triples' => 0]],
        );

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 0);
        $this->assertSame(30, (int) trim(substr($actual, 341, 2)), 'seasonHighPTS = 30');
        $this->assertSame(50, (int) trim(substr($actual, 365, 6)), 'careerSeasonHighPTS stays 50 (monotonic)');
    }

    /**
     * @param array<int, array{gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}> $regular
     * @param array<int, array{gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}> $playoffs
     * @param array<int, array{high_pts: int, high_reb: int, high_ast: int, high_stl: int, high_blk: int, doubles: int, triples: int}> $regularHighs
     * @param array<int, array{high_pts: int, high_reb: int, high_ast: int, high_stl: int, high_blk: int, doubles: int, triples: int}> $playoffHighs
     * @param array<int, array{gp: int, gpAlt: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}> $teamStats
     * @param array<int, array{gp: int, gpAlt: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}> $teamPlayoffStats
     */
    private function stubRepo(
        array $regular,
        array $playoffs = [],
        array $regularHighs = [],
        array $playoffHighs = [],
        array $teamStats = [],
        array $teamPlayoffStats = [],
    ): PlrBoxScoreRepositoryInterface {
        $repo = $this->createStub(PlrBoxScoreRepositoryInterface::class);
        $repo->method('sumStatsByGameTypeThroughDate')->willReturnCallback(
            static function (int $seasonYear, int $gameType, string $endDate) use ($regular, $playoffs): array {
                return $gameType === PlrBoxScoreRepositoryInterface::GAME_TYPE_REGULAR_SEASON
                    ? $regular
                    : $playoffs;
            },
        );
        $repo->method('getSingleGameMaximumsThroughDate')->willReturnCallback(
            static function (int $seasonYear, int $gameType, string $endDate) use ($regularHighs, $playoffHighs): array {
                return $gameType === PlrBoxScoreRepositoryInterface::GAME_TYPE_REGULAR_SEASON
                    ? $regularHighs
                    : $playoffHighs;
            },
        );
        $repo->method('sumTeamRegularSeasonStatsThroughDate')->willReturn($teamStats);
        $repo->method('sumTeamPlayoffStatsThroughDate')->willReturn($teamPlayoffStats);
        return $repo;
    }

    public function testReconstructUpdatesTeamRowsFromTeamBoxScores(): void
    {
        $player = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->zeroSeasonStats(),
        );
        $teamRow = $this->buildTeamRecord(ordinal: 1441, stats: [
            'gp' => 0, 'gpAlt' => 0, 'twoGM' => 0, 'twoGA' => 0,
            'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0,
            'orb' => 0, 'drb' => 0, 'ast' => 0, 'stl' => 0,
            'tov' => 0, 'blk' => 0, 'pf' => 0,
        ]);
        $this->writeBaseFile([$player, $teamRow]);

        $teamBoxScores = [
            1 => [
                'gp' => 18, 'gpAlt' => 18, 'twoGM' => 420, 'twoGA' => 900,
                'ftm' => 180, 'fta' => 230, 'threeGM' => 100, 'threeGA' => 280,
                'orb' => 90, 'drb' => 310, 'ast' => 250, 'stl' => 80,
                'tov' => 120, 'blk' => 50, 'pf' => 200,
            ],
        ];
        $repo = $this->stubRepo(
            regular: [self::LEBRON_PID => $this->statsArray(gp: 18, min: 600, twoGm: 0, twoGa: 0, ftm: 0, fta: 0, threeGm: 0, threeGa: 0, orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0)],
            teamStats: $teamBoxScores,
        );

        $service = new PlrReconstructionService($repo);
        $result = $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $this->assertSame(1, $result->teamsUpdated);
        $this->assertSame(0, $result->teamsUnchanged);

        $actual = $this->readPlayerLine($this->outputFile, 1);
        $this->assertSame(607, strlen($actual), 'team row length preserved');
        $this->assertSame(18, (int) trim(substr($actual, 148, 4)), 'team gp');
        $this->assertSame(420, (int) trim(substr($actual, 156, 4)), 'team twoGM');
        $this->assertSame(100, (int) trim(substr($actual, 172, 4)), 'team threeGM');
        $this->assertSame(200, (int) trim(substr($actual, 204, 4)), 'team pf');
    }

    public function testReconstructPreservesTeamRowBytesOutsideKnownFields(): void
    {
        $player = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->zeroSeasonStats(),
        );
        $teamRow = $this->buildTeamRecord(ordinal: 1441, stats: [
            'gp' => 0, 'gpAlt' => 0, 'twoGM' => 0, 'twoGA' => 0,
            'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0,
            'orb' => 0, 'drb' => 0, 'ast' => 0, 'stl' => 0,
            'tov' => 0, 'blk' => 0, 'pf' => 0,
        ]);
        // Plant recognizable bytes outside the validated stat range
        $teamRow = substr_replace($teamRow, 'XY', 300, 2);
        $teamRow = substr_replace($teamRow, 'ZW', 550, 2);
        $this->writeBaseFile([$player, $teamRow]);

        $repo = $this->stubRepo(
            regular: [self::LEBRON_PID => $this->zeroSeasonStats()],
            teamStats: [1 => [
                'gp' => 5, 'gpAlt' => 5, 'twoGM' => 10, 'twoGA' => 20,
                'ftm' => 3, 'fta' => 5, 'threeGM' => 2, 'threeGA' => 8,
                'orb' => 4, 'drb' => 12, 'ast' => 8, 'stl' => 3,
                'tov' => 5, 'blk' => 2, 'pf' => 7,
            ]],
        );

        $service = new PlrReconstructionService($repo);
        $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $actual = $this->readPlayerLine($this->outputFile, 1);
        $this->assertSame('XY', substr($actual, 300, 2), 'byte 300-301 preserved');
        $this->assertSame('ZW', substr($actual, 550, 2), 'byte 550-551 preserved');
    }

    public function testTeamRowWithNoBoxScoresIsCountedAsUnchanged(): void
    {
        $player = $this->buildPlayerRecord(
            ordinal: 1,
            pid: self::LEBRON_PID,
            name: 'LeBron James',
            seasonStats: $this->zeroSeasonStats(),
        );
        $teamRow = $this->buildTeamRecord(ordinal: 1441, stats: [
            'gp' => 10, 'gpAlt' => 10, 'twoGM' => 50, 'twoGA' => 100,
            'ftm' => 20, 'fta' => 25, 'threeGM' => 15, 'threeGA' => 40,
            'orb' => 10, 'drb' => 30, 'ast' => 20, 'stl' => 8,
            'tov' => 12, 'blk' => 5, 'pf' => 18,
        ]);
        $this->writeBaseFile([$player, $teamRow]);

        // No team stats provided — team row should be unchanged
        $repo = $this->stubRepo(
            regular: [self::LEBRON_PID => $this->zeroSeasonStats()],
            teamStats: [],
        );

        $service = new PlrReconstructionService($repo);
        $result = $service->reconstruct($this->baseFile, 2007, '2006-12-20', $this->outputFile);

        $this->assertSame(0, $result->teamsUpdated);
        $this->assertSame(1, $result->teamsUnchanged);

        $actual = $this->readPlayerLine($this->outputFile, 1);
        $this->assertSame(10, (int) trim(substr($actual, 148, 4)), 'team gp preserved');
    }

    /**
     * @param array<string, int> $seasonStats
     * @param array<string, int>|null $careerStats
     */
    private function buildPlayerRecord(
        int $ordinal,
        int $pid,
        string $name,
        array $seasonStats,
        int $gs = 0,
        ?array $careerStats = null,
        int $careerBestPts = 0,
    ): string {
        $record = str_repeat(' ', PlrFileWriter::PLAYER_RECORD_LENGTH);

        $record = substr_replace($record, PlrFieldSerializer::formatInt($ordinal, 4), 0, 4);
        $record = substr_replace($record, str_pad($name, 32, ' ', STR_PAD_RIGHT), 4, 32);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($pid, 6), 38, 6);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(0, 2), 44, 2);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(0, 1), 330, 1);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(0, 2), 331, 2);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(-1, 2), 333, 2);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(-1, 2), 335, 2);

        $seasonOffsets = [
            'gs' => 144,
            'gp' => 148,
            'min' => 152,
            'two_gm' => 156,
            'two_ga' => 160,
            'ftm' => 164,
            'fta' => 168,
            'three_gm' => 172,
            'three_ga' => 176,
            'orb' => 180,
            'drb' => 184,
            'ast' => 188,
            'stl' => 192,
            'tov' => 196,
            'blk' => 200,
            'pf' => 204,
        ];
        foreach ($seasonOffsets as $key => $offset) {
            $value = $key === 'gs' ? $gs : $seasonStats[$key];
            $record = substr_replace($record, PlrFieldSerializer::formatInt($value, 4), $offset, 4);
        }

        if ($careerStats !== null) {
            $careerOffsets = [
                'gp' => 437,
                'min' => 442,
                'two_gm' => 447,
                'two_ga' => 452,
                'ftm' => 457,
                'fta' => 462,
                'three_gm' => 467,
                'three_ga' => 472,
                'orb' => 477,
                'drb' => 482,
                'ast' => 487,
                'stl' => 492,
                'tov' => 497,
                'blk' => 502,
                'pf' => 507,
            ];
            foreach ($careerOffsets as $key => $offset) {
                $record = substr_replace($record, PlrFieldSerializer::formatInt($careerStats[$key], 5), $offset, 5);
            }
        }

        if ($careerBestPts > 0) {
            $record = substr_replace($record, PlrFieldSerializer::formatInt($careerBestPts, 6), 365, 6);
        }

        return $record;
    }

    /**
     * @param list<string> $records
     */
    private function writeBaseFile(array $records): void
    {
        $this->baseFile = (string) tempnam(sys_get_temp_dir(), 'plr_base_');
        $this->outputFile = (string) tempnam(sys_get_temp_dir(), 'plr_out_');
        file_put_contents($this->baseFile, implode("\r\n", $records));
    }

    private function readPlayerLine(string $path, int $index): string
    {
        $content = (string) file_get_contents($path);
        $lines = explode("\r\n", $content);
        return $lines[$index];
    }

    /**
     * @return array{gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}
     */
    private function statsArray(
        int $gp,
        int $min,
        int $twoGm,
        int $twoGa,
        int $ftm,
        int $fta,
        int $threeGm,
        int $threeGa,
        int $orb,
        int $drb,
        int $ast,
        int $stl,
        int $tov,
        int $blk,
        int $pf,
    ): array {
        return [
            'gp' => $gp,
            'min' => $min,
            'two_gm' => $twoGm,
            'two_ga' => $twoGa,
            'ftm' => $ftm,
            'fta' => $fta,
            'three_gm' => $threeGm,
            'three_ga' => $threeGa,
            'orb' => $orb,
            'drb' => $drb,
            'ast' => $ast,
            'stl' => $stl,
            'tov' => $tov,
            'blk' => $blk,
            'pf' => $pf,
        ];
    }

    /**
     * @return array{gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}
     */
    private function careerArray(
        int $gp,
        int $min,
        int $twoGm,
        int $twoGa,
        int $ftm,
        int $fta,
        int $threeGm,
        int $threeGa,
        int $orb,
        int $drb,
        int $ast,
        int $stl,
        int $tov,
        int $blk,
        int $pf,
    ): array {
        return $this->statsArray($gp, $min, $twoGm, $twoGa, $ftm, $fta, $threeGm, $threeGa, $orb, $drb, $ast, $stl, $tov, $blk, $pf);
    }

    /**
     * @return array{gp: int, min: int, two_gm: int, two_ga: int, ftm: int, fta: int, three_gm: int, three_ga: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}
     */
    private function zeroSeasonStats(): array
    {
        return $this->statsArray(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
    }

    /**
     * Build a 608-byte franchise team-summary record for testing.
     *
     * @param array{gp: int, gpAlt: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int} $stats
     */
    private function buildTeamRecord(int $ordinal, array $stats): string
    {
        $record = str_repeat(' ', PlrTeamRowLayout::FRANCHISE_ROW_MIN_LENGTH);

        // Ordinal at offset 0, width 4
        $record = substr_replace($record, PlrFieldSerializer::formatInt($ordinal, 4), 0, 4);
        // pid=0 at offset 38, width 6
        $record = substr_replace($record, PlrFieldSerializer::formatInt(0, 6), 38, 6);

        // Write stats at their validated offsets
        foreach (PlrTeamRowLayout::REGULAR_SEASON_FIELD_MAP as $field => [$offset, $width]) {
            if (isset($stats[$field])) {
                $record = substr_replace(
                    $record,
                    PlrFieldSerializer::formatInt($stats[$field], $width),
                    $offset,
                    $width,
                );
            }
        }

        return $record;
    }
}
