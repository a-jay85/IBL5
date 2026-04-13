<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\ScoFileParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\ScoFileParser
 */
class ScoFileParserTest extends TestCase
{
    /**
     * Build a synthetic 2000-byte .sco game record.
     *
     * The game-info section (58 bytes) is filled with $gameInfoByte,
     * each of the 30 player slots (53 bytes each) is filled with a
     * distinct byte value (slot 0 = chr(1), slot 1 = chr(2), etc.),
     * and trailing padding is filled with NUL bytes.
     */
    private function buildRecord(string $gameInfoByte = 'G'): string
    {
        $record = str_repeat($gameInfoByte, ScoFileParser::GAME_INFO_SIZE);

        for ($i = 0; $i < ScoFileParser::PLAYER_SLOT_COUNT; $i++) {
            $record .= str_repeat(chr($i + 1), ScoFileParser::PLAYER_SLOT_SIZE);
        }

        // Pad to full record size
        $record .= str_repeat("\0", ScoFileParser::RECORD_SIZE - strlen($record));

        return $record;
    }

    // --- Constants ---

    public function testRecordSizeIs2000Bytes(): void
    {
        $this->assertSame(2000, ScoFileParser::RECORD_SIZE);
    }

    public function testGameInfoSizeIs58Bytes(): void
    {
        $this->assertSame(58, ScoFileParser::GAME_INFO_SIZE);
    }

    public function testPlayerSlotSizeIs53Bytes(): void
    {
        $this->assertSame(53, ScoFileParser::PLAYER_SLOT_SIZE);
    }

    public function testPlayerSlotCountIs30(): void
    {
        $this->assertSame(30, ScoFileParser::PLAYER_SLOT_COUNT);
    }

    public function testVisitorSlotCountIs15(): void
    {
        $this->assertSame(15, ScoFileParser::VISITOR_SLOT_COUNT);
    }

    public function testHeaderOffsetIs1000000Bytes(): void
    {
        $this->assertSame(1_000_000, ScoFileParser::HEADER_OFFSET_BYTES);
    }

    // --- extractGameInfo() ---

    public function testExtractGameInfoReturnsFirst58Bytes(): void
    {
        $record = $this->buildRecord('G');

        $gameInfo = ScoFileParser::extractGameInfo($record);

        $this->assertSame(ScoFileParser::GAME_INFO_SIZE, strlen($gameInfo));
        $this->assertSame(str_repeat('G', 58), $gameInfo);
    }

    public function testExtractGameInfoDoesNotIncludePlayerData(): void
    {
        $record = $this->buildRecord('G');

        $gameInfo = ScoFileParser::extractGameInfo($record);

        // The first player slot starts at byte 58 with chr(1)
        $this->assertStringNotContainsString(chr(1), $gameInfo);
    }

    // --- extractPlayerSlot() ---

    public function testExtractFirstVisitorSlotReturns53Bytes(): void
    {
        $record = $this->buildRecord();

        $slot = ScoFileParser::extractPlayerSlot($record, 0);

        $this->assertSame(ScoFileParser::PLAYER_SLOT_SIZE, strlen($slot));
        // Slot 0 is filled with chr(1)
        $this->assertSame(str_repeat(chr(1), 53), $slot);
    }

    public function testExtractLastVisitorSlot(): void
    {
        $record = $this->buildRecord();

        $slot = ScoFileParser::extractPlayerSlot($record, 14);

        // Slot 14 is filled with chr(15)
        $this->assertSame(str_repeat(chr(15), 53), $slot);
    }

    public function testExtractFirstHomeSlot(): void
    {
        $record = $this->buildRecord();

        $slot = ScoFileParser::extractPlayerSlot($record, 15);

        // Slot 15 is filled with chr(16)
        $this->assertSame(str_repeat(chr(16), 53), $slot);
    }

    public function testExtractLastHomeSlot(): void
    {
        $record = $this->buildRecord();

        $slot = ScoFileParser::extractPlayerSlot($record, 29);

        // Slot 29 is filled with chr(30)
        $this->assertSame(str_repeat(chr(30), 53), $slot);
    }

    public function testExtractPlayerSlotOffsetsAreContiguous(): void
    {
        $record = $this->buildRecord();

        // Verify all 30 slots are at expected offsets and don't overlap
        for ($i = 0; $i < ScoFileParser::PLAYER_SLOT_COUNT; $i++) {
            $slot = ScoFileParser::extractPlayerSlot($record, $i);
            $expectedByte = chr($i + 1);
            $this->assertSame(
                str_repeat($expectedByte, 53),
                $slot,
                "Slot {$i} should be filled with chr(" . ($i + 1) . ')',
            );
        }
    }

    /**
     * Verify the slot layout covers the expected byte range:
     * game info (58) + 30 slots × 53 = 58 + 1590 = 1648 bytes of structured data.
     */
    public function testPlayerSlotsEndAt1648Bytes(): void
    {
        $expectedEnd = ScoFileParser::GAME_INFO_SIZE
            + (ScoFileParser::PLAYER_SLOT_COUNT * ScoFileParser::PLAYER_SLOT_SIZE);

        $this->assertSame(1648, $expectedEnd);
    }

    // --- isHomeTeamSlot() ---

    #[DataProvider('visitorSlotProvider')]
    public function testVisitorSlotsReturnFalse(int $slotIndex): void
    {
        $this->assertFalse(ScoFileParser::isHomeTeamSlot($slotIndex));
    }

    /**
     * @return array<string, list<int>>
     */
    public static function visitorSlotProvider(): array
    {
        return [
            'first visitor slot' => [0],
            'mid visitor slot' => [7],
            'last visitor slot' => [14],
        ];
    }

    #[DataProvider('homeSlotProvider')]
    public function testHomeSlotsReturnTrue(int $slotIndex): void
    {
        $this->assertTrue(ScoFileParser::isHomeTeamSlot($slotIndex));
    }

    /**
     * @return array<string, list<int>>
     */
    public static function homeSlotProvider(): array
    {
        return [
            'first home slot' => [15],
            'mid home slot' => [22],
            'last home slot' => [29],
        ];
    }

    public function testBoundaryBetweenVisitorAndHome(): void
    {
        $this->assertFalse(ScoFileParser::isHomeTeamSlot(14));
        $this->assertTrue(ScoFileParser::isHomeTeamSlot(15));
    }
}
