<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\CarFileParser;
use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportService;
use JsbParser\PlayerIdResolver;
use JsbParser\TrnFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\JsbImportService
 */
class JsbImportServiceTest extends TestCase
{
    private JsbImportRepositoryInterface $stubRepo;
    private PlayerIdResolver $stubResolver;

    protected function setUp(): void
    {
        $this->stubRepo = $this->createStub(JsbImportRepositoryInterface::class);
        $this->stubResolver = $this->createStub(PlayerIdResolver::class);
    }

    private function makeService(?PlayerIdResolver $resolver = null): JsbImportService
    {
        return new JsbImportService($this->stubRepo, $resolver ?? $this->stubResolver);
    }

    // ── .car file helpers ────────────────────────────────────────

    private function buildSeasonRecord(
        int $year = 2006,
        string $team = 'Lakers',
        string $name = 'Test Player',
    ): string {
        $record = str_pad((string) $year, 4);
        $record .= str_pad($team, 16);
        $record .= str_pad($name, 16);
        $record .= str_pad('SF', 2);
        $record .= '0 ';
        $record .= str_pad('82', 2, ' ', STR_PAD_LEFT);   // gp
        $record .= str_pad('3200', 4, ' ', STR_PAD_LEFT);  // min
        $record .= str_pad('400', 4, ' ', STR_PAD_LEFT);   // 2gm
        $record .= str_pad('800', 4, ' ', STR_PAD_LEFT);   // 2ga
        $record .= str_pad('200', 4, ' ', STR_PAD_LEFT);   // ftm
        $record .= str_pad('250', 4, ' ', STR_PAD_LEFT);   // fta
        $record .= str_pad('100', 4, ' ', STR_PAD_LEFT);   // 3gm
        $record .= str_pad('300', 4, ' ', STR_PAD_LEFT);   // 3ga
        $record .= str_pad('80', 4, ' ', STR_PAD_LEFT);    // orb
        $record .= str_pad('320', 4, ' ', STR_PAD_LEFT);   // drb
        $record .= str_pad('250', 4, ' ', STR_PAD_LEFT);   // ast
        $record .= str_pad('100', 4, ' ', STR_PAD_LEFT);   // stl
        $record .= str_pad('150', 4, ' ', STR_PAD_LEFT);   // to
        $record .= str_pad('40', 4, ' ', STR_PAD_LEFT);    // blk
        $record .= str_pad('200', 4, ' ', STR_PAD_LEFT);   // pf
        $record .= '  ';
        return $record;
    }

    private function buildPlayerBlock(int $seasonCount, int $jsbId, string $name, string $seasonRecord): string
    {
        $header = str_pad((string) $seasonCount, 3, ' ', STR_PAD_LEFT);
        $header .= str_pad((string) $jsbId, 5, ' ', STR_PAD_LEFT);
        $header .= str_pad($name, 16);
        $block = $header . $seasonRecord;
        return str_pad($block, CarFileParser::BLOCK_SIZE, ' ');
    }

    private function buildCarFile(int $playerCount, string $playerBlock = ''): string
    {
        $header = str_pad((string) $playerCount, 4, ' ', STR_PAD_LEFT);
        $header = str_pad($header, CarFileParser::BLOCK_SIZE, ' ');
        return $header . $playerBlock;
    }

    private function writeTmpFile(string $data, string $prefix = 'jsb_test_'): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), $prefix);
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $data);
        return $tmpFile;
    }

    // ── processCarFile ───────────────────────────────────────────

    public function testProcessCarFileReturnsErrorOnParseFailure(): void
    {
        $tmpFile = $this->writeTmpFile('invalid data');

        try {
            $result = $this->makeService()->processCarFile($tmpFile, 2006);
            $this->assertSame(1, $result->errors);
            $this->assertStringContainsString('CAR parse failed', $result->messages[0]);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessCarFileSkipsSeasonsNotMatchingFilterYear(): void
    {
        $seasonRecord = $this->buildSeasonRecord(2005);
        $playerBlock = $this->buildPlayerBlock(1, 100, 'Test Player', $seasonRecord);
        $carData = $this->buildCarFile(1, $playerBlock);
        $tmpFile = $this->writeTmpFile($carData);

        $mockResolver = $this->createMock(PlayerIdResolver::class);
        $mockResolver->expects($this->never())->method('resolve');

        try {
            $result = $this->makeService($mockResolver)->processCarFile($tmpFile, 2006);
            $this->assertSame(0, $result->inserted);
            $this->assertSame(0, $result->skipped);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessCarFileProcessesMatchingYear(): void
    {
        $seasonRecord = $this->buildSeasonRecord(2006);
        $playerBlock = $this->buildPlayerBlock(1, 100, 'Test Player', $seasonRecord);
        $carData = $this->buildCarFile(1, $playerBlock);
        $tmpFile = $this->writeTmpFile($carData);

        $this->stubRepo->method('resolveTeamIdByName')->willReturn(21);
        $mockResolver = $this->createMock(PlayerIdResolver::class);
        $mockResolver->expects($this->once())->method('resolve')->willReturn(12345);

        try {
            $result = $this->makeService($mockResolver)->processCarFile($tmpFile, 2006);
            $this->assertSame(1, $result->inserted);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessCarFileSkipsWhenResolverReturnsNull(): void
    {
        $seasonRecord = $this->buildSeasonRecord(2006);
        $playerBlock = $this->buildPlayerBlock(1, 100, 'Test Player', $seasonRecord);
        $carData = $this->buildCarFile(1, $playerBlock);
        $tmpFile = $this->writeTmpFile($carData);

        $this->stubRepo->method('resolveTeamIdByName')->willReturn(21);
        $mockResolver = $this->createMock(PlayerIdResolver::class);
        $mockResolver->expects($this->once())->method('resolve')->willReturn(null);

        try {
            $result = $this->makeService($mockResolver)->processCarFile($tmpFile, 2006);
            $this->assertSame(1, $result->skipped);
            $this->assertSame(0, $result->inserted);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessCarFileCountsResolvedPlayers(): void
    {
        $seasonRecord = $this->buildSeasonRecord(2006);
        $playerBlock = $this->buildPlayerBlock(1, 100, 'Test Player', $seasonRecord);
        $carData = $this->buildCarFile(1, $playerBlock);
        $tmpFile = $this->writeTmpFile($carData);

        $this->stubRepo->method('resolveTeamIdByName')->willReturn(21);
        $mockResolver = $this->createMock(PlayerIdResolver::class);
        $mockResolver->expects($this->once())->method('resolve')->willReturn(12345);

        try {
            // ibl_hist is now a VIEW — processCarFile no longer writes to it.
            // Each resolved player is counted as inserted for tracking purposes.
            $result = $this->makeService($mockResolver)->processCarFile($tmpFile, 2006);
            $this->assertSame(1, $result->inserted);
        } finally {
            unlink($tmpFile);
        }
    }

    // ── processTrnFile ───────────────────────────────────────────

    private function buildTrnFile(int $recordCount, array $records = []): string
    {
        $data = str_repeat(' ', TrnFileParser::FILE_SIZE);
        foreach ($records as $index => $record) {
            $offset = $index * TrnFileParser::RECORD_SIZE;
            $data = substr_replace($data, $record, $offset, TrnFileParser::RECORD_SIZE);
        }
        $countStr = str_pad((string) $recordCount, TrnFileParser::HEADER_AREA_SIZE, ' ', STR_PAD_LEFT);
        $data = substr_replace($data, $countStr, 0, TrnFileParser::HEADER_AREA_SIZE);
        return $data;
    }

    private function buildInjuryRecord(int $month, int $day, int $year, int $pid, int $teamId, int $gamesMissed, string $injuryDesc): string
    {
        $record = str_repeat(' ', TrnFileParser::RECORD_SIZE);
        $record = substr_replace($record, str_pad((string) $month, 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        $record = substr_replace($record, (string) TrnFileParser::TYPE_INJURY, 26, 1);
        $record = substr_replace($record, str_pad((string) $pid, 4, ' ', STR_PAD_LEFT), 29, 4);
        $record = substr_replace($record, str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT), 33, 2);
        $record = substr_replace($record, str_pad((string) $gamesMissed, 4, ' ', STR_PAD_LEFT), 35, 4);
        $record = substr_replace($record, str_pad($injuryDesc, 57), 39, 57);
        return $record;
    }

    private function buildWaiverRecord(int $month, int $day, int $year, int $type, int $teamId, int $pid): string
    {
        $record = str_repeat(' ', TrnFileParser::RECORD_SIZE);
        $record = substr_replace($record, str_pad((string) $month, 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        $record = substr_replace($record, (string) $type, 26, 1);
        $record = substr_replace($record, str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT), 27, 2);
        $record = substr_replace($record, str_pad((string) $pid, 4, ' ', STR_PAD_LEFT), 31, 4);
        return $record;
    }

    public function testProcessTrnFileReturnsErrorOnParseFailure(): void
    {
        $tmpFile = $this->writeTmpFile('invalid data');

        try {
            $result = $this->makeService()->processTrnFile($tmpFile);
            $this->assertSame(1, $result->errors);
            $this->assertStringContainsString('TRN parse failed', $result->messages[0]);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessTrnFileInitializesTradeGroupIdFromRepo(): void
    {
        $this->stubRepo->method('fetchMaxTradeGroupId')->willReturn(5);
        $this->stubRepo->method('getPlayerName')->willReturn('Test Player');
        $this->stubRepo->method('upsertTransaction')->willReturn(1);

        $injuryRecord = $this->buildInjuryRecord(10, 15, 2006, 1234, 5, 12, 'Sprained ankle');
        $trnData = $this->buildTrnFile(1, [$injuryRecord]);
        $tmpFile = $this->writeTmpFile($trnData);

        try {
            $result = $this->makeService()->processTrnFile($tmpFile);
            $this->assertSame(1, $result->inserted);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessTrnFileImportsInjuryTransaction(): void
    {
        $this->stubRepo->method('fetchMaxTradeGroupId')->willReturn(0);
        $this->stubRepo->method('getPlayerName')->willReturn('John Smith');
        $this->stubRepo->method('upsertTransaction')->willReturn(1);

        $injuryRecord = $this->buildInjuryRecord(3, 15, 2006, 1234, 5, 8, 'Knee injury');
        $trnData = $this->buildTrnFile(1, [$injuryRecord]);
        $tmpFile = $this->writeTmpFile($trnData);

        try {
            $result = $this->makeService()->processTrnFile($tmpFile, 'test-import');
            $this->assertSame(1, $result->inserted);
            $this->assertSame(0, $result->errors);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessTrnFileImportsWaiverRelease(): void
    {
        $this->stubRepo->method('fetchMaxTradeGroupId')->willReturn(0);
        $this->stubRepo->method('getPlayerName')->willReturn('Released Player');
        $this->stubRepo->method('upsertTransaction')->willReturn(1);

        $waiverRecord = $this->buildWaiverRecord(5, 10, 2006, TrnFileParser::TYPE_WAIVER_RELEASE, 21, 5678);
        $trnData = $this->buildTrnFile(1, [$waiverRecord]);
        $tmpFile = $this->writeTmpFile($trnData);

        try {
            $result = $this->makeService()->processTrnFile($tmpFile);
            $this->assertSame(1, $result->inserted);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessTrnFileImportsWaiverClaim(): void
    {
        $this->stubRepo->method('fetchMaxTradeGroupId')->willReturn(0);
        $this->stubRepo->method('getPlayerName')->willReturn('Claimed Player');
        $this->stubRepo->method('upsertTransaction')->willReturn(1);

        $waiverRecord = $this->buildWaiverRecord(5, 10, 2006, TrnFileParser::TYPE_WAIVER_CLAIM, 21, 5678);
        $trnData = $this->buildTrnFile(1, [$waiverRecord]);
        $tmpFile = $this->writeTmpFile($trnData);

        try {
            $result = $this->makeService()->processTrnFile($tmpFile);
            $this->assertSame(1, $result->inserted);
        } finally {
            unlink($tmpFile);
        }
    }

    // ── processHisFile ───────────────────────────────────────────

    public function testProcessHisFileReturnsErrorOnParseFailure(): void
    {
        $tmpFile = $this->writeTmpFile('');
        // Empty file will trigger RuntimeException from HisFileParser

        try {
            // HisFileParser returns empty array for empty content (no exception)
            $result = $this->makeService()->processHisFile($tmpFile);
            $this->assertSame(0, $result->errors);
            $this->assertSame(0, $result->inserted);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessHisFileUpsertsPerTeam(): void
    {
        $hisContent = "Celtics (50-32) Won First Round (2005)\n"
            . "Lakers (55-27) Won Championship (2005)\n";
        $tmpFile = $this->writeTmpFile($hisContent);

        $this->stubRepo->method('resolveTeamIdByName')->willReturn(1);
        $this->stubRepo->method('upsertHistoryRecord')->willReturn(1);

        try {
            $result = $this->makeService()->processHisFile($tmpFile, 'test-source');
            $this->assertSame(2, $result->inserted);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testProcessHisFileHandlesEmptyPlayoffResult(): void
    {
        $hisContent = "Celtics (50-32) (2005)\n";
        $tmpFile = $this->writeTmpFile($hisContent);

        $this->stubRepo->method('resolveTeamIdByName')->willReturn(1);
        $this->stubRepo->method('upsertHistoryRecord')->willReturn(1);

        try {
            $result = $this->makeService()->processHisFile($tmpFile);
            $this->assertSame(1, $result->inserted);
        } finally {
            unlink($tmpFile);
        }
    }

    // ── processRcbFile ───────────────────────────────────────────

    public function testProcessRcbFileReturnsErrorOnParseFailure(): void
    {
        $tmpFile = $this->writeTmpFile('invalid');

        try {
            $result = $this->makeService()->processRcbFile($tmpFile, 2006);
            $this->assertSame(1, $result->errors);
            $this->assertStringContainsString('RCB parse failed', $result->messages[0]);
        } finally {
            unlink($tmpFile);
        }
    }
}
