<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use Boxscore\Boxscore;
use JsbParser\ScoFileParser;
use JsbParser\ScoFileWriter;
use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStats;

/**
 * @covers \JsbParser\ScoFileWriter
 */
class ScoFileWriterTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function mockDb(): \mysqli
    {
        return $this->createMock(\mysqli::class);
    }

    /** Build a minimal synthetic .sco of the correct size (all spaces). */
    private function blankSco(): string
    {
        return str_repeat(' ', ScoFileWriter::SCO_FILE_SIZE);
    }

    /** Build a .sco with a non-blank tail so splice can verify tail preservation. */
    private function syntheticSco(): string
    {
        return str_repeat(' ', 4000) . str_repeat('x', ScoFileWriter::SCO_FILE_SIZE - 4000);
    }

    /** Minimal valid 4000-byte block (all spaces except one non-space in game-info). */
    private function minimalBlock(): string
    {
        $record = str_repeat(' ', ScoFileParser::RECORD_SIZE);
        // Make game-info non-blank with a single digit so trim() !== '' passes
        $record = substr_replace($record, '1', 0, 1);
        return $record . str_repeat(' ', ScoFileParser::RECORD_SIZE);
    }

    // ── V3: Game-info round-trip ──────────────────────────────────────────────

    public function testGameInfoRoundTrip(): void
    {
        $visitorQ = [34, 39, 30, 31, 0];
        $homeQ    = [34, 31, 39, 41, 0];

        $gameInfo = ScoFileWriter::buildGameInfo(
            1, 1, 0, 39, 40,
            5244, 20000,
            0, 0, 0, 0,
            $visitorQ, $homeQ,
        );

        $record = ScoFileWriter::buildRecord($gameInfo, []);
        $boxscore = Boxscore::withGameInfoLine(ScoFileParser::extractGameInfo($record), 2007, 'Regular Season/Playoffs');

        // Quarter scores and attendance/capacity are read verbatim — assert as ints (V3)
        self::assertEquals(34, (int) $boxscore->visitor_q1_points);
        self::assertEquals(39, (int) $boxscore->visitor_q2_points);
        self::assertEquals(30, (int) $boxscore->visitor_q3_points);
        self::assertEquals(31, (int) $boxscore->visitor_q4_points);
        self::assertEquals(0,  (int) $boxscore->visitor_ot_points);
        self::assertEquals(34, (int) $boxscore->home_q1_points);
        self::assertEquals(31, (int) $boxscore->home_q2_points);
        self::assertEquals(39, (int) $boxscore->home_q3_points);
        self::assertEquals(41, (int) $boxscore->home_q4_points);
        self::assertEquals(0,  (int) $boxscore->home_ot_points);
        self::assertEquals(5244,  (int) $boxscore->attendance);
        self::assertEquals(20000, (int) $boxscore->capacity);
        self::assertEquals(0, (int) $boxscore->visitor_wins);
        self::assertEquals(0, (int) $boxscore->visitor_losses);
        self::assertEquals(0, (int) $boxscore->home_wins);
        self::assertEquals(0, (int) $boxscore->home_losses);
    }

    // ── V4: Player slot round-trip ────────────────────────────────────────────

    public function testPlayerSlotRoundTrip(): void
    {
        $slot = ScoFileWriter::buildPlayerSlot(
            'Test Player',  // name (11 chars, fits W=16)
            'PG',           // pos
            5936,           // pid
            32,             // min
            7,              // twoGM
            15,             // twoGA
            5,              // ftm
            5,              // fta
            0,              // threeGM
            3,              // threeGA
            3,              // orb
            1,              // drb
            7,              // ast
            0,              // stl
            3,              // tov
            0,              // blk
            3,              // pf
        );

        $stats = PlayerStats::withBoxscoreInfoLine($this->mockDb(), $slot);

        // V4: every stat parses back equal (int-cast to handle space-padded strings)
        self::assertSame('Test Player', trim($stats->name));
        self::assertSame('PG', trim($stats->position));
        self::assertEquals(5936, (int) $stats->playerID);
        self::assertEquals(32, (int) $stats->gameMinutesPlayed);
        self::assertEquals(7,  (int) $stats->gameFieldGoalsMade);
        self::assertEquals(15, (int) $stats->gameFieldGoalsAttempted);
        self::assertEquals(5,  (int) $stats->gameFreeThrowsMade);
        self::assertEquals(5,  (int) $stats->gameFreeThrowsAttempted);
        self::assertEquals(0,  (int) $stats->gameThreePointersMade);
        self::assertEquals(3,  (int) $stats->gameThreePointersAttempted);
        self::assertEquals(3,  (int) $stats->gameOffensiveRebounds);
        self::assertEquals(1,  (int) $stats->gameDefensiveRebounds);
        self::assertEquals(7,  (int) $stats->gameAssists);
        self::assertEquals(0,  (int) $stats->gameSteals);
        self::assertEquals(3,  (int) $stats->gameTurnovers);
        self::assertEquals(0,  (int) $stats->gameBlocks);
        self::assertEquals(3,  (int) $stats->gamePersonalFouls);
    }

    // ── V5: Team-total slot has playerID 0 ───────────────────────────────────

    public function testTeamTotalSlotHasZeroPlayerId(): void
    {
        $slot = ScoFileWriter::buildTeamTotalSlot('Rookies', 47, 98, 16, 21, 8, 21, 25, 41, 30, 11, 22, 8, 19);
        $stats = PlayerStats::withBoxscoreInfoLine($this->mockDb(), $slot);

        self::assertEquals(0, (int) $stats->playerID);
        self::assertSame('Rookies', trim($stats->name));
    }

    // ── V6: Size assertions ───────────────────────────────────────────────────

    public function testRecordIsExactly2000Bytes(): void
    {
        $gameInfo = str_repeat(' ', ScoFileParser::GAME_INFO_SIZE);
        $record = ScoFileWriter::buildRecord($gameInfo, []);
        self::assertSame(ScoFileParser::RECORD_SIZE, strlen($record));
    }

    public function testBlockIsExactly4000Bytes(): void
    {
        $block = $this->buildMinimalRsgAsgBlock();
        self::assertSame(ScoFileParser::RECORD_SIZE * 2, strlen($block));
    }

    // ── V7: Both records' game-info non-blank (importer guard passes) ─────────

    public function testGameInfoNonBlankSoImporterGuardPasses(): void
    {
        $block = $this->buildMinimalRsgAsgBlock();

        $record0 = substr($block, 0, ScoFileParser::RECORD_SIZE);
        $record1 = substr($block, ScoFileParser::RECORD_SIZE, ScoFileParser::RECORD_SIZE);

        self::assertNotSame('', trim(ScoFileParser::extractGameInfo($record0)), 'record0 game-info must be non-blank');
        self::assertNotSame('', trim(ScoFileParser::extractGameInfo($record1)), 'record1 game-info must be non-blank');
    }

    // ── V8: Encoder rejects overlong name ────────────────────────────────────

    public function testRejectsOverlongName(): void
    {
        $this->expectException(\RuntimeException::class);
        ScoFileWriter::buildPlayerSlot('12345678901234567', 'PG', 1, 30, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
    }

    // ── V9: Encoder rejects out-of-range stat (quarter value ≥ 1000) ──────────

    public function testRejectsOutOfRangeStat(): void
    {
        $this->expectException(\RuntimeException::class);
        ScoFileWriter::buildGameInfo(
            1, 1, 0, 39, 40,
            5244, 20000,
            0, 0, 0, 0,
            [1000, 0, 0, 0, 0], // 1000 overflows W=3
            [0, 0, 0, 0, 0],
        );
    }

    // ── V10: Encoder rejects negative stat ───────────────────────────────────

    public function testRejectsNegativeStat(): void
    {
        $this->expectException(\RuntimeException::class);
        ScoFileWriter::buildPlayerSlot('Test Player', 'PG', 1, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
    }

    // ── V23a: Splice guard rejects non-blank target ───────────────────────────

    public function testSpliceRejectsNonBlankTarget(): void
    {
        $sco = str_repeat(' ', ScoFileWriter::SCO_FILE_SIZE);
        // Dirty byte 0
        $sco = substr_replace($sco, 'X', 0, 1);

        $this->expectException(\RuntimeException::class);
        ScoFileWriter::spliceAllStarBlock($sco, str_repeat('A', 4000));
    }

    // ── V23b: Splice guard rejects wrong-size inputs ──────────────────────────

    public function testSpliceRejectsWrongBlockSize(): void
    {
        $this->expectException(\RuntimeException::class);
        ScoFileWriter::spliceAllStarBlock($this->blankSco(), str_repeat('A', 3999));
    }

    public function testSpliceRejectsWrongScoSize(): void
    {
        $this->expectException(\RuntimeException::class);
        ScoFileWriter::spliceAllStarBlock(str_repeat(' ', 100), str_repeat('A', 4000));
    }

    // ── V23c: Splice happy path ───────────────────────────────────────────────

    public function testSplicePreservesTailAndLength(): void
    {
        $sco = $this->syntheticSco();
        $block = str_repeat('B', 4000);

        $patched = ScoFileWriter::spliceAllStarBlock($sco, $block);

        // Total length preserved
        self::assertSame(ScoFileWriter::SCO_FILE_SIZE, strlen($patched));

        // First 4000 bytes replaced
        self::assertSame($block, substr($patched, 0, 4000));

        // Tail (bytes 1,000,000..EOF) hash identical
        $originalTail = substr($sco, ScoFileParser::HEADER_OFFSET_BYTES);
        $patchedTail  = substr($patched, ScoFileParser::HEADER_OFFSET_BYTES);
        self::assertSame(hash('sha256', $originalTail), hash('sha256', $patchedTail));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build a valid 4000-byte block using actual RSG/ASG data from the dataset.
     * Uses minimal but real stats so V7 (non-blank game-info) and V6 (sizes) pass.
     */
    private function buildMinimalRsgAsgBlock(): string
    {
        $risingStars = [
            'visitor_name'    => 'Rookies',
            'home_name'       => 'Sophomores',
            'visitor_q'       => [34, 39, 30, 31, 0],
            'home_q'          => [34, 31, 39, 41, 0],
            'visitor_teamid'  => 40,
            'home_teamid'     => 41,
            'attendance'      => 5244,
            'capacity'        => 20000,
            'visitor_team'    => ['twoGM' => 47, 'twoGA' => 98, 'ftm' => 16, 'fta' => 21, 'threeGM' => 8, 'threeGA' => 21, 'orb' => 25, 'drb' => 41, 'ast' => 30, 'stl' => 11, 'tov' => 22, 'blk' => 8, 'pf' => 19],
            'home_team'       => ['twoGM' => 52, 'twoGA' => 100, 'ftm' => 5, 'fta' => 9, 'threeGM' => 12, 'threeGA' => 27, 'orb' => 24, 'drb' => 43, 'ast' => 34, 'stl' => 10, 'tov' => 21, 'blk' => 14, 'pf' => 21],
            'visitor_players' => [
                ['name' => 'Player A',         'pos' => 'PG', 'pid' => 5936, 'min' => 32, 'twoGM' => 7, 'twoGA' => 15, 'ftm' => 5, 'fta' => 5, 'threeGM' => 0, 'threeGA' => 3, 'orb' => 3, 'drb' => 1, 'ast' => 7, 'stl' => 0, 'tov' => 3, 'blk' => 0, 'pf' => 3],
                ['name' => 'Player B',         'pos' => 'PG', 'pid' => 5938, 'min' => 16, 'twoGM' => 1, 'twoGA' => 2, 'ftm' => 0, 'fta' => 0, 'threeGM' => 1, 'threeGA' => 5, 'orb' => 1, 'drb' => 0, 'ast' => 1, 'stl' => 0, 'tov' => 2, 'blk' => 1, 'pf' => 0],
                ['name' => 'Player C',         'pos' => 'SG', 'pid' => 5931, 'min' => 32, 'twoGM' => 8, 'twoGA' => 16, 'ftm' => 3, 'fta' => 5, 'threeGM' => 2, 'threeGA' => 5, 'orb' => 4, 'drb' => 4, 'ast' => 0, 'stl' => 3, 'tov' => 1, 'blk' => 1, 'pf' => 2],
                ['name' => 'Player D',         'pos' => 'SG', 'pid' => 5930, 'min' => 19, 'twoGM' => 1, 'twoGA' => 3, 'ftm' => 2, 'fta' => 3, 'threeGM' => 2, 'threeGA' => 2, 'orb' => 1, 'drb' => 1, 'ast' => 2, 'stl' => 2, 'tov' => 1, 'blk' => 1, 'pf' => 1],
                ['name' => 'Player E',         'pos' => 'SF', 'pid' => 5937, 'min' => 29, 'twoGM' => 5, 'twoGA' => 11, 'ftm' => 0, 'fta' => 0, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 0, 'drb' => 4, 'ast' => 11, 'stl' => 1, 'tov' => 5, 'blk' => 0, 'pf' => 2],
                ['name' => 'Player F',         'pos' => 'SF', 'pid' => 5939, 'min' => 13, 'twoGM' => 6, 'twoGA' => 11, 'ftm' => 1, 'fta' => 2, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 1, 'drb' => 3, 'ast' => 4, 'stl' => 0, 'tov' => 3, 'blk' => 1, 'pf' => 4],
                ['name' => 'Player G',         'pos' => 'PF', 'pid' => 5929, 'min' => 30, 'twoGM' => 7, 'twoGA' => 15, 'ftm' => 2, 'fta' => 2, 'threeGM' => 2, 'threeGA' => 3, 'orb' => 3, 'drb' => 4, 'ast' => 2, 'stl' => 1, 'tov' => 2, 'blk' => 1, 'pf' => 1],
                ['name' => 'Player H',         'pos' => 'PF', 'pid' => 5935, 'min' => 30, 'twoGM' => 7, 'twoGA' => 13, 'ftm' => 3, 'fta' => 4, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 6, 'drb' => 6, 'ast' => 2, 'stl' => 4, 'tov' => 3, 'blk' => 1, 'pf' => 3],
                ['name' => 'Player I',         'pos' => 'C',  'pid' => 5942, 'min' => 14, 'twoGM' => 2, 'twoGA' => 5, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 3, 'drb' => 4, 'ast' => 0, 'stl' => 0, 'tov' => 2, 'blk' => 1, 'pf' => 1],
                ['name' => 'Player J',         'pos' => 'C',  'pid' => 5964, 'min' => 22, 'twoGM' => 3, 'twoGA' => 7, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 1, 'drb' => 8, 'ast' => 1, 'stl' => 0, 'tov' => 0, 'blk' => 1, 'pf' => 2],
            ],
            'home_players'    => [
                ['name' => 'Home A',           'pos' => 'PG', 'pid' => 5640, 'min' => 31, 'twoGM' => 13, 'twoGA' => 21, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 2, 'orb' => 4, 'drb' => 7, 'ast' => 12, 'stl' => 3, 'tov' => 6, 'blk' => 3, 'pf' => 1],
                ['name' => 'Home B',           'pos' => 'PG', 'pid' => 5649, 'min' => 13, 'twoGM' => 1, 'twoGA' => 3, 'ftm' => 3, 'fta' => 4, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 0, 'drb' => 1, 'ast' => 5, 'stl' => 3, 'tov' => 2, 'blk' => 0, 'pf' => 0],
                ['name' => 'Home C',           'pos' => 'SG', 'pid' => 5642, 'min' => 20, 'twoGM' => 10, 'twoGA' => 12, 'ftm' => 1, 'fta' => 3, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 1, 'drb' => 1, 'ast' => 6, 'stl' => 0, 'tov' => 3, 'blk' => 0, 'pf' => 2],
                ['name' => 'Home D',           'pos' => 'SG', 'pid' => 5659, 'min' => 30, 'twoGM' => 4, 'twoGA' => 13, 'ftm' => 0, 'fta' => 0, 'threeGM' => 6, 'threeGA' => 11, 'orb' => 0, 'drb' => 5, 'ast' => 5, 'stl' => 2, 'tov' => 3, 'blk' => 2, 'pf' => 2],
                ['name' => 'Home E',           'pos' => 'SF', 'pid' => 5645, 'min' => 31, 'twoGM' => 7, 'twoGA' => 14, 'ftm' => 1, 'fta' => 1, 'threeGM' => 2, 'threeGA' => 5, 'orb' => 0, 'drb' => 5, 'ast' => 1, 'stl' => 0, 'tov' => 0, 'blk' => 0, 'pf' => 4],
                ['name' => 'Home F',           'pos' => 'SF', 'pid' => 5685, 'min' => 20, 'twoGM' => 1, 'twoGA' => 5, 'ftm' => 0, 'fta' => 0, 'threeGM' => 2, 'threeGA' => 4, 'orb' => 0, 'drb' => 1, 'ast' => 2, 'stl' => 1, 'tov' => 0, 'blk' => 0, 'pf' => 2],
                ['name' => 'Home G',           'pos' => 'PF', 'pid' => 5646, 'min' => 17, 'twoGM' => 3, 'twoGA' => 5, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 2, 'drb' => 4, 'ast' => 0, 'stl' => 1, 'tov' => 2, 'blk' => 0, 'pf' => 5],
                ['name' => 'Home H (DNP)',     'pos' => 'PF', 'pid' => 5663, 'min' => 0,  'twoGM' => 0, 'twoGA' => 0, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 0, 'drb' => 0, 'ast' => 0, 'stl' => 0, 'tov' => 0, 'blk' => 0, 'pf' => 0],
                ['name' => 'Home I',           'pos' => 'C',  'pid' => 5641, 'min' => 31, 'twoGM' => 7, 'twoGA' => 16, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 10, 'drb' => 6, 'ast' => 2, 'stl' => 0, 'tov' => 1, 'blk' => 4, 'pf' => 4],
                ['name' => 'Home J',           'pos' => 'C',  'pid' => 5644, 'min' => 43, 'twoGM' => 6, 'twoGA' => 11, 'ftm' => 0, 'fta' => 1, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 6, 'drb' => 11, 'ast' => 1, 'stl' => 0, 'tov' => 4, 'blk' => 5, 'pf' => 1],
            ],
        ];

        $allStar = [
            'visitor_name'    => 'Team Away',
            'home_name'       => 'Team Home',
            'visitor_q'       => [43, 31, 44, 26, 0],
            'home_q'          => [43, 48, 55, 36, 0],
            'visitor_teamid'  => 50,
            'home_teamid'     => 51,
            'attendance'      => 5244,
            'capacity'        => 20000,
            'visitor_team'    => ['twoGM' => 48, 'twoGA' => 88, 'ftm' => 33, 'fta' => 38, 'threeGM' => 5, 'threeGA' => 20, 'orb' => 11, 'drb' => 42, 'ast' => 23, 'stl' => 4, 'tov' => 20, 'blk' => 10, 'pf' => 23],
            'home_team'       => ['twoGM' => 60, 'twoGA' => 102, 'ftm' => 26, 'fta' => 29, 'threeGM' => 12, 'threeGA' => 30, 'orb' => 20, 'drb' => 47, 'ast' => 39, 'stl' => 16, 'tov' => 10, 'blk' => 9, 'pf' => 28],
            'visitor_players' => [
                ['name' => 'ASG Vis A', 'pos' => 'PG', 'pid' => 3852, 'min' => 13, 'twoGM' => 2, 'twoGA' => 4, 'ftm' => 2, 'fta' => 2, 'threeGM' => 1, 'threeGA' => 4, 'orb' => 0, 'drb' => 2, 'ast' => 3, 'stl' => 0, 'tov' => 1, 'blk' => 0, 'pf' => 3],
                ['name' => 'ASG Vis B', 'pos' => 'PG', 'pid' => 5640, 'min' => 17, 'twoGM' => 3, 'twoGA' => 4, 'ftm' => 4, 'fta' => 4, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 1, 'drb' => 2, 'ast' => 5, 'stl' => 0, 'tov' => 2, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG Vis C', 'pos' => 'PG', 'pid' => 3851, 'min' => 28, 'twoGM' => 8, 'twoGA' => 11, 'ftm' => 4, 'fta' => 4, 'threeGM' => 1, 'threeGA' => 3, 'orb' => 0, 'drb' => 3, 'ast' => 4, 'stl' => 2, 'tov' => 2, 'blk' => 0, 'pf' => 1],
                ['name' => 'ASG Vis D', 'pos' => 'PF', 'pid' => 4148, 'min' => 21, 'twoGM' => 2, 'twoGA' => 4, 'ftm' => 5, 'fta' => 5, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 2, 'drb' => 5, 'ast' => 2, 'stl' => 0, 'tov' => 1, 'blk' => 0, 'pf' => 4],
                ['name' => 'ASG Vis E', 'pos' => 'SF', 'pid' => 5258, 'min' => 25, 'twoGM' => 6, 'twoGA' => 12, 'ftm' => 5, 'fta' => 6, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 0, 'drb' => 5, 'ast' => 4, 'stl' => 1, 'tov' => 3, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG Vis F', 'pos' => 'C',  'pid' => 4500, 'min' => 22, 'twoGM' => 7, 'twoGA' => 10, 'ftm' => 7, 'fta' => 7, 'threeGM' => 2, 'threeGA' => 2, 'orb' => 3, 'drb' => 4, 'ast' => 0, 'stl' => 0, 'tov' => 0, 'blk' => 5, 'pf' => 2],
                ['name' => 'ASG Vis G', 'pos' => 'SG', 'pid' => 3282, 'min' => 15, 'twoGM' => 0, 'twoGA' => 1, 'ftm' => 2, 'fta' => 2, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 0, 'drb' => 3, 'ast' => 3, 'stl' => 0, 'tov' => 2, 'blk' => 0, 'pf' => 4],
                ['name' => 'ASG Vis H', 'pos' => 'SG', 'pid' => 3561, 'min' => 13, 'twoGM' => 2, 'twoGA' => 5, 'ftm' => 0, 'fta' => 0, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 0, 'drb' => 1, 'ast' => 0, 'stl' => 0, 'tov' => 3, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG Vis I', 'pos' => 'C',  'pid' => 2975, 'min' => 25, 'twoGM' => 7, 'twoGA' => 15, 'ftm' => 3, 'fta' => 6, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 1, 'drb' => 8, 'ast' => 1, 'stl' => 1, 'tov' => 3, 'blk' => 4, 'pf' => 4],
                ['name' => 'ASG Vis J', 'pos' => 'SF', 'pid' => 3277, 'min' => 15, 'twoGM' => 4, 'twoGA' => 8, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 5, 'orb' => 0, 'drb' => 0, 'ast' => 0, 'stl' => 0, 'tov' => 0, 'blk' => 0, 'pf' => 2],
                ['name' => 'Drazen Dalipagic', 'pos' => 'SG', 'pid' => 5265, 'min' => 20, 'twoGM' => 5, 'twoGA' => 6, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 0, 'drb' => 3, 'ast' => 0, 'stl' => 0, 'tov' => 1, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG Vis L', 'pos' => 'PF', 'pid' => 4507, 'min' => 21, 'twoGM' => 2, 'twoGA' => 8, 'ftm' => 1, 'fta' => 2, 'threeGM' => 0, 'threeGA' => 2, 'orb' => 1, 'drb' => 4, 'ast' => 1, 'stl' => 0, 'tov' => 2, 'blk' => 1, 'pf' => 3],
            ],
            'home_players'    => [
                ['name' => 'ASG Home A', 'pos' => 'PG', 'pid' => 4150, 'min' => 18, 'twoGM' => 5, 'twoGA' => 8, 'ftm' => 0, 'fta' => 0, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 1, 'drb' => 3, 'ast' => 3, 'stl' => 2, 'tov' => 2, 'blk' => 1, 'pf' => 1],
                ['name' => 'ASG Home B', 'pos' => 'PG', 'pid' => 3556, 'min' => 18, 'twoGM' => 7, 'twoGA' => 10, 'ftm' => 4, 'fta' => 4, 'threeGM' => 0, 'threeGA' => 3, 'orb' => 1, 'drb' => 6, 'ast' => 5, 'stl' => 2, 'tov' => 1, 'blk' => 1, 'pf' => 1],
                ['name' => 'ASG Home C', 'pos' => 'SG', 'pid' => 3552, 'min' => 18, 'twoGM' => 4, 'twoGA' => 8, 'ftm' => 0, 'fta' => 0, 'threeGM' => 2, 'threeGA' => 5, 'orb' => 0, 'drb' => 3, 'ast' => 4, 'stl' => 2, 'tov' => 0, 'blk' => 1, 'pf' => 3],
                ['name' => 'ASG Home D', 'pos' => 'PF', 'pid' => 5261, 'min' => 22, 'twoGM' => 3, 'twoGA' => 6, 'ftm' => 7, 'fta' => 8, 'threeGM' => 1, 'threeGA' => 1, 'orb' => 1, 'drb' => 1, 'ast' => 2, 'stl' => 0, 'tov' => 0, 'blk' => 1, 'pf' => 1],
                ['name' => 'ASG Home E', 'pos' => 'C',  'pid' => 3555, 'min' => 12, 'twoGM' => 3, 'twoGA' => 6, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 2, 'drb' => 5, 'ast' => 0, 'stl' => 0, 'tov' => 1, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG Home F', 'pos' => 'SG', 'pid' => 5259, 'min' => 27, 'twoGM' => 8, 'twoGA' => 19, 'ftm' => 1, 'fta' => 2, 'threeGM' => 1, 'threeGA' => 4, 'orb' => 7, 'drb' => 1, 'ast' => 8, 'stl' => 1, 'tov' => 1, 'blk' => 1, 'pf' => 2],
                ['name' => 'ASG Home G', 'pos' => 'C',  'pid' => 4490, 'min' => 20, 'twoGM' => 5, 'twoGA' => 9, 'ftm' => 3, 'fta' => 3, 'threeGM' => 3, 'threeGA' => 5, 'orb' => 1, 'drb' => 4, 'ast' => 1, 'stl' => 1, 'tov' => 1, 'blk' => 0, 'pf' => 3],
                ['name' => 'ASG Home H', 'pos' => 'C',  'pid' => 4492, 'min' => 20, 'twoGM' => 6, 'twoGA' => 11, 'ftm' => 2, 'fta' => 2, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 2, 'drb' => 7, 'ast' => 1, 'stl' => 0, 'tov' => 1, 'blk' => 1, 'pf' => 3],
                ['name' => 'ASG Home I', 'pos' => 'SG', 'pid' => 4494, 'min' => 16, 'twoGM' => 6, 'twoGA' => 7, 'ftm' => 6, 'fta' => 6, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 1, 'drb' => 2, 'ast' => 2, 'stl' => 3, 'tov' => 1, 'blk' => 0, 'pf' => 3],
                ['name' => 'ASG Home J', 'pos' => 'PG', 'pid' => 4502, 'min' => 11, 'twoGM' => 2, 'twoGA' => 2, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 0, 'drb' => 3, 'ast' => 3, 'stl' => 0, 'tov' => 0, 'blk' => 0, 'pf' => 6],
                ['name' => 'ASG Home K', 'pos' => 'C',  'pid' => 4824, 'min' => 29, 'twoGM' => 8, 'twoGA' => 9, 'ftm' => 3, 'fta' => 4, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 2, 'drb' => 6, 'ast' => 0, 'stl' => 3, 'tov' => 2, 'blk' => 1, 'pf' => 5],
                ['name' => 'ASG Home L', 'pos' => 'PG', 'pid' => 4825, 'min' => 23, 'twoGM' => 3, 'twoGA' => 7, 'ftm' => 0, 'fta' => 0, 'threeGM' => 3, 'threeGA' => 6, 'orb' => 1, 'drb' => 4, 'ast' => 10, 'stl' => 2, 'tov' => 0, 'blk' => 2, 'pf' => 0],
            ],
        ];

        return ScoFileWriter::buildAllStarHeaderBlock($risingStars, $allStar);
    }
}
