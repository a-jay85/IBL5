<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\SchFileParser;

/**
 * SchFileParserTest - Tests for the JSB .sch file parser
 *
 * Covers:
 * - Game record parsing (1-digit and 2-digit team IDs, various score widths)
 * - Empty slot detection
 * - Date slot to month/day conversion
 * - Invalid date filtering (Nov 31, Feb 30, etc.)
 * - BoxID computation
 * - Full file parsing with real .sch data
 */
class SchFileParserTest extends TestCase
{
    // parseGameRecord tests

    public function testParseGameRecordWithTwoDigitTeams(): void
    {
        // "2409146130" → V=24, H=09, VS=146, HS=130
        $result = SchFileParser::parseGameRecord('2409146130');

        $this->assertNotNull($result);
        $this->assertSame(24, $result['visitor']);
        $this->assertSame(9, $result['home']);
        $this->assertSame(146, $result['visitor_score']);
        $this->assertSame(130, $result['home_score']);
    }

    public function testParseGameRecordWithSingleDigitVisitor(): void
    {
        // "510 92131 " → V=5, H=10, VS=92, HS=131
        $result = SchFileParser::parseGameRecord('510 92131 ');

        $this->assertNotNull($result);
        $this->assertSame(5, $result['visitor']);
        $this->assertSame(10, $result['home']);
        $this->assertSame(92, $result['visitor_score']);
        $this->assertSame(131, $result['home_score']);
    }

    public function testParseGameRecordWithSingleDigitHome(): void
    {
        // "2206118136" → V=22, H=06, VS=118, HS=136
        $result = SchFileParser::parseGameRecord('2206118136');

        $this->assertNotNull($result);
        $this->assertSame(22, $result['visitor']);
        $this->assertSame(6, $result['home']);
        $this->assertSame(118, $result['visitor_score']);
        $this->assertSame(136, $result['home_score']);
    }

    public function testParseGameRecordWithPaddedScores(): void
    {
        // "1401111146" → V=14, H=01, VS=111, HS=146
        $result = SchFileParser::parseGameRecord('1401111146');

        $this->assertNotNull($result);
        $this->assertSame(14, $result['visitor']);
        $this->assertSame(1, $result['home']);
        $this->assertSame(111, $result['visitor_score']);
        $this->assertSame(146, $result['home_score']);
    }

    public function testParseGameRecordReturnsNullForEmptySlot(): void
    {
        $result = SchFileParser::parseGameRecord('0   0     ');

        $this->assertNull($result);
    }

    public function testParseGameRecordReturnsNullForWrongLength(): void
    {
        $result = SchFileParser::parseGameRecord('short');

        $this->assertNull($result);
    }

    public function testParseGameRecordHandlesUnplayedGame(): void
    {
        // "308 0     " → V=3, H=08, VS=0, HS=0
        $result = SchFileParser::parseGameRecord('308 0     ');

        $this->assertNotNull($result);
        $this->assertSame(3, $result['visitor']);
        $this->assertSame(8, $result['home']);
        $this->assertSame(0, $result['visitor_score']);
        $this->assertSame(0, $result['home_score']);
    }

    public function testParseGameRecordHandlesUnplayedGameWithTwoDigitTeams(): void
    {
        // "18200     " → V=18, H=20, VS=0, HS=0
        $result = SchFileParser::parseGameRecord('18200     ');

        $this->assertNotNull($result);
        $this->assertSame(18, $result['visitor']);
        $this->assertSame(20, $result['home']);
        $this->assertSame(0, $result['visitor_score']);
        $this->assertSame(0, $result['home_score']);
    }

    // dateSlotToMonthDay tests

    public function testDateSlotToMonthDayNovember(): void
    {
        // Slot 32 → month_offset=1, day_offset=1 → November 2
        $result = SchFileParser::dateSlotToMonthDay(32);

        $this->assertNotNull($result);
        $this->assertSame(11, $result['month']);
        $this->assertSame(2, $result['day']);
    }

    public function testDateSlotToMonthDayDecember(): void
    {
        // Slot 62 → month_offset=2, day_offset=0 → December 1
        $result = SchFileParser::dateSlotToMonthDay(62);

        $this->assertNotNull($result);
        $this->assertSame(12, $result['month']);
        $this->assertSame(1, $result['day']);
    }

    public function testDateSlotToMonthDayJanuary(): void
    {
        // Slot 93 → month_offset=3, day_offset=0 → January 1
        $result = SchFileParser::dateSlotToMonthDay(93);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['month']);
        $this->assertSame(1, $result['day']);
    }

    public function testDateSlotToMonthDayOctober(): void
    {
        // Slot 0 → month_offset=0, day_offset=0 → October 1
        $result = SchFileParser::dateSlotToMonthDay(0);

        $this->assertNotNull($result);
        $this->assertSame(10, $result['month']);
        $this->assertSame(1, $result['day']);
    }

    public function testDateSlotToMonthDayFebruary(): void
    {
        // Slot 124 → month_offset=4, day_offset=0 → February 1
        $result = SchFileParser::dateSlotToMonthDay(124);

        $this->assertNotNull($result);
        $this->assertSame(2, $result['month']);
        $this->assertSame(1, $result['day']);
    }

    public function testDateSlotToMonthDayReturnsNullForInvalidDate(): void
    {
        // Slot 61 → month_offset=1, day_offset=30 → November 31 (invalid)
        $result = SchFileParser::dateSlotToMonthDay(61);

        $this->assertNull($result);
    }

    public function testDateSlotToMonthDayReturnsNullForFeb30(): void
    {
        // Slot 153 → month_offset=4, day_offset=29 → February 30 (invalid)
        $result = SchFileParser::dateSlotToMonthDay(153);

        $this->assertNull($result);
    }

    public function testDateSlotToMonthDayReturnsNullForExcessiveOffset(): void
    {
        // Slot 372 → month_offset=12 → beyond MAX_MONTH_OFFSET
        $result = SchFileParser::dateSlotToMonthDay(372);

        $this->assertNull($result);
    }

    // computeBoxId tests

    public function testComputeBoxIdNovember2FirstGame(): void
    {
        // Slot 32 → month_offset=1, day_offset=1, game_index=0
        // BoxID = 1 * 500 + 1 * 15 + 0 = 515
        $result = SchFileParser::computeBoxId(32, 0);

        $this->assertSame(515, $result);
    }

    public function testComputeBoxIdDecember1FirstGame(): void
    {
        // Slot 62 → month_offset=2, day_offset=0, game_index=0
        // BoxID = 2 * 500 + 0 * 15 + 0 = 1000
        $result = SchFileParser::computeBoxId(62, 0);

        $this->assertSame(1000, $result);
    }

    public function testComputeBoxIdWithGameIndex(): void
    {
        // Slot 32 → month_offset=1, day_offset=1, game_index=3
        // BoxID = 1 * 500 + 1 * 15 + 3 = 518
        $result = SchFileParser::computeBoxId(32, 3);

        $this->assertSame(518, $result);
    }

    public function testComputeBoxIdJanuary1(): void
    {
        // Slot 93 → month_offset=3, day_offset=0, game_index=0
        // BoxID = 3 * 500 + 0 * 15 + 0 = 1500
        $result = SchFileParser::computeBoxId(93, 0);

        $this->assertSame(1500, $result);
    }

    // parseFile tests

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Schedule file not found');

        SchFileParser::parseFile('/nonexistent/path/IBL5.sch');
    }

    public function testParseFileThrowsForInvalidSize(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sch_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, str_repeat("\0", 100));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Invalid .sch file size');

            SchFileParser::parseFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileWithRealData(): void
    {
        $schFile = dirname(__DIR__, 2) . '/IBL5.sch';
        if (!file_exists($schFile)) {
            $this->fail("Test .sch file not found at: {$schFile}");
        }

        $games = SchFileParser::parseFile($schFile);

        $this->assertNotEmpty($games);

        // Count played vs unplayed
        $played = 0;
        $unplayed = 0;
        foreach ($games as $game) {
            if ($game['played']) {
                $played++;
            } else {
                $unplayed++;
            }
        }

        // Played + unplayed should equal total games
        $this->assertSame(count($games), $played + $unplayed, 'Played + unplayed should equal total games');

        // Every game is either played or unplayed — played games have nonzero scores
        foreach ($games as $game) {
            if ($game['played']) {
                $this->assertGreaterThan(0, $game['visitor_score'] + $game['home_score'], 'Played game should have nonzero combined score');
            } else {
                $this->assertSame(0, $game['visitor_score'], 'Unplayed game should have 0 visitor score');
                $this->assertSame(0, $game['home_score'], 'Unplayed game should have 0 home score');
            }
        }
    }

    public function testParseFileFirstGameHasValidStructure(): void
    {
        $schFile = dirname(__DIR__, 2) . '/IBL5.sch';
        if (!file_exists($schFile)) {
            $this->fail("Test .sch file not found at: {$schFile}");
        }

        $games = SchFileParser::parseFile($schFile);
        $firstGame = $games[0];

        // First game should have all required keys with valid types
        $this->assertArrayHasKey('date_slot', $firstGame);
        $this->assertArrayHasKey('game_index', $firstGame);
        $this->assertArrayHasKey('visitor', $firstGame);
        $this->assertArrayHasKey('home', $firstGame);
        $this->assertArrayHasKey('visitor_score', $firstGame);
        $this->assertArrayHasKey('home_score', $firstGame);
        $this->assertArrayHasKey('played', $firstGame);

        // First game is always game_index 0
        $this->assertSame(0, $firstGame['game_index']);

        // Team IDs should be valid
        $this->assertGreaterThan(0, $firstGame['visitor']);
        $this->assertGreaterThan(0, $firstGame['home']);
        $this->assertLessThanOrEqual(\League::MAX_REAL_TEAMID, $firstGame['visitor']);
        $this->assertLessThanOrEqual(\League::MAX_REAL_TEAMID, $firstGame['home']);

        // Visitor and home should be different teams
        $this->assertNotSame($firstGame['visitor'], $firstGame['home'], 'A team cannot play itself');
    }

    public function testParseFileGamesHaveValidTeamIds(): void
    {
        $schFile = dirname(__DIR__, 2) . '/IBL5.sch';
        if (!file_exists($schFile)) {
            $this->fail("Test .sch file not found at: {$schFile}");
        }

        $games = SchFileParser::parseFile($schFile);

        foreach ($games as $game) {
            $this->assertGreaterThan(0, $game['visitor'], 'Visitor team ID should be positive');
            $this->assertGreaterThan(0, $game['home'], 'Home team ID should be positive');
            $this->assertLessThanOrEqual(\League::MAX_REAL_TEAMID, $game['visitor']);
            $this->assertLessThanOrEqual(\League::MAX_REAL_TEAMID, $game['home']);
        }
    }

    public function testParseFileBoxIdsMatchExpected(): void
    {
        $schFile = dirname(__DIR__, 2) . '/IBL5.sch';
        if (!file_exists($schFile)) {
            $this->fail("Test .sch file not found at: {$schFile}");
        }

        $games = SchFileParser::parseFile($schFile);

        // Verify the first few BoxIDs match known values
        // Game 0: date_slot=32, game_index=0 → BoxID=515
        $this->assertSame(515, SchFileParser::computeBoxId($games[0]['date_slot'], $games[0]['game_index']));

        // Game 1: date_slot=32, game_index=1 → BoxID=516
        $this->assertSame(516, SchFileParser::computeBoxId($games[1]['date_slot'], $games[1]['game_index']));
    }
}
