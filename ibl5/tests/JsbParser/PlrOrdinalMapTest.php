<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlrOrdinalMap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\PlrOrdinalMap
 */
class PlrOrdinalMapTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'plr_map_test_');
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
     * Build a minimal .plr line with ordinal, name, and pid.
     *
     * Format: ordinal(4) | name(32) | age(2) | pid(6) + padding
     */
    private function buildPlrLine(int $ordinal, string $name, int $pid): string
    {
        $line = str_pad((string) $ordinal, 4, ' ', STR_PAD_LEFT);
        $line .= str_pad($name, 32);
        $line .= str_pad('25', 2); // age placeholder
        $line .= str_pad((string) $pid, 6, ' ', STR_PAD_LEFT);
        // Pad to at least 44 chars (minimum needed)
        return str_pad($line, 100);
    }

    public function testFromPlrFileBuildsMap(): void
    {
        $content = $this->buildPlrLine(1, 'John Smith', 12345) . "\n"
            . $this->buildPlrLine(2, 'Jane Doe', 67890) . "\n"
            . $this->buildPlrLine(3, 'Bob Wilson', 11111) . "\n";

        file_put_contents($this->tmpFile, $content);
        $map = PlrOrdinalMap::fromPlrFile($this->tmpFile);

        $this->assertSame(3, $map->count());
    }

    public function testGetSlotPlayerReturnsCorrectPlayer(): void
    {
        // tid=1, slot=0 → ordinal = (1-1)*30 + 0 + 1 = 1
        $content = $this->buildPlrLine(1, 'John Smith', 12345) . "\n";

        file_put_contents($this->tmpFile, $content);
        $map = PlrOrdinalMap::fromPlrFile($this->tmpFile);

        $player = $map->getSlotPlayer(1, 0);
        $this->assertNotNull($player);
        $this->assertSame(12345, $player['pid']);
        $this->assertSame('John Smith', $player['name']);
    }

    public function testOrdinalCalculation(): void
    {
        // tid=3, slot=4 → ordinal = (3-1)*30 + 4 + 1 = 65
        $content = $this->buildPlrLine(65, 'Mid Roster', 99999) . "\n";

        file_put_contents($this->tmpFile, $content);
        $map = PlrOrdinalMap::fromPlrFile($this->tmpFile);

        $player = $map->getSlotPlayer(3, 4);
        $this->assertNotNull($player);
        $this->assertSame(99999, $player['pid']);
        $this->assertSame('Mid Roster', $player['name']);
    }

    public function testReturnsNullForMissingOrdinal(): void
    {
        $content = $this->buildPlrLine(1, 'John Smith', 12345) . "\n";

        file_put_contents($this->tmpFile, $content);
        $map = PlrOrdinalMap::fromPlrFile($this->tmpFile);

        // tid=2, slot=0 → ordinal=31, which is not in the file
        $this->assertNull($map->getSlotPlayer(2, 0));
    }

    public function testSkipsPidZeroLines(): void
    {
        $content = $this->buildPlrLine(1, 'Empty Slot', 0) . "\n"
            . $this->buildPlrLine(2, 'Real Player', 12345) . "\n";

        file_put_contents($this->tmpFile, $content);
        $map = PlrOrdinalMap::fromPlrFile($this->tmpFile);

        $this->assertSame(1, $map->count());
        $this->assertNull($map->getSlotPlayer(1, 0)); // pid=0 was skipped
    }

    public function testSkipsOrdinalAbove1440(): void
    {
        $content = $this->buildPlrLine(1441, 'Over Limit', 99999) . "\n"
            . $this->buildPlrLine(1, 'Valid Player', 12345) . "\n";

        file_put_contents($this->tmpFile, $content);
        $map = PlrOrdinalMap::fromPlrFile($this->tmpFile);

        $this->assertSame(1, $map->count());
    }

    public function testConvertsCP1252Names(): void
    {
        // "José" in CP1252: J(4A) o(6F) s(73) é(E9)
        $cp1252Name = "Jos\xE9 Garcia";
        $content = $this->buildPlrLine(1, $cp1252Name, 12345) . "\n";

        file_put_contents($this->tmpFile, $content);
        $map = PlrOrdinalMap::fromPlrFile($this->tmpFile);

        $player = $map->getSlotPlayer(1, 0);
        $this->assertNotNull($player);
        $this->assertSame('José Garcia', $player['name']);
    }

    public function testEmptyMapReturnsNull(): void
    {
        $map = PlrOrdinalMap::empty();

        $this->assertSame(0, $map->count());
        $this->assertNull($map->getSlotPlayer(1, 0));
    }

    public function testFromPlrFileThrowsForUnreadableFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PLR file not found');

        PlrOrdinalMap::fromPlrFile('/nonexistent/path/to/file.plr');
    }
}
