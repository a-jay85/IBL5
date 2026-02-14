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
 * - Team entry parsing (28-team and fewer-team files)
 * - Season metadata parsing (year, team count)
 * - Full file parsing with real .lge data
 * - Synthetic data tests for format variations (round format, team count)
 */
class LgeFileParserTest extends TestCase
{
    private static function lgeFile(): string
    {
        return dirname(__DIR__, 2) . '/IBL5.lge';
    }

    private function requireLgeFile(): string
    {
        $path = self::lgeFile();
        if (!file_exists($path)) {
            $this->fail("Test .lge file not found at: {$path}");
        }
        return $path;
    }

    /**
     * Build a synthetic 64000-byte .lge file with the given parameters.
     *
     * @param list<string> $teamNames
     * @param list<string> $playoffFormats 4 round formats (e.g. ["3 of 5", "4 of 7", "4 of 7", "4 of 7"])
     */
    private static function buildSyntheticLge(
        array $teamNames,
        int $beginningYear,
        array $playoffFormats = ['4 of 7', '4 of 7', '4 of 7', '4 of 7'],
        int $qualifierCount = 8,
    ): string {
        $data = str_repeat(' ', LgeFileParser::FILE_SIZE);

        // Header: qualifier description (32 bytes at offset 0)
        $qualDesc = str_pad($qualifierCount . ' teams per conference', LgeFileParser::QUALIFIER_FIELD_SIZE);
        $data = substr_replace($data, $qualDesc, 0, LgeFileParser::QUALIFIER_FIELD_SIZE);

        // Playoff formats: 4 x 8 bytes at offset 32
        for ($i = 0; $i < 4; $i++) {
            $fmt = str_pad($playoffFormats[$i] ?? '', LgeFileParser::PLAYOFF_FIELD_SIZE);
            $offset = LgeFileParser::QUALIFIER_FIELD_SIZE + $i * LgeFileParser::PLAYOFF_FIELD_SIZE;
            $data = substr_replace($data, $fmt, $offset, LgeFileParser::PLAYOFF_FIELD_SIZE);
        }

        // Conferences: 2 x 16 bytes at offset 64
        $confOffset = LgeFileParser::QUALIFIER_FIELD_SIZE + 4 * LgeFileParser::PLAYOFF_FIELD_SIZE;
        $conferences = ['Eastern', 'Western'];
        for ($i = 0; $i < 2; $i++) {
            $conf = str_pad($conferences[$i], LgeFileParser::CONFERENCE_FIELD_SIZE);
            $data = substr_replace($data, $conf, $confOffset + $i * LgeFileParser::CONFERENCE_FIELD_SIZE, LgeFileParser::CONFERENCE_FIELD_SIZE);
        }

        // Divisions: 4 x 16 bytes at offset 96
        $divOffset = $confOffset + 2 * LgeFileParser::CONFERENCE_FIELD_SIZE;
        $divisions = ['Atlantic', 'Central', 'Midwest', 'Pacific'];
        for ($i = 0; $i < 4; $i++) {
            $div = str_pad($divisions[$i], LgeFileParser::DIVISION_FIELD_SIZE);
            $data = substr_replace($data, $div, $divOffset + $i * LgeFileParser::DIVISION_FIELD_SIZE, LgeFileParser::DIVISION_FIELD_SIZE);
        }

        // Team entries: 72 bytes each at offset 160
        $divAssignments = ['Atlantic', 'Central', 'Midwest', 'Pacific'];
        foreach ($teamNames as $idx => $name) {
            $entryOffset = LgeFileParser::TEAM_ENTRIES_OFFSET + $idx * LgeFileParser::TEAM_ENTRY_SIZE;
            $teamName = str_pad($name, LgeFileParser::TEAM_NAME_SIZE);
            $control = str_pad('Computer', LgeFileParser::TEAM_CONTROL_SIZE);
            $conference = $idx < intdiv(count($teamNames), 2) ? 'Eastern' : 'Western';
            $confField = str_pad($conference, LgeFileParser::TEAM_CONFERENCE_SIZE);
            $divField = str_pad($divAssignments[$idx % 4], LgeFileParser::TEAM_DIVISION_SIZE);

            $entry = $teamName . $control . $confField . $divField;
            $data = substr_replace($data, $entry, $entryOffset, LgeFileParser::TEAM_ENTRY_SIZE);
        }

        // Season info at offset 0x0F98: year(4) + season_number(4) + field1(2) + team_count(2)
        $seasonInfo = str_pad((string) $beginningYear, 4)
            . str_pad('1', 4)
            . str_pad('2', 2)
            . str_pad((string) count($teamNames), 2);
        $data = substr_replace($data, $seasonInfo, LgeFileParser::SEASON_INFO_OFFSET, strlen($seasonInfo));

        return $data;
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

    public function testParseTeamEntriesReturnsFewerTeams(): void
    {
        $teamNames = [
            'Celtics', 'Heat', 'Knicks', 'Nets', 'Magic', 'Bucks',
            'Bulls', 'Pelicans', 'Hawks', 'Hornets', 'Pacers', 'Raptors',
            'Jazz', 'Timberwolves', 'Nuggets', 'Thunder', 'Spurs', 'Trailblazers',
            'Clippers', 'Grizzlies', 'Lakers', 'Suns', 'Warriors', 'Kings',
        ];

        $data = self::buildSyntheticLge($teamNames, 1988);
        $teams = LgeFileParser::parseTeamEntries($data);

        $this->assertCount(24, $teams);
        $this->assertSame(1, $teams[0]['slot']);
        $this->assertSame('Celtics', $teams[0]['name']);
        $this->assertSame(24, $teams[23]['slot']);
        $this->assertSame('Kings', $teams[23]['name']);
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

    public function testParseSeasonMetadataFromSyntheticData(): void
    {
        $teamNames = array_fill(0, 24, 'Team');
        $data = self::buildSyntheticLge($teamNames, 1988);

        $season = LgeFileParser::parseSeasonMetadata($data);

        $this->assertSame(1988, $season['season_beginning_year']);
        $this->assertSame(1989, $season['season_ending_year']);
        $this->assertSame(24, $season['team_count']);
    }

    public function testParseHeaderDetectsRound1FormatChange(): void
    {
        $teamNames = array_fill(0, 24, 'Team');
        $data = self::buildSyntheticLge(
            $teamNames,
            1989,
            ['3 of 5', '4 of 7', '4 of 7', '4 of 7'],
        );

        $header = LgeFileParser::parseHeader($data);

        $this->assertSame('3 of 5', $header['playoff_formats'][0]);
        $this->assertSame('4 of 7', $header['playoff_formats'][1]);
        $this->assertSame('4 of 7', $header['playoff_formats'][2]);
        $this->assertSame('4 of 7', $header['playoff_formats'][3]);
    }
}
