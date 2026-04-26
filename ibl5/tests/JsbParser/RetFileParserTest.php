<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\RetFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\RetFileParser
 */
class RetFileParserTest extends TestCase
{
    /**
     * Build a synthetic .ret file from structured data.
     *
     * @param list<array{name: string, pid: int}> $entries
     * @param int $totalSlots Total padded slots (default 40)
     */
    private function buildRetFile(array $entries, int $totalSlots = 40): string
    {
        $content = '';
        foreach ($entries as $entry) {
            $content .= "{$entry['name']} {$entry['pid']}\r\n";
        }
        // Pad remaining slots with " 0"
        $remaining = $totalSlots - count($entries);
        for ($i = 0; $i < $remaining; $i++) {
            $content .= " 0\r\n";
        }
        // Trailing whitespace
        $content .= str_repeat(' ', 60) . "\r\n";
        return $content;
    }

    public function testExtractsRealEntries(): void
    {
        $retData = $this->buildRetFile([
            ['name' => 'John Smith', 'pid' => 12345],
            ['name' => 'Jane Doe', 'pid' => 67890],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'ret_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $retData);

        try {
            $result = RetFileParser::parseFile($tmpFile);

            $this->assertCount(2, $result);
            $this->assertSame(12345, $result[0]['jsb_pid']);
            $this->assertSame('John Smith', $result[0]['player_name']);
            $this->assertSame(67890, $result[1]['jsb_pid']);
            $this->assertSame('Jane Doe', $result[1]['player_name']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testSkipsPaddingAndBlankLines(): void
    {
        $retData = $this->buildRetFile([
            ['name' => 'Only Player', 'pid' => 11111],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'ret_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $retData);

        try {
            $result = RetFileParser::parseFile($tmpFile);

            $this->assertCount(1, $result);
            $this->assertSame('Only Player', $result[0]['player_name']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParsesMultiWordNames(): void
    {
        $retData = $this->buildRetFile([
            ['name' => 'Jean Claude Van Damme', 'pid' => 99999],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'ret_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $retData);

        try {
            $result = RetFileParser::parseFile($tmpFile);

            $this->assertCount(1, $result);
            $this->assertSame('Jean Claude Van Damme', $result[0]['player_name']);
            $this->assertSame(99999, $result[0]['jsb_pid']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testPidIsLastToken(): void
    {
        // Player name has numbers but PID is the last whitespace token
        $content = "Player Name III 55555\r\n";
        $content .= " 0\r\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'ret_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $content);

        try {
            $result = RetFileParser::parseFile($tmpFile);

            $this->assertCount(1, $result);
            $this->assertSame('Player Name III', $result[0]['player_name']);
            $this->assertSame(55555, $result[0]['jsb_pid']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RET file not found');

        RetFileParser::parseFile('/nonexistent/file.ret');
    }

    public function testParseAcceptsInMemoryData(): void
    {
        $data = "John Doe 12345\nJane Smith 67890\n";
        $result = RetFileParser::parse($data);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame(12345, $result[0]['jsb_pid']);
        $this->assertSame('John Doe', $result[0]['player_name']);
    }
}