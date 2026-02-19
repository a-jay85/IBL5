<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\CarFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\CarFileParser
 */
class CarFileParserTest extends TestCase
{
    /**
     * Build a minimal .car file with a header block and one player block.
     *
     * @param int $playerCount Player count for the header
     * @param string $playerBlock Raw 2,500-byte player block (or empty for header-only)
     */
    private function buildCarFile(int $playerCount, string $playerBlock = ''): string
    {
        // Block 0: header — player count right-justified in first 4 bytes
        $header = str_pad((string) $playerCount, 4, ' ', STR_PAD_LEFT);
        $header = str_pad($header, CarFileParser::BLOCK_SIZE, ' ');

        return $header . $playerBlock;
    }

    /**
     * Build a player block with header and one season record.
     *
     * @param int $seasonCount Number of seasons
     * @param int $jsbId JSB internal player ID
     * @param string $name Player name (max 16 chars)
     * @param string $seasonRecord 100-byte season record
     */
    private function buildPlayerBlock(int $seasonCount, int $jsbId, string $name, string $seasonRecord): string
    {
        // Header: 3 (season count) + 5 (jsb id) + 16 (name) = 24 bytes
        $header = str_pad((string) $seasonCount, 3, ' ', STR_PAD_LEFT);
        $header .= str_pad((string) $jsbId, 5, ' ', STR_PAD_LEFT);
        $header .= str_pad($name, 16);

        $block = $header . $seasonRecord;
        return str_pad($block, CarFileParser::BLOCK_SIZE, ' ');
    }

    /**
     * Build a 100-byte season record with known stat values.
     */
    private function buildSeasonRecord(
        int $year = 2006,
        string $team = 'Lakers',
        string $name = 'Test Player',
        string $position = 'SF',
        int $gp = 82,
        int $min = 3200,
        int $twoGm = 400,
        int $twoGa = 800,
        int $ftm = 200,
        int $fta = 250,
        int $threeGm = 100,
        int $threeGa = 300,
        int $orb = 80,
        int $drb = 320,
        int $ast = 250,
        int $stl = 100,
        int $to = 150,
        int $blk = 40,
        int $pf = 200,
    ): string {
        $record = str_pad((string) $year, 4);                          // 0-3
        $record .= str_pad($team, 16);                                  // 4-19
        $record .= str_pad($name, 16);                                  // 20-35
        $record .= str_pad($position, 2);                               // 36-37
        $record .= '0 ';                                                // 38-39 (depth chart flag + space)
        $record .= str_pad((string) $gp, 2, ' ', STR_PAD_LEFT);        // 40-41
        $record .= str_pad((string) $min, 4, ' ', STR_PAD_LEFT);       // 42-45
        $record .= str_pad((string) $twoGm, 4, ' ', STR_PAD_LEFT);     // 46-49
        $record .= str_pad((string) $twoGa, 4, ' ', STR_PAD_LEFT);     // 50-53
        $record .= str_pad((string) $ftm, 4, ' ', STR_PAD_LEFT);       // 54-57
        $record .= str_pad((string) $fta, 4, ' ', STR_PAD_LEFT);       // 58-61
        $record .= str_pad((string) $threeGm, 4, ' ', STR_PAD_LEFT);   // 62-65
        $record .= str_pad((string) $threeGa, 4, ' ', STR_PAD_LEFT);   // 66-69
        $record .= str_pad((string) $orb, 4, ' ', STR_PAD_LEFT);       // 70-73
        $record .= str_pad((string) $drb, 4, ' ', STR_PAD_LEFT);       // 74-77
        $record .= str_pad((string) $ast, 4, ' ', STR_PAD_LEFT);       // 78-81
        $record .= str_pad((string) $stl, 4, ' ', STR_PAD_LEFT);       // 82-85
        $record .= str_pad((string) $to, 4, ' ', STR_PAD_LEFT);        // 86-89
        $record .= str_pad((string) $blk, 4, ' ', STR_PAD_LEFT);       // 90-93
        $record .= str_pad((string) $pf, 4, ' ', STR_PAD_LEFT);        // 94-97
        $record .= '  ';                                                // 98-99 trailing padding

        return $record;
    }

    public function testParseFileReturnsPlayerCount(): void
    {
        $seasonRecord = $this->buildSeasonRecord();
        $playerBlock = $this->buildPlayerBlock(1, 12345, 'Test Player', $seasonRecord);
        $carData = $this->buildCarFile(1, $playerBlock);

        $tmpFile = tempnam(sys_get_temp_dir(), 'car_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $carData);

        try {
            $result = CarFileParser::parseFile($tmpFile);
            $this->assertSame(1, $result['player_count']);
            $this->assertCount(1, $result['players']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testPlayerBlockHasExpectedStructure(): void
    {
        $seasonRecord = $this->buildSeasonRecord();
        $playerBlock = $this->buildPlayerBlock(1, 27242, 'John Starks', $seasonRecord);
        $carData = $this->buildCarFile(1, $playerBlock);

        $tmpFile = tempnam(sys_get_temp_dir(), 'car_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $carData);

        try {
            $result = CarFileParser::parseFile($tmpFile);
            $player = $result['players'][0];

            $this->assertArrayHasKey('block_index', $player);
            $this->assertArrayHasKey('jsb_id', $player);
            $this->assertArrayHasKey('name', $player);
            $this->assertArrayHasKey('season_count', $player);
            $this->assertArrayHasKey('seasons', $player);

            $this->assertSame(1, $player['block_index']);
            $this->assertSame(27242, $player['jsb_id']);
            $this->assertSame('John Starks', $player['name']);
            $this->assertSame(1, $player['season_count']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testSeasonRecordHasExpectedFields(): void
    {
        $seasonRecord = $this->buildSeasonRecord(year: 1988, team: 'Supersonics', name: 'John Starks', position: 'SG');
        $playerBlock = $this->buildPlayerBlock(1, 27242, 'John Starks', $seasonRecord);

        $player = CarFileParser::parsePlayerBlock($playerBlock, 1);
        $this->assertNotNull($player);

        $season = $player['seasons'][0];

        $expectedKeys = [
            'year', 'team', 'name', 'position', 'gp', 'min',
            'two_gm', 'two_ga', 'ftm', 'fta', 'three_gm', 'three_ga',
            'orb', 'drb', 'ast', 'stl', 'to', 'blk', 'pf',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $season, "Missing key: {$key}");
        }

        $this->assertSame(1988, $season['year']);
        $this->assertSame('Supersonics', $season['team']);
        $this->assertSame('SG', $season['position']);
    }

    public function testSeasonRecordParsesCorrectStats(): void
    {
        $seasonRecord = $this->buildSeasonRecord(
            gp: 80,
            min: 2546,
            twoGm: 251,
            twoGa: 703,
            ftm: 161,
            fta: 205,
            threeGm: 139,
            threeGa: 380,
            orb: 38,
            drb: 179,
            ast: 385,
            stl: 139,
            to: 198,
            blk: 12,
            pf: 189,
        );

        $parsed = CarFileParser::parseSeasonRecord($seasonRecord);

        $this->assertSame(80, $parsed['gp']);
        $this->assertSame(2546, $parsed['min']);
        $this->assertSame(251, $parsed['two_gm']);
        $this->assertSame(703, $parsed['two_ga']);
        $this->assertSame(161, $parsed['ftm']);
        $this->assertSame(205, $parsed['fta']);
        $this->assertSame(139, $parsed['three_gm']);
        $this->assertSame(380, $parsed['three_ga']);
        $this->assertSame(38, $parsed['orb']);
        $this->assertSame(179, $parsed['drb']);
        $this->assertSame(385, $parsed['ast']);
        $this->assertSame(139, $parsed['stl']);
        $this->assertSame(198, $parsed['to']);
        $this->assertSame(12, $parsed['blk']);
        $this->assertSame(189, $parsed['pf']);
    }

    public function testConvertToHistFormatAppliesCorrectConversions(): void
    {
        $season = [
            'year' => 2006,
            'team' => 'Lakers',
            'name' => 'Test Player',
            'position' => 'SF',
            'gp' => 82,
            'min' => 3200,
            'two_gm' => 400,
            'two_ga' => 800,
            'ftm' => 200,
            'fta' => 250,
            'three_gm' => 100,
            'three_ga' => 300,
            'orb' => 80,
            'drb' => 320,
            'ast' => 250,
            'stl' => 100,
            'to' => 150,
            'blk' => 40,
            'pf' => 200,
        ];

        $hist = CarFileParser::convertToHistFormat($season);

        // fgm = two_gm + three_gm = 400 + 100 = 500
        $this->assertSame(500, $hist['fgm']);

        // fga = two_ga + three_ga = 800 + 300 = 1100
        $this->assertSame(1100, $hist['fga']);

        // reb = orb + drb = 80 + 320 = 400
        $this->assertSame(400, $hist['reb']);

        // pts = two_gm * 2 + ftm + three_gm * 3 = 800 + 200 + 300 = 1300
        $this->assertSame(1300, $hist['pts']);

        // Direct mappings
        $this->assertSame(100, $hist['tgm']);
        $this->assertSame(300, $hist['tga']);
        $this->assertSame(80, $hist['orb']);
        $this->assertSame(150, $hist['tvr']);
    }

    public function testParsePlayerBlockReturnsNullForEmptyBlock(): void
    {
        $emptyBlock = str_repeat(' ', CarFileParser::BLOCK_SIZE);
        $result = CarFileParser::parsePlayerBlock($emptyBlock, 999);

        $this->assertNull($result);
    }

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CAR file not found');

        CarFileParser::parseFile('/nonexistent/file.car');
    }

    public function testParseFileThrowsForTooSmallFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'car_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, str_repeat(' ', 100));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('too small');
            CarFileParser::parseFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testMultipleSeasonsPerPlayer(): void
    {
        $season1 = $this->buildSeasonRecord(year: 1988, team: 'Supersonics', gp: 80);
        $season2 = $this->buildSeasonRecord(year: 1989, team: 'Pelicans', gp: 82);

        // Build a player block with 2 seasons
        $header = str_pad('2', 3, ' ', STR_PAD_LEFT);
        $header .= str_pad('27242', 5, ' ', STR_PAD_LEFT);
        $header .= str_pad('John Starks', 16);
        $block = str_pad($header . $season1 . $season2, CarFileParser::BLOCK_SIZE, ' ');

        $player = CarFileParser::parsePlayerBlock($block, 1);
        $this->assertNotNull($player);
        $this->assertSame(2, $player['season_count']);
        $this->assertCount(2, $player['seasons']);
        $this->assertSame(1988, $player['seasons'][0]['year']);
        $this->assertSame(1989, $player['seasons'][1]['year']);
        $this->assertSame('Supersonics', $player['seasons'][0]['team']);
        $this->assertSame('Pelicans', $player['seasons'][1]['team']);
    }
}
