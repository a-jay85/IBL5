<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\HofFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\HofFileParser
 */
class HofFileParserTest extends TestCase
{
    private const FILE_SIZE = 7000;
    private const BLOCK_SIZE = 500;

    /**
     * Build a synthetic .hof file with entries placed into 500-byte blocks.
     *
     * @param list<list<array{pos: string, name: string, pid: int, year: int}>> $blocks Each inner list = entries for one block
     */
    private function buildHofFile(array $blocks): string
    {
        $content = '';
        for ($i = 0; $i < 14; $i++) {
            $blockEntries = $blocks[$i] ?? [];
            $blockContent = '';
            foreach ($blockEntries as $entry) {
                // Format: "{pos:2} {name}{pid} {year:4} " + CRLF
                $pos = str_pad($entry['pos'], 2, ' ', STR_PAD_LEFT);
                $line = sprintf('%s %s %d %d ', $pos, $entry['name'], $entry['pid'], $entry['year']);
                $blockContent .= $line . "\r\n";
            }
            // Pad to exactly 500 bytes
            $blockContent = str_pad($blockContent, self::BLOCK_SIZE, ' ');
            $content .= $blockContent;
        }
        return $content;
    }

    public function testParsesSingleEntry(): void
    {
        $blocks = [
            [['pos' => 'PF', 'name' => 'John Smith', 'pid' => 12345, 'year' => 1996]],
        ];
        $hofData = $this->buildHofFile($blocks);

        $tmpFile = tempnam(sys_get_temp_dir(), 'hof_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $hofData);

        try {
            $result = HofFileParser::parseFile($tmpFile);

            $this->assertCount(1, $result);
            $this->assertSame(12345, $result[0]['jsb_pid']);
            $this->assertSame('John Smith', $result[0]['player_name']);
            $this->assertSame('PF', $result[0]['pos']);
            $this->assertSame(1996, $result[0]['induction_year']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParsesMultipleEntries(): void
    {
        $blocks = [
            [
                ['pos' => 'PG', 'name' => 'Alice Brown', 'pid' => 111, 'year' => 1996],
                ['pos' => 'C', 'name' => 'Bob Jones', 'pid' => 222, 'year' => 1996],
            ],
            [
                ['pos' => 'SF', 'name' => 'Charlie Davis', 'pid' => 333, 'year' => 1997],
            ],
        ];
        $hofData = $this->buildHofFile($blocks);

        $tmpFile = tempnam(sys_get_temp_dir(), 'hof_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $hofData);

        try {
            $result = HofFileParser::parseFile($tmpFile);

            $this->assertCount(3, $result);
            $this->assertSame('Alice Brown', $result[0]['player_name']);
            $this->assertSame('Bob Jones', $result[1]['player_name']);
            $this->assertSame('Charlie Davis', $result[2]['player_name']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testIgnoresEmptyBlocks(): void
    {
        // Only put entries in block index 5
        $blocks = [];
        $blocks[5] = [['pos' => 'SG', 'name' => 'Solo Entry', 'pid' => 777, 'year' => 2001]];
        $hofData = $this->buildHofFile($blocks);

        $tmpFile = tempnam(sys_get_temp_dir(), 'hof_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $hofData);

        try {
            $result = HofFileParser::parseFile($tmpFile);

            $this->assertCount(1, $result);
            $this->assertSame('Solo Entry', $result[0]['player_name']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testTrimsPositionLeadingSpace(): void
    {
        $blocks = [
            [['pos' => 'C', 'name' => 'Center Guy', 'pid' => 444, 'year' => 2000]],
        ];
        $hofData = $this->buildHofFile($blocks);

        $tmpFile = tempnam(sys_get_temp_dir(), 'hof_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $hofData);

        try {
            $result = HofFileParser::parseFile($tmpFile);

            $this->assertSame('C', $result[0]['pos']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testValidatesFileSize(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'hof_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, 'too short');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('HOF data size mismatch');

            HofFileParser::parseFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testHandlesVariableNameLengths(): void
    {
        $blocks = [
            [
                ['pos' => 'PG', 'name' => 'A B', 'pid' => 1, 'year' => 1998],
                ['pos' => 'SF', 'name' => 'Very Long Name Here', 'pid' => 99999, 'year' => 1998],
            ],
        ];
        $hofData = $this->buildHofFile($blocks);

        $tmpFile = tempnam(sys_get_temp_dir(), 'hof_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $hofData);

        try {
            $result = HofFileParser::parseFile($tmpFile);

            $this->assertCount(2, $result);
            $this->assertSame('A B', $result[0]['player_name']);
            $this->assertSame(1, $result[0]['jsb_pid']);
            $this->assertSame('Very Long Name Here', $result[1]['player_name']);
            $this->assertSame(99999, $result[1]['jsb_pid']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HOF file not found');

        HofFileParser::parseFile('/nonexistent/file.hof');
    }

    public function testParseThrowsForWrongSize(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HOF data size mismatch');
        HofFileParser::parse(str_repeat(' ', 100));
    }

    public function testParseAcceptsValidSizedData(): void
    {
        $data = str_repeat(' ', 7000);
        $result = HofFileParser::parse($data);
        $this->assertIsArray($result);
    }
}