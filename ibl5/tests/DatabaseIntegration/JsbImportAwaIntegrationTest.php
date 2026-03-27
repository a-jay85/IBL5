<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use JsbParser\AwaFileParser;
use JsbParser\CarFileParser;
use JsbParser\JsbImportRepository;
use JsbParser\JsbImportService;
use JsbParser\PlayerIdResolver;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for JsbImportService::processAwaFile() against real MariaDB.
 *
 * @covers \JsbParser\JsbImportService::processAwaFile
 */
#[Group('database')]
class JsbImportAwaIntegrationTest extends DatabaseTestCase
{
    private JsbImportService $service;
    private string $tempDir;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $repository = new JsbImportRepository($this->db);
        $resolver = new PlayerIdResolver($this->db);
        $this->service = new JsbImportService($repository, $resolver);

        $this->tempDir = sys_get_temp_dir() . '/awa_int_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function testProcessAwaFileInsertsStatLeaderAward(): void
    {
        $awaPath = $this->buildAwaFile(8887, [
            1 => $this->buildBlockWithScoringLeader(1),
        ]);
        $carPath = $this->buildCarFile([1 => 'Awa Scorer']);

        $result = $this->service->processAwaFile($awaPath, $carPath, 8888);

        $this->assertGreaterThanOrEqual(1, $result->inserted);
        $this->assertSame(0, $result->errors);

        $rows = $this->queryAwards(8888, "Scoring Leader (1st)");
        $this->assertCount(1, $rows);
        $this->assertSame('Awa Scorer', $rows[0]['name']);
    }

    public function testProcessAwaFileInsertsAllFiveCategories(): void
    {
        $awaPath = $this->buildAwaFile(8887, [
            1 => $this->buildBlockWithFullChain(10, 20, 30, 40, 50),
        ]);
        $carPath = $this->buildCarFile([
            10 => 'Scorer One',
            20 => 'Rebounder One',
            30 => 'Assister One',
            40 => 'Stealer One',
            50 => 'Blocker One',
        ]);

        $result = $this->service->processAwaFile($awaPath, $carPath, 8888);

        $this->assertSame(5, $result->inserted);
        $this->assertSame(0, $result->errors);

        $categories = [
            'Scoring Leader (1st)' => 'Scorer One',
            'Rebounding Leader (1st)' => 'Rebounder One',
            'Assists Leader (1st)' => 'Assister One',
            'Steals Leader (1st)' => 'Stealer One',
            'Blocks Leader (1st)' => 'Blocker One',
        ];

        foreach ($categories as $award => $expectedName) {
            $rows = $this->queryAwards(8888, $award);
            $this->assertCount(1, $rows, "Expected 1 row for {$award}");
            $this->assertSame($expectedName, $rows[0]['name']);
        }
    }

    public function testProcessAwaFileIsIdempotent(): void
    {
        $awaPath = $this->buildAwaFile(8887, [
            1 => $this->buildBlockWithScoringLeader(1),
        ]);
        $carPath = $this->buildCarFile([1 => 'Idem Player']);

        $first = $this->service->processAwaFile($awaPath, $carPath, 8888);
        $this->assertGreaterThanOrEqual(1, $first->inserted);

        $second = $this->service->processAwaFile($awaPath, $carPath, 8888);
        $this->assertSame(0, $second->inserted);
        $this->assertGreaterThanOrEqual(1, $second->updated);

        $rows = $this->queryAwards(8888, "Scoring Leader (1st)");
        $this->assertCount(1, $rows, 'Should have exactly 1 row, not duplicates');
    }

    public function testProcessAwaFileSkipsUnknownPids(): void
    {
        $awaPath = $this->buildAwaFile(8887, [
            1 => $this->buildBlockWithScoringLeader(999),
        ]);
        // .car only has player at block 1, not 999
        $carPath = $this->buildCarFile([1 => 'Known Player']);

        $result = $this->service->processAwaFile($awaPath, $carPath, 8888);

        $this->assertGreaterThanOrEqual(1, $result->skipped);
        $this->assertSame(0, $result->inserted);

        $rows = $this->queryAwards(8888);
        $this->assertCount(0, $rows);
    }

    public function testProcessAwaFileRespectsFilterYear(): void
    {
        $awaPath = $this->buildAwaFile(8886, [
            1 => $this->buildBlockWithScoringLeader(1), // year 8887
            2 => $this->buildBlockWithScoringLeader(1), // year 8888
        ]);
        $carPath = $this->buildCarFile([1 => 'Filter Player']);

        $result = $this->service->processAwaFile($awaPath, $carPath, 8888);

        $this->assertGreaterThanOrEqual(1, $result->inserted);

        $rows8887 = $this->queryAwards(8887);
        $this->assertCount(0, $rows8887, 'Should not have imported year 8887');

        $rows8888 = $this->queryAwards(8888);
        $this->assertGreaterThanOrEqual(1, count($rows8888));
    }

    // --- Fixture helpers ---

    /**
     * Build a minimal .awa file with the given starting year and block data.
     *
     * @param int $startingYear Year written to block 0 header
     * @param array<int, string> $blocks Map of blockIndex → block data (1000 bytes each)
     */
    private function buildAwaFile(int $startingYear, array $blocks): string
    {
        $maxBlock = max(array_keys($blocks));
        $totalBlocks = $maxBlock + 1;
        $fileSize = max($totalBlocks + 1, 2) * AwaFileParser::BLOCK_SIZE;

        $data = str_repeat(' ', $fileSize);
        $data = substr_replace($data, str_pad((string) $startingYear, 4, ' ', STR_PAD_LEFT), 0, 4);

        foreach ($blocks as $blockIndex => $blockData) {
            $offset = $blockIndex * AwaFileParser::BLOCK_SIZE;
            $data = substr_replace($data, $blockData, $offset, AwaFileParser::BLOCK_SIZE);
        }

        return $this->writeTempFile($data, 'awa_');
    }

    /**
     * Build a minimal .car file with players at specific block indices.
     *
     * @param array<int, string> $players Map of blockIndex → player name
     */
    private function buildCarFile(array $players): string
    {
        $maxBlock = max(array_keys($players));
        $totalBlocks = $maxBlock + 1;
        $fileSize = ($totalBlocks + 1) * CarFileParser::BLOCK_SIZE;

        $data = str_repeat(' ', $fileSize);

        // Block 0: header — player count
        $data = substr_replace($data, str_pad((string) count($players), CarFileParser::PLAYER_COUNT_WIDTH, ' ', STR_PAD_LEFT), 0, CarFileParser::PLAYER_COUNT_WIDTH);

        foreach ($players as $blockIndex => $name) {
            $offset = $blockIndex * CarFileParser::BLOCK_SIZE;
            // Player header: seasonCount(3) + jsbId(5) + name(16)
            $header = str_pad('1', 3, ' ', STR_PAD_LEFT)        // 1 season
                    . str_pad((string) $blockIndex, 5, ' ', STR_PAD_LEFT) // JSB ID
                    . str_pad($name, 16);                        // Name (left-justified)
            $data = substr_replace($data, $header, $offset, strlen($header));

            // Write a minimal season record (100 bytes) so the parser finds valid data
            $seasonOffset = $offset + CarFileParser::HEADER_SIZE;
            $seasonData = str_pad('2000', 4, ' ', STR_PAD_LEFT) . str_repeat(' ', 96);
            $data = substr_replace($data, $seasonData, $seasonOffset, CarFileParser::SEASON_RECORD_SIZE);
        }

        return $this->writeTempFile($data, 'car_');
    }

    /**
     * Build a block with a rank-1 scoring leader.
     */
    private function buildBlockWithScoringLeader(int $pid): string
    {
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE);
        $data = $this->writeAsciiInt($data, 32, $pid); // Active check + scoring PID
        return $data;
    }

    /**
     * Build a block with all 5 stat categories at rank 1.
     */
    private function buildBlockWithFullChain(int $scorePid, int $rebPid, int $astPid, int $stlPid, int $blkPid): string
    {
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE);

        // Scoring PID at section 0, offset 32
        $data = $this->writeAsciiInt($data, 32, $scorePid);

        // Stat chain at section 0, offset 40
        // Entry 0: stat(4) + reb_PID(4)
        $data = $this->writeAsciiInt($data, 40, 3500); // scoring stat
        $data = $this->writeAsciiInt($data, 44, $rebPid);

        // Entry 1: stat(4) + ast_PID(4) at offset 52 (40 + 12)
        $data = $this->writeAsciiInt($data, 52, 1200);
        $data = $this->writeAsciiInt($data, 56, $astPid);

        // Entry 2: stat(4) + stl_PID(4) at offset 64 (40 + 24)
        $data = $this->writeAsciiInt($data, 64, 800);
        $data = $this->writeAsciiInt($data, 68, $stlPid);

        // Entry 3: stat(4) + blk_PID(4) at offset 76 (40 + 36)
        $data = $this->writeAsciiInt($data, 76, 200);
        $data = $this->writeAsciiInt($data, 80, $blkPid);

        return $data;
    }

    private function writeAsciiInt(string $data, int $offset, int $value): string
    {
        $ascii = str_pad((string) $value, 4, ' ', STR_PAD_LEFT);
        return substr_replace($data, $ascii, $offset, 4);
    }

    private function writeTempFile(string $data, string $prefix): string
    {
        $path = $this->tempDir . '/' . $prefix . uniqid();
        file_put_contents($path, $data);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * Query ibl_awards for a given year and optional award name.
     *
     * @return list<array{year: int, Award: string, name: string}>
     */
    private function queryAwards(int $year, ?string $award = null): array
    {
        if ($award !== null) {
            $stmt = $this->db->prepare("SELECT year, Award, name FROM ibl_awards WHERE year = ? AND Award = ?");
            self::assertNotFalse($stmt);
            $stmt->bind_param('is', $year, $award);
        } else {
            $stmt = $this->db->prepare("SELECT year, Award, name FROM ibl_awards WHERE year = ?");
            self::assertNotFalse($stmt);
            $stmt->bind_param('i', $year);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            /** @var array{year: int, Award: string, name: string} $row */
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}
