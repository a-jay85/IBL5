<?php

declare(strict_types=1);

namespace Utilities;

/**
 * LgeFileParser - Parse JSB .lge league configuration files
 *
 * The .lge format is a fixed-size binary/ASCII file (64,000 bytes):
 * - 640 rows of 100 bytes each, space-padded (0x20)
 *
 * Sections:
 * 1. Header (offset 0x0000, 160 bytes)
 *    - Qualifier description (32 bytes): e.g. "8 teams per conference"
 *    - Playoff formats: 4 x 8-byte fields (32 bytes)
 *    - Conference names: 2 x 16-byte fields (32 bytes)
 *    - Division names: 4 x 16-byte fields (64 bytes)
 *
 * 2. Team Entries (offset 0x00A0, up to 32 x 72 bytes)
 *    Each 72-byte record: name(32) + control(8) + conference(16) + division(16)
 *
 * 3. Season Info (offset 0x0F98, 12 bytes)
 *    Year(4) + season_number(4) + field1(2) + team_count(2)
 */
class LgeFileParser
{
    public const FILE_SIZE = 64000;
    public const ROW_SIZE = 100;
    public const HEADER_SIZE = 160;
    public const TEAM_ENTRY_SIZE = 72;
    public const TEAM_NAME_SIZE = 32;
    public const TEAM_CONTROL_SIZE = 8;
    public const TEAM_CONFERENCE_SIZE = 16;
    public const TEAM_DIVISION_SIZE = 16;
    public const MAX_TEAM_SLOTS = 32;
    public const TEAM_ENTRIES_OFFSET = 160;
    public const QUALIFIER_FIELD_SIZE = 32;
    public const PLAYOFF_FIELD_SIZE = 8;
    public const CONFERENCE_FIELD_SIZE = 16;
    public const DIVISION_FIELD_SIZE = 16;
    public const SEASON_INFO_OFFSET = 0x0F98;

    /**
     * Parse a .lge file and return the complete league configuration.
     *
     * @return array{
     *     header: array{qualifier_count: int, playoff_formats: list<string>, conferences: list<string>, divisions: list<string>},
     *     teams: list<array{slot: int, name: string, control: string, conference: string, division: string}>,
     *     season: array{season_beginning_year: int, season_ending_year: int, phase: string, team_count: int}
     * }
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("League file not found: {$filePath}");
        }

        $fileSize = filesize($filePath);
        if ($fileSize !== self::FILE_SIZE) {
            throw new \RuntimeException(
                'Invalid .lge file size: expected ' . self::FILE_SIZE . " bytes, got {$fileSize}"
            );
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new \RuntimeException("Failed to read league file: {$filePath}");
        }

        return [
            'header' => self::parseHeader($data),
            'teams' => self::parseTeamEntries($data),
            'season' => self::parseSeasonMetadata($data),
        ];
    }

    /**
     * Parse the 160-byte header section.
     *
     * @return array{qualifier_count: int, playoff_formats: list<string>, conferences: list<string>, divisions: list<string>}
     */
    public static function parseHeader(string $data): array
    {
        $qualifierDesc = self::readField($data, 0, self::QUALIFIER_FIELD_SIZE);

        $qualifierCount = 0;
        if (preg_match('/(\d+)\s+teams?\s+per\s+conference/i', $qualifierDesc, $matches) === 1) {
            $qualifierCount = (int) $matches[1];
        }

        $playoffFormats = [];
        for ($i = 0; $i < 4; $i++) {
            $format = self::readField($data, self::QUALIFIER_FIELD_SIZE + $i * self::PLAYOFF_FIELD_SIZE, self::PLAYOFF_FIELD_SIZE);
            if ($format !== '') {
                $playoffFormats[] = $format;
            }
        }

        $conferences = [];
        $confOffset = self::QUALIFIER_FIELD_SIZE + 4 * self::PLAYOFF_FIELD_SIZE;
        for ($i = 0; $i < 2; $i++) {
            $conf = self::readField($data, $confOffset + $i * self::CONFERENCE_FIELD_SIZE, self::CONFERENCE_FIELD_SIZE);
            if ($conf !== '') {
                $conferences[] = $conf;
            }
        }

        $divisions = [];
        $divOffset = $confOffset + 2 * self::CONFERENCE_FIELD_SIZE;
        for ($i = 0; $i < 4; $i++) {
            $div = self::readField($data, $divOffset + $i * self::DIVISION_FIELD_SIZE, self::DIVISION_FIELD_SIZE);
            if ($div !== '') {
                $divisions[] = $div;
            }
        }

        return [
            'qualifier_count' => $qualifierCount,
            'playoff_formats' => $playoffFormats,
            'conferences' => $conferences,
            'divisions' => $divisions,
        ];
    }

    /**
     * Parse up to 32 team entry slots starting at offset 160.
     *
     * @return list<array{slot: int, name: string, control: string, conference: string, division: string}>
     */
    public static function parseTeamEntries(string $data): array
    {
        $teams = [];

        for ($i = 0; $i < self::MAX_TEAM_SLOTS; $i++) {
            $offset = self::TEAM_ENTRIES_OFFSET + $i * self::TEAM_ENTRY_SIZE;
            if ($offset + self::TEAM_ENTRY_SIZE > strlen($data)) {
                break;
            }

            $name = self::readField($data, $offset, self::TEAM_NAME_SIZE);
            $control = self::readField($data, $offset + self::TEAM_NAME_SIZE, self::TEAM_CONTROL_SIZE);
            $conference = self::readField($data, $offset + self::TEAM_NAME_SIZE + self::TEAM_CONTROL_SIZE, self::TEAM_CONFERENCE_SIZE);
            $division = self::readField(
                $data,
                $offset + self::TEAM_NAME_SIZE + self::TEAM_CONTROL_SIZE + self::TEAM_CONFERENCE_SIZE,
                self::TEAM_DIVISION_SIZE,
            );

            if ($name === '' && $conference === '') {
                continue;
            }

            if ($name === '') {
                continue;
            }

            $teams[] = [
                'slot' => $i + 1,
                'name' => $name,
                'control' => $control !== '' ? $control : 'Human',
                'conference' => $conference,
                'division' => $division,
            ];
        }

        return $teams;
    }

    /**
     * Parse season metadata from offset 0x0F98.
     *
     * Format: year(4) + season_number(4) + field1(2) + team_count(2)
     * Season ending year = beginning year + 1.
     *
     * @return array{season_beginning_year: int, season_ending_year: int, phase: string, team_count: int}
     */
    public static function parseSeasonMetadata(string $data): array
    {
        $yearStr = self::readField($data, self::SEASON_INFO_OFFSET, 4);
        $seasonNumber = self::readField($data, self::SEASON_INFO_OFFSET + 4, 4);
        $teamCountStr = self::readField($data, self::SEASON_INFO_OFFSET + 10, 2);

        $beginningYear = (int) $yearStr;
        $teamCount = (int) $teamCountStr;

        $phase = match (trim($seasonNumber)) {
            '1' => 'Regular Season',
            '2' => 'Playoffs',
            default => 'Unknown',
        };

        return [
            'season_beginning_year' => $beginningYear,
            'season_ending_year' => $beginningYear + 1,
            'phase' => $phase,
            'team_count' => $teamCount,
        ];
    }

    /**
     * Read a fixed-width ASCII field, stripping trailing spaces.
     */
    private static function readField(string $data, int $offset, int $length): string
    {
        if ($offset + $length > strlen($data)) {
            return '';
        }

        return rtrim(substr($data, $offset, $length));
    }
}
