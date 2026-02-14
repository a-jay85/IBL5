<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\LgeFileParser;

/**
 * LgeFileParserTest - Tests for the JSB .lge league file parser
 *
 * Covers:
 * - File validation (missing file, wrong size)
 * - Header parsing (qualifier count, playoff formats)
 * - Team entry parsing (28-team and 24-team files)
 * - Season metadata parsing (year, team count)
 * - Full file parsing with real .lge data
 */
class LgeFileParserTest extends TestCase
{
    private const OLDEST_LGE_FILE = '/scoNonFiles/IBL8889PostHEAT/IBL5.lge';
    private const EARLY_90S_LGE_FILE = '/scoNonFiles/IBL8990PostHEAT/IBL5.lge';

    private static function lgeFile(): string
    {
        return dirname(__DIR__, 2) . '/IBL5.lge';
    }

    private static function historicalLgeFile(string $relativePath): string
    {
        return dirname(__DIR__, 2) . $relativePath;
    }

    private function requireLgeFile(): string
    {
        $path = self::lgeFile();
        if (!file_exists($path)) {
            $this->fail("Test .lge file not found at: {$path}");
        }
        return $path;
    }

    private function requireHistoricalLgeFile(string $relativePath): string
    {
        $path = self::historicalLgeFile($relativePath);
        if (!file_exists($path)) {
            $this->fail("Test .lge file not found at: {$path}");
        }
        return $path;
    }

    // parseFile validation tests

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('League file not found');

        LgeFileParser::parseFile('/nonexistent/path/IBL5.lge');
    }

    public function testParseFileThrowsForInvalidSize(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'lge_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, str_repeat(' ', 100));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Invalid .lge file size');

            LgeFileParser::parseFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    // Full file parsing with real data

    public function testParseFileWithCurrentLge(): void
    {
        $path = $this->requireLgeFile();
        $result = LgeFileParser::parseFile($path);

        $this->assertArrayHasKey('header', $result);
        $this->assertArrayHasKey('teams', $result);
        $this->assertArrayHasKey('season', $result);

        $this->assertSame(28, count($result['teams']));
        $this->assertSame(28, $result['season']['team_count']);
    }

    // Header parsing tests

    public function testParseHeaderExtractsPlayoffFormat(): void
    {
        $path = $this->requireLgeFile();
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $header = LgeFileParser::parseHeader($data);

        $this->assertCount(4, $header['playoff_formats']);
        $this->assertSame('4 of 7', $header['playoff_formats'][0]);
        $this->assertSame('4 of 7', $header['playoff_formats'][1]);
        $this->assertSame('4 of 7', $header['playoff_formats'][2]);
        $this->assertSame('4 of 7', $header['playoff_formats'][3]);
    }

    public function testParseHeaderExtractsQualifierCount(): void
    {
        $path = $this->requireLgeFile();
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $header = LgeFileParser::parseHeader($data);

        $this->assertSame(8, $header['qualifier_count']);
    }

    public function testParseHeaderExtractsConferences(): void
    {
        $path = $this->requireLgeFile();
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $header = LgeFileParser::parseHeader($data);

        $this->assertSame(['Eastern', 'Western'], $header['conferences']);
    }

    public function testParseHeaderExtractsDivisions(): void
    {
        $path = $this->requireLgeFile();
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $header = LgeFileParser::parseHeader($data);

        $this->assertSame(['Atlantic', 'Central', 'Midwest', 'Pacific'], $header['divisions']);
    }

    // Team entry parsing tests

    public function testParseTeamEntriesReturns28Teams(): void
    {
        $path = $this->requireLgeFile();
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $teams = LgeFileParser::parseTeamEntries($data);

        $this->assertCount(28, $teams);
    }

    public function testParseTeamEntriesReturns24Teams(): void
    {
        $path = $this->requireHistoricalLgeFile(self::OLDEST_LGE_FILE);
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $teams = LgeFileParser::parseTeamEntries($data);

        $this->assertCount(24, $teams);
    }

    public function testParseTeamEntriesHaveCorrectStructure(): void
    {
        $path = $this->requireLgeFile();
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $teams = LgeFileParser::parseTeamEntries($data);
        $firstTeam = $teams[0];

        $this->assertSame(1, $firstTeam['slot']);
        $this->assertSame('Celtics', $firstTeam['name']);
        $this->assertSame('Eastern', $firstTeam['conference']);
        $this->assertSame('Atlantic', $firstTeam['division']);
    }

    public function testParseTeamEntriesDetectsHumanControlled(): void
    {
        $path = $this->requireLgeFile();
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $teams = LgeFileParser::parseTeamEntries($data);

        $humanTeams = array_filter($teams, static fn (array $t): bool => $t['control'] === 'Human');
        $this->assertCount(4, $humanTeams);
    }

    // Season metadata tests

    public function testParseSeasonMetadataYear(): void
    {
        $path = $this->requireLgeFile();
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $season = LgeFileParser::parseSeasonMetadata($data);

        $this->assertSame(28, $season['team_count']);
        $this->assertSame($season['season_beginning_year'] + 1, $season['season_ending_year']);
    }

    public function testParseSeasonMetadataOldestFile(): void
    {
        $path = $this->requireHistoricalLgeFile(self::OLDEST_LGE_FILE);
        $data = file_get_contents($path);
        $this->assertIsString($data);

        $season = LgeFileParser::parseSeasonMetadata($data);

        $this->assertSame(1988, $season['season_beginning_year']);
        $this->assertSame(1989, $season['season_ending_year']);
        $this->assertSame(24, $season['team_count']);
    }

    public function testParseFileDetectsRound1FormatChange(): void
    {
        $path = $this->requireHistoricalLgeFile(self::EARLY_90S_LGE_FILE);
        $result = LgeFileParser::parseFile($path);

        // 1989-90 season has "3 of 5" for round 1
        $this->assertSame('3 of 5', $result['header']['playoff_formats'][0]);
        $this->assertSame('4 of 7', $result['header']['playoff_formats'][1]);
    }
}
