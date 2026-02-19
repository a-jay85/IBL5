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
    private string $carFilePath;

    protected function setUp(): void
    {
        $this->carFilePath = dirname(__DIR__, 2) . '/IBL5.car';
    }

    public function testParseFileReturnsPlayerCount(): void
    {
        if (!file_exists($this->carFilePath)) {
            $this->markTestSkipped('.car file not available');
        }

        $result = CarFileParser::parseFile($this->carFilePath);

        $this->assertArrayHasKey('player_count', $result);
        $this->assertIsInt($result['player_count']);
        $this->assertGreaterThan(0, $result['player_count']);
    }

    public function testParseFileReturnsPlayers(): void
    {
        if (!file_exists($this->carFilePath)) {
            $this->markTestSkipped('.car file not available');
        }

        $result = CarFileParser::parseFile($this->carFilePath);

        $this->assertArrayHasKey('players', $result);
        $this->assertNotEmpty($result['players']);
    }

    public function testPlayerBlockHasExpectedStructure(): void
    {
        if (!file_exists($this->carFilePath)) {
            $this->markTestSkipped('.car file not available');
        }

        $result = CarFileParser::parseFile($this->carFilePath);
        $player = $result['players'][0];

        $this->assertArrayHasKey('block_index', $player);
        $this->assertArrayHasKey('jsb_id', $player);
        $this->assertArrayHasKey('name', $player);
        $this->assertArrayHasKey('season_count', $player);
        $this->assertArrayHasKey('seasons', $player);

        $this->assertIsInt($player['block_index']);
        $this->assertIsInt($player['jsb_id']);
        $this->assertIsString($player['name']);
        $this->assertNotSame('', $player['name']);
        $this->assertIsInt($player['season_count']);
        $this->assertGreaterThan(0, $player['season_count']);
    }

    public function testSeasonRecordHasExpectedFields(): void
    {
        if (!file_exists($this->carFilePath)) {
            $this->markTestSkipped('.car file not available');
        }

        $result = CarFileParser::parseFile($this->carFilePath);
        $player = $result['players'][0];
        $season = $player['seasons'][0];

        $expectedKeys = [
            'year', 'team', 'name', 'position', 'gp', 'min',
            'two_gm', 'two_ga', 'ftm', 'fta', 'three_gm', 'three_ga',
            'orb', 'drb', 'ast', 'stl', 'to', 'blk', 'pf',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $season, "Missing key: {$key}");
        }
    }

    public function testParsesJohnStarksBlock1(): void
    {
        if (!file_exists($this->carFilePath)) {
            $this->markTestSkipped('.car file not available');
        }

        // Block 1 = John Starks (from hex dump analysis)
        $data = file_get_contents($this->carFilePath);
        $this->assertIsString($data);

        $blockData = substr($data, CarFileParser::BLOCK_SIZE, CarFileParser::BLOCK_SIZE);
        $player = CarFileParser::parsePlayerBlock($blockData, 1);

        $this->assertNotNull($player);
        $this->assertSame('John Starks', $player['name']);
        $this->assertSame(27242, $player['jsb_id']);
        $this->assertGreaterThanOrEqual(1, $player['season_count']);

        // First season should be 1988
        $firstSeason = $player['seasons'][0];
        $this->assertSame(1988, $firstSeason['year']);
        $this->assertSame('Supersonics', $firstSeason['team']);
        $this->assertSame('SG', $firstSeason['position']);
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

    public function testSeasonRecordStatsAreNonNegative(): void
    {
        if (!file_exists($this->carFilePath)) {
            $this->markTestSkipped('.car file not available');
        }

        $result = CarFileParser::parseFile($this->carFilePath);

        // Check first 10 players
        $checked = 0;
        foreach ($result['players'] as $player) {
            foreach ($player['seasons'] as $season) {
                $this->assertGreaterThanOrEqual(0, $season['gp'], 'GP should be >= 0 for ' . $player['name']);
                $this->assertGreaterThanOrEqual(0, $season['min'], 'MIN should be >= 0 for ' . $player['name']);
                $this->assertGreaterThanOrEqual(0, $season['two_gm'], '2GM should be >= 0 for ' . $player['name']);
                $this->assertGreaterThanOrEqual(0, $season['ftm'], 'FTM should be >= 0 for ' . $player['name']);
                $this->assertGreaterThanOrEqual(0, $season['three_gm'], '3GM should be >= 0 for ' . $player['name']);
            }
            $checked++;
            if ($checked >= 10) {
                break;
            }
        }
    }
}
