<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\AwaFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\AwaFileParser
 */
class AwaFileParserTest extends TestCase
{
    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');
        AwaFileParser::parseFile('/nonexistent/IBL5.awa');
    }

    public function testParseFileThrowsForTooSmallFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'awa_test');
        $this->assertIsString($tempFile);
        file_put_contents($tempFile, str_repeat(' ', 100));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('need at least 2000');
            AwaFileParser::parseFile($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testParseBlockReturnsNullForEmptyBlock(): void
    {
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE);
        $result = AwaFileParser::parseBlock($data, 1, 2000);
        $this->assertNull($result);
    }

    public function testParseBlockReturnsNullForShortData(): void
    {
        $result = AwaFileParser::parseBlock('short', 1, 2000);
        $this->assertNull($result);
    }

    public function testParseBlockComputesYearCorrectly(): void
    {
        $data = $this->buildBlockWithScoringLeader(42, 1);
        $result = AwaFileParser::parseBlock($data, 3, 2000);
        $this->assertNotNull($result);
        $this->assertSame(2003, $result['year']);
    }

    public function testParseBlockExtractsRank1ScoringLeader(): void
    {
        $data = $this->buildBlockWithScoringLeader(42, 1);
        $result = AwaFileParser::parseBlock($data, 1, 2000);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('Scoring Leader', $result['stat_leaders']);

        $scoringLeaders = $result['stat_leaders']['Scoring Leader'];
        $rank1 = $this->findRank($scoringLeaders, 1);
        $this->assertNotNull($rank1);
        $this->assertSame(42, $rank1['pid']);
    }

    public function testParseBlockExtractsRank2ScoringLeader(): void
    {
        $data = $this->buildBlockWithRank2ScoringLeader(99);
        $result = AwaFileParser::parseBlock($data, 1, 2000);

        $this->assertNotNull($result);
        $scoringLeaders = $result['stat_leaders']['Scoring Leader'];
        $rank2 = $this->findRank($scoringLeaders, 2);
        $this->assertNotNull($rank2);
        $this->assertSame(99, $rank2['pid']);
    }

    public function testParseBlockExtractsRank3ScoringLeader(): void
    {
        $data = $this->buildBlockWithScoringLeader(77, 3);
        $result = AwaFileParser::parseBlock($data, 1, 2000);

        $this->assertNotNull($result);
        $scoringLeaders = $result['stat_leaders']['Scoring Leader'];
        $rank3 = $this->findRank($scoringLeaders, 3);
        $this->assertNotNull($rank3);
        $this->assertSame(77, $rank3['pid']);
    }

    public function testParseBlockExtractsReboundingLeader(): void
    {
        $data = $this->buildBlockWithStatChain(1, [
            'scoring_pid' => 10,
            'scoring_stat' => 3500,
            'reb_pid' => 20,
        ]);
        $result = AwaFileParser::parseBlock($data, 1, 2000);

        $this->assertNotNull($result);
        $rebLeaders = $result['stat_leaders']['Rebounding Leader'];
        $rank1 = $this->findRank($rebLeaders, 1);
        $this->assertNotNull($rank1);
        $this->assertSame(20, $rank1['pid']);
    }

    public function testParseBlockExtractsAllStatCategories(): void
    {
        $data = $this->buildBlockWithFullChain(1, 10, 20, 30, 40, 50);
        $result = AwaFileParser::parseBlock($data, 1, 2000);

        $this->assertNotNull($result);
        $this->assertSame(10, $this->findRank($result['stat_leaders']['Scoring Leader'], 1)['pid']);
        $this->assertSame(20, $this->findRank($result['stat_leaders']['Rebounding Leader'], 1)['pid']);
        $this->assertSame(30, $this->findRank($result['stat_leaders']['Assists Leader'], 1)['pid']);
        $this->assertSame(40, $this->findRank($result['stat_leaders']['Steals Leader'], 1)['pid']);
        $this->assertSame(50, $this->findRank($result['stat_leaders']['Blocks Leader'], 1)['pid']);
    }

    public function testParseBlockSortsLeadersByRank(): void
    {
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE);

        // Rank 1: section 0, offset 0+32 = 32 (also activates block)
        $data = $this->writeAsciiInt($data, 32, 10);

        // Rank 3: section 1, offset 268+32 = 300
        $data = $this->writeAsciiInt($data, 300, 30);

        // Rank 4: section 2, offset 402+32 = 434
        $data = $this->writeAsciiInt($data, 434, 40);

        // Rank 5: section 3, offset 536+32 = 568
        $data = $this->writeAsciiInt($data, 568, 50);

        // Rank 2: offset 166
        $data = $this->writeAsciiInt($data, 166, 20);

        $result = AwaFileParser::parseBlock($data, 1, 2000);
        $this->assertNotNull($result);

        $scoringLeaders = $result['stat_leaders']['Scoring Leader'];
        $ranks = array_map(static fn (array $l): int => $l['rank'], $scoringLeaders);
        $this->assertSame([1, 2, 3, 4, 5], $ranks);
    }

    public function testParseFileWithMultipleSeasons(): void
    {
        // 4 blocks = 4000 bytes (header + 3 season blocks)
        $data = str_repeat(' ', 4000);

        // Block 0: starting year = 2000
        $data = substr_replace($data, '2000', 0, 4);

        // Block 1: season 2001 with scoring leader PID=42
        $block1 = $this->buildBlockWithScoringLeader(42, 1);
        $data = substr_replace($data, $block1, AwaFileParser::BLOCK_SIZE, AwaFileParser::BLOCK_SIZE);

        // Block 2: empty (spaces — should be skipped)
        // Block 3: season 2003 with scoring leader PID=99
        $block3 = $this->buildBlockWithScoringLeader(99, 1);
        $data = substr_replace($data, $block3, 3 * AwaFileParser::BLOCK_SIZE, AwaFileParser::BLOCK_SIZE);

        $tempFile = tempnam(sys_get_temp_dir(), 'awa_test');
        $this->assertIsString($tempFile);
        file_put_contents($tempFile, $data);

        try {
            $result = AwaFileParser::parseFile($tempFile);
            $this->assertSame(2000, $result['starting_year']);
            $this->assertCount(2, $result['seasons']);
            $this->assertSame(2001, $result['seasons'][0]['year']);
            $this->assertSame(2003, $result['seasons'][1]['year']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testParseFileSmallerThan50000Bytes(): void
    {
        // 3 blocks = 3000 bytes (valid — header + 2 season blocks)
        $data = str_repeat(' ', 3000);
        $data = substr_replace($data, '1988', 0, 4);

        // Block 1: season 1989 with scoring leader PID=306
        $block1 = $this->buildBlockWithScoringLeader(306, 1);
        $data = substr_replace($data, $block1, AwaFileParser::BLOCK_SIZE, AwaFileParser::BLOCK_SIZE);

        $tempFile = tempnam(sys_get_temp_dir(), 'awa_test');
        $this->assertIsString($tempFile);
        file_put_contents($tempFile, $data);

        try {
            $result = AwaFileParser::parseFile($tempFile);
            $this->assertSame(1988, $result['starting_year']);
            $this->assertCount(1, $result['seasons']);
            $this->assertSame(1989, $result['seasons'][0]['year']);

            $scoringLeaders = $result['seasons'][0]['stat_leaders']['Scoring Leader'];
            $rank1 = $this->findRank($scoringLeaders, 1);
            $this->assertNotNull($rank1);
            $this->assertSame(306, $rank1['pid']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testParseBlockSkipsSpaceFilledBlocks(): void
    {
        // All spaces — should be treated as inactive (PID field is blank)
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE);
        $result = AwaFileParser::parseBlock($data, 1, 2000);
        $this->assertNull($result);
    }

    /**
     * Build a block with a single scoring leader at the given rank.
     */
    private function buildBlockWithScoringLeader(int $pid, int $rank): string
    {
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE);

        // Make block active (PID at offset 32 must be non-blank)
        $data = $this->writeAsciiInt($data, 32, $pid);

        $sectionMap = [1 => 0, 3 => 268, 4 => 402, 5 => 536];
        if ($rank === 2) {
            return $this->buildBlockWithRank2ScoringLeader($pid);
        }

        $sectionOffset = $sectionMap[$rank] ?? 0;
        $data = $this->writeAsciiInt($data, $sectionOffset + 32, $pid);

        return $data;
    }

    /**
     * Build a block with a rank 2 scoring leader.
     */
    private function buildBlockWithRank2ScoringLeader(int $pid): string
    {
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE);

        // Make block active
        $data = $this->writeAsciiInt($data, 32, 1);

        // Rank 2 scoring PID at offset 166
        $data = $this->writeAsciiInt($data, 166, $pid);

        return $data;
    }

    /**
     * Build a block with scoring and rebounding in the stat chain.
     *
     * @param array{scoring_pid: int, scoring_stat: int, reb_pid: int} $chainData
     */
    private function buildBlockWithStatChain(int $rank, array $chainData): string
    {
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE);

        // Make block active
        $data = $this->writeAsciiInt($data, 32, $chainData['scoring_pid']);

        $sectionMap = [1 => 0, 3 => 268, 4 => 402, 5 => 536];
        $sectionOffset = $sectionMap[$rank] ?? 0;

        // Scoring PID
        $data = $this->writeAsciiInt($data, $sectionOffset + 32, $chainData['scoring_pid']);

        // Stat chain: entry 0 at section_start + 40
        // stat(4) + PID(4) + blank(2) + team(2)
        $chainBase = $sectionOffset + 40;
        $data = $this->writeAsciiInt($data, $chainBase, $chainData['scoring_stat']);
        $data = $this->writeAsciiInt($data, $chainBase + 4, $chainData['reb_pid']);

        return $data;
    }

    /**
     * Build a block with a full stat chain for all 5 categories.
     */
    private function buildBlockWithFullChain(int $rank, int $scoringPid, int $rebPid, int $astPid, int $stlPid, int $blkPid): string
    {
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE);

        // Make block active
        $data = $this->writeAsciiInt($data, 32, $scoringPid);

        $sectionMap = [1 => 0, 3 => 268, 4 => 402, 5 => 536];
        $sectionOffset = $sectionMap[$rank] ?? 0;

        // Scoring PID
        $data = $this->writeAsciiInt($data, $sectionOffset + 32, $scoringPid);

        $chainBase = $sectionOffset + 40;
        // Entry 0: stat(4) + reb_PID(4) + blank(2) + team(2)
        $data = $this->writeAsciiInt($data, $chainBase, 3500);
        $data = $this->writeAsciiInt($data, $chainBase + 4, $rebPid);

        // Entry 1: reb_stat(4) + ast_PID(4) + blank(2) + team(2)
        $data = $this->writeAsciiInt($data, $chainBase + 12, 1200);
        $data = $this->writeAsciiInt($data, $chainBase + 16, $astPid);

        // Entry 2: ast_stat(4) + stl_PID(4) + blank(2) + team(2)
        $data = $this->writeAsciiInt($data, $chainBase + 24, 800);
        $data = $this->writeAsciiInt($data, $chainBase + 28, $stlPid);

        // Entry 3: stl_stat(4) + blk_PID(4) + blank(2) + team(2)
        $data = $this->writeAsciiInt($data, $chainBase + 36, 200);
        $data = $this->writeAsciiInt($data, $chainBase + 40, $blkPid);

        return $data;
    }

    /**
     * Write a right-justified, space-padded 4-char ASCII integer into data.
     */
    private function writeAsciiInt(string $data, int $offset, int $value): string
    {
        $ascii = str_pad((string) $value, 4, ' ', STR_PAD_LEFT);
        return substr_replace($data, $ascii, $offset, 4);
    }

    /**
     * Find a leader entry by rank.
     *
     * @param list<array{rank: int, pid: int}> $leaders
     * @return array{rank: int, pid: int}|null
     */
    private function findRank(array $leaders, int $rank): ?array
    {
        foreach ($leaders as $leader) {
            if ($leader['rank'] === $rank) {
                return $leader;
            }
        }
        return null;
    }

    public function testParseThrowsForTooSmallData(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('need at least');
        AwaFileParser::parse(str_repeat(' ', 100));
    }

    public function testParseAcceptsMinimalData(): void
    {
        // Two blocks (header + one block): 2000 bytes
        $data = str_repeat(' ', AwaFileParser::BLOCK_SIZE * 2);
        $result = AwaFileParser::parse($data);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('seasons', $result);
        $this->assertSame([], $result['seasons']);
    }
}