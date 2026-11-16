<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlbFileParser;
use JsbParser\PlbFileWriter;
use PHPUnit\Framework\TestCase;
use Tests\PlrParser\JsbExportFixtures;

/**
 * @covers \JsbParser\PlbFileWriter
 */
class PlbFileWriterTest extends TestCase
{
    /**
     * @return array{dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}
     */
    private static function slot(int $minutes, int $of = 0, int $df = 0, int $oi = 0, int $di = 0, int $bh = 0): array
    {
        return ['dc_minutes' => $minutes, 'dc_of' => $of, 'dc_df' => $df, 'dc_oi' => $oi, 'dc_di' => $di, 'dc_bh' => $bh];
    }

    public function testFormatSlotRoundTripsThroughParser(): void
    {
        $slot = self::slot(36, 2, -1, 1, -2, 0);
        self::assertSame('36 2-1 1-2 0', PlbFileWriter::formatSlot($slot));

        $content = PlbFileWriter::applySlots(JsbExportFixtures::plb(), [4 => [7 => $slot]]);
        $parsed = PlbFileParser::parse($content);

        self::assertSame(['slot_index' => 7] + $slot, $parsed[4][7]);
    }

    public function testUntouchedBytesAndCrlfPreserved(): void
    {
        $source = JsbExportFixtures::plb(' 5 1 1 0 0 0');
        $output = PlbFileWriter::applySlots($source, [0 => [0 => self::slot(40)], 31 => [29 => self::slot(9)]]);

        self::assertSame(strlen($source), strlen($output));
        self::assertSame(substr_count($source, "\r\n"), substr_count($output, "\r\n"));
        self::assertSame('40 0 0 0 0 0', substr($output, 0, 12));
        self::assertSame(substr($source, 12, 348), substr($output, 12, 348));

        $sourceLines = explode("\r\n", $source);
        $outputLines = explode("\r\n", $output);
        self::assertSame(' 9 0 0 0 0 0', substr($outputLines[31], 348, 12));
        self::assertSame(array_slice($sourceLines, 1, 30), array_slice($outputLines, 1, 30));
        self::assertSame(array_slice($sourceLines, 32), array_slice($outputLines, 32));
    }

    public function testRejectsValueOutsideTwoCharRange(): void
    {
        self::assertSame('99-9 0 0 0 0', PlbFileWriter::formatSlot(self::slot(99, -9)));

        foreach ([self::slot(100), self::slot(0, -10)] as $bad) {
            try {
                PlbFileWriter::formatSlot($bad);
                self::fail('Expected InvalidArgumentException');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('does not fit in two chars', $e->getMessage());
            }
        }
    }

    public function testRejectsShortTeamLine(): void
    {
        $ok = str_repeat(' 0 0 0 0 0 0', 30);
        self::assertSame(360, strlen(PlbFileWriter::applySlots($ok, [0 => [0 => self::slot(0)]])));

        try {
            PlbFileWriter::applySlots(substr($ok, 0, 359), [0 => [0 => self::slot(1)]]);
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('shorter than 360', $e->getMessage());
        }
    }

    public function testOrdinalToTeamSlotBoundaries(): void
    {
        self::assertNull(PlbFileWriter::ordinalToTeamSlot(0));
        self::assertSame([0, 0], PlbFileWriter::ordinalToTeamSlot(1));
        self::assertSame([0, 29], PlbFileWriter::ordinalToTeamSlot(30));
        self::assertSame([1, 0], PlbFileWriter::ordinalToTeamSlot(31));
        self::assertSame([19, 8], PlbFileWriter::ordinalToTeamSlot(579));
        self::assertSame([31, 29], PlbFileWriter::ordinalToTeamSlot(960));
        self::assertNull(PlbFileWriter::ordinalToTeamSlot(961));
    }
}
