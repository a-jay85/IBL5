<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\PlrFileWriter;
use PlrParser\PlrTradeRelocator;

/**
 * @covers \PlrParser\PlrTradeRelocator
 */
class PlrTradeRelocatorTest extends TestCase
{
    private static function ordinalAt(string $line): int
    {
        return (int) trim(substr($line, 0, 4));
    }

    private static function pidAt(string $line): int
    {
        return (int) trim(substr($line, 38, 6));
    }

    public function testMovesInAscendingSourceOrdinalToFirstEmptySlot(): void
    {
        // Team 2 block = ordinals 31..60. Slots 31-33 filled, 34 and 35 empty.
        $lines = JsbExportFixtures::lines(2, [
            5 => ['pid' => 105, 'teamid' => 1],
            2 => ['pid' => 102, 'teamid' => 1],
            31 => ['pid' => 201, 'teamid' => 2],
            32 => ['pid' => 202, 'teamid' => 2],
            33 => ['pid' => 203, 'teamid' => 2],
        ]);

        $result = (new PlrTradeRelocator())->relocate($lines, [102 => 2, 105 => 2, 201 => 2]);

        self::assertSame(102, self::pidAt($result->lines[34]));
        self::assertSame(105, self::pidAt($result->lines[35]));
        self::assertSame(
            [[102, 2, 34], [105, 5, 35]],
            array_map(static fn (array $m): array => [$m['pid'], $m['oldOrdinal'], $m['newOrdinal']], $result->moves),
        );
        self::assertSame([], $result->warnings);
    }

    public function testVacatedSlotReceivesEmptyLineBytesWithVacatedOrdinal(): void
    {
        $lines = JsbExportFixtures::lines(2, [
            7 => ['pid' => 107, 'teamid' => 1],
            31 => ['pid' => 201, 'teamid' => 2],
        ]);
        $emptyTarget = $lines[32];

        $result = (new PlrTradeRelocator())->relocate($lines, [107 => 2]);

        $expected = substr_replace($emptyTarget, '   7', 0, 4);
        self::assertSame($expected, $result->lines[7]);
        self::assertSame(0, self::pidAt($result->lines[7]));
        self::assertSame(count($lines), count($result->lines));
        self::assertSame(strlen(implode("\r\n", $lines)), strlen(implode("\r\n", $result->lines)));
    }

    public function testMovedRecordGetsTargetOrdinalAndTeamFields(): void
    {
        $lines = JsbExportFixtures::lines(2, [
            4 => ['pid' => 104, 'teamid' => 1, 'name' => 'Mover One', 'prev' => -1],
            31 => ['pid' => 201, 'teamid' => 2],
        ]);

        $result = (new PlrTradeRelocator())->relocate($lines, [104 => 2]);
        $moved = $result->lines[32];

        self::assertSame(32, self::ordinalAt($moved));
        self::assertSame(104, self::pidAt($moved));
        self::assertSame(2, PlrFileWriter::readField($moved, 'teamid'));
        self::assertSame(2, PlrFileWriter::readField($moved, 'contractOwnedBy'));
        self::assertSame(1, PlrFileWriter::readField($moved, 'currentTeamIndex'));
        self::assertSame(-1, PlrFileWriter::readField($moved, 'previousTeamIndex'));
        self::assertSame(substr($lines[4], 4, 34), substr($moved, 4, 34));
        self::assertSame(
            [['pid' => 104, 'name' => 'Mover One', 'oldOrdinal' => 4, 'newOrdinal' => 32, 'oldTid' => 1, 'newTid' => 2]],
            $result->moves,
        );
    }

    public function testFullTargetBlockLeavesRecordInPlaceWithWarning(): void
    {
        $players = [3 => ['pid' => 103, 'teamid' => 1]];
        for ($ordinal = 31; $ordinal <= 60; $ordinal++) {
            $players[$ordinal] = ['pid' => 1000 + $ordinal, 'teamid' => 2];
        }
        $lines = JsbExportFixtures::lines(2, $players);

        $result = (new PlrTradeRelocator())->relocate($lines, [103 => 2]);

        self::assertSame($lines, $result->lines);
        self::assertSame([], $result->moves);
        self::assertSame(
            ['WARNING: no empty slot in team 2 block for pid 103; teamid written in place'],
            $result->warnings,
        );
    }

    public function testFreeAgentAndOutOfRangeTeamsAreNotRelocated(): void
    {
        $lines = JsbExportFixtures::lines(2, [
            1 => ['pid' => 101, 'teamid' => 1],
            2 => ['pid' => 102, 'teamid' => 1],
            3 => ['pid' => 103, 'teamid' => 0],
        ]);

        $result = (new PlrTradeRelocator())->relocate($lines, [101 => 0, 102 => 33, 103 => 2]);

        self::assertSame($lines, $result->lines);
        self::assertSame([], $result->moves);
        self::assertSame([], $result->warnings);
    }
}
