<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlbFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\PlbFileParser
 */
class PlbFileParserTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'plb_test_');
        if ($this->tmpFile === false) {
            $this->fail('Could not create temp file');
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    /**
     * Build a single PLB line (360 chars) with specified slot values.
     *
     * @param array<int, array{minutes: int, of: int, df: int, oi: int, di: int, bh: int}> $slots
     *        Key = slot index, value = field values
     */
    private function buildPlbLine(array $slots = []): string
    {
        $line = str_repeat('00', 180); // 30 slots × 6 fields × 2 chars = 360 zeros

        foreach ($slots as $slotIndex => $values) {
            $offset = $slotIndex * 12;
            $slotStr = sprintf(
                '%02d%02d%02d%02d%02d%02d',
                $values['minutes'],
                $values['of'],
                $values['df'],
                $values['oi'],
                $values['di'],
                $values['bh']
            );
            $line = substr_replace($line, $slotStr, $offset, 12);
        }

        return $line;
    }

    /**
     * Build a full PLB file with 32 lines.
     *
     * @param array<int, string> $lineOverrides Key = line index, value = line content
     */
    private function buildPlbFile(array $lineOverrides = []): string
    {
        $lines = [];
        $defaultLine = $this->buildPlbLine();

        for ($i = 0; $i < 32; $i++) {
            $lines[] = $lineOverrides[$i] ?? $defaultLine;
        }

        return implode("\n", $lines);
    }

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PLB file not found');

        PlbFileParser::parseFile('/nonexistent/path/to/file.plb');
    }

    public function testParseFileReturns32Lines(): void
    {
        file_put_contents($this->tmpFile, $this->buildPlbFile());

        $result = PlbFileParser::parseFile($this->tmpFile);

        $this->assertCount(32, $result);
    }

    public function testParsesFirstSlotFields(): void
    {
        $line = $this->buildPlbLine([
            0 => ['minutes' => 36, 'of' => 5, 'df' => 3, 'oi' => 2, 'di' => 1, 'bh' => 4],
        ]);

        file_put_contents($this->tmpFile, $this->buildPlbFile([0 => $line]));
        $result = PlbFileParser::parseFile($this->tmpFile);

        $slot = $result[0][0];
        $this->assertSame(0, $slot['slot_index']);
        $this->assertSame(36, $slot['dc_minutes']);
        $this->assertSame(5, $slot['dc_of']);
        $this->assertSame(3, $slot['dc_df']);
        $this->assertSame(2, $slot['dc_oi']);
        $this->assertSame(1, $slot['dc_di']);
        $this->assertSame(4, $slot['dc_bh']);
    }

    public function testParsesLastSlotOfLine(): void
    {
        $line = $this->buildPlbLine([
            29 => ['minutes' => 12, 'of' => 1, 'df' => 2, 'oi' => 3, 'di' => 4, 'bh' => 5],
        ]);

        file_put_contents($this->tmpFile, $this->buildPlbFile([0 => $line]));
        $result = PlbFileParser::parseFile($this->tmpFile);

        $slot = $result[0][29];
        $this->assertSame(29, $slot['slot_index']);
        $this->assertSame(12, $slot['dc_minutes']);
        $this->assertSame(1, $slot['dc_of']);
        $this->assertSame(2, $slot['dc_df']);
        $this->assertSame(3, $slot['dc_oi']);
        $this->assertSame(4, $slot['dc_di']);
        $this->assertSame(5, $slot['dc_bh']);
    }

    public function testSkipsLinesTooShort(): void
    {
        $lines = [];
        $lines[] = 'short'; // line 0: too short, should be skipped
        for ($i = 1; $i < 32; $i++) {
            $lines[] = $this->buildPlbLine();
        }

        file_put_contents($this->tmpFile, implode("\n", $lines));
        $result = PlbFileParser::parseFile($this->tmpFile);

        $this->assertArrayNotHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
    }

    public function testHandlesCRLFLineEndings(): void
    {
        $content = $this->buildPlbFile();
        // Replace LF with CRLF
        $content = str_replace("\n", "\r\n", $content);

        file_put_contents($this->tmpFile, $content);
        $result = PlbFileParser::parseFile($this->tmpFile);

        $this->assertCount(32, $result);
    }

    public function testReturns30SlotsPerLine(): void
    {
        file_put_contents($this->tmpFile, $this->buildPlbFile());
        $result = PlbFileParser::parseFile($this->tmpFile);

        foreach ($result as $slots) {
            $this->assertCount(30, $slots);
        }
    }

    public function testSlotIndexCorrect(): void
    {
        file_put_contents($this->tmpFile, $this->buildPlbFile());
        $result = PlbFileParser::parseFile($this->tmpFile);

        $slots = $result[0];
        for ($i = 0; $i < 30; $i++) {
            $this->assertSame($i, $slots[$i]['slot_index']);
        }
    }

    public function testZeroMinutesSlotPresent(): void
    {
        // Parser returns ALL slots including zero-minutes (filtering is service-layer)
        file_put_contents($this->tmpFile, $this->buildPlbFile());
        $result = PlbFileParser::parseFile($this->tmpFile);

        $slot = $result[0][0];
        $this->assertSame(0, $slot['dc_minutes']);
        $this->assertArrayHasKey('dc_minutes', $slot);
    }
}
