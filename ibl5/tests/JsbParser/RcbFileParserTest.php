<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\RcbFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\RcbFileParser
 */
class RcbFileParserTest extends TestCase
{
    /**
     * Build a 51-character all-time single-season entry.
     */
    private function buildAlltimeSingleSeasonEntry(
        string $name = 'Stephen Curry',
        int $blockId = 3851,
        int $statRaw = 3611,
        int $teamId = 19,
        int $year = 2005,
    ): string {
        $entry = str_pad($name, 33, ' ', STR_PAD_LEFT);        // 0-32
        $entry .= str_pad((string) $blockId, 5, ' ', STR_PAD_LEFT); // 33-37
        $entry .= str_pad((string) $statRaw, 6, ' ', STR_PAD_LEFT); // 38-43
        $entry .= str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT);  // 44-45
        $entry .= str_pad((string) $year, 4);                       // 46-49
        $entry .= ' ';                                               // 50

        return $entry;
    }

    /**
     * Build a 51-character all-time career entry.
     */
    private function buildAlltimeCareerEntry(
        string $name = 'Michael Jordan',
        int $blockId = 1230,
        int $careerTotal = 35042,
        int $statRaw = 3047,
        int $teamId = 6,
    ): string {
        $entry = str_pad($name, 33, ' ', STR_PAD_LEFT);             // 0-32
        $entry .= str_pad((string) $blockId, 5, ' ', STR_PAD_LEFT); // 33-37
        $entry .= str_pad((string) $careerTotal, 5, ' ', STR_PAD_LEFT); // 38-42
        $entry .= str_pad((string) $statRaw, 6, ' ', STR_PAD_LEFT); // 43-48
        $entry .= str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT);  // 49-50

        return $entry;
    }

    /**
     * Build a 90-character current season entry (45-char player + 45-char team).
     */
    private function buildCurrentSeasonEntry(
        string $position = 'PG',
        string $name = 'Stephen Curry',
        int $blockId = 3851,
        int $statValue = 73,
        int $year = 2006,
    ): string {
        $posName = $position . ' ' . $name;
        $playerPart = str_pad($posName, 33, ' ', STR_PAD_LEFT);        // 0-32
        $playerPart .= str_pad((string) $blockId, 5, ' ', STR_PAD_LEFT); // 33-37
        $playerPart .= str_pad((string) $statValue, 3, ' ', STR_PAD_LEFT); // 38-40
        $playerPart .= str_pad((string) $year, 4);                       // 41-44

        $teamPart = str_repeat(' ', 45);

        return $playerPart . $teamPart;
    }

    /**
     * Build a minimal .rcb file for testing.
     *
     * @param list<string> $alltimeLines 50 lines for all-time section
     * @param list<string> $seasonLines 33 lines for current season section
     */
    private function buildRcbFile(array $alltimeLines, array $seasonLines): string
    {
        // Pad to exactly 50 all-time lines and 33 season lines
        while (count($alltimeLines) < 50) {
            $alltimeLines[] = str_repeat(' ', RcbFileParser::ENTRIES_PER_ALLTIME_LINE * RcbFileParser::ALLTIME_ENTRY_SIZE);
        }
        while (count($seasonLines) < 33) {
            $seasonLines[] = str_repeat(' ', RcbFileParser::ENTRIES_PER_SEASON_LINE * RcbFileParser::SEASON_ENTRY_SIZE);
        }

        // Add trailing lines (83-85)
        $trailing = [
            str_repeat(' ', 110),
            str_repeat(' ', 56),
            str_repeat(' ', 22),
        ];

        $allLines = array_merge($alltimeLines, $seasonLines, $trailing);
        return implode("\r\n", $allLines) . "\r\n";
    }

    /**
     * Build an all-time line with entries placed at specific group/entry positions.
     *
     * @param list<array{group: int, entry: int, data: string}> $entries
     */
    private function buildAlltimeLine(array $entries): string
    {
        $totalChars = RcbFileParser::ENTRIES_PER_ALLTIME_LINE * RcbFileParser::ALLTIME_ENTRY_SIZE;
        $line = str_repeat(' ', $totalChars);

        foreach ($entries as $entrySpec) {
            $globalIdx = $entrySpec['group'] * RcbFileParser::ENTRIES_PER_GROUP + $entrySpec['entry'];
            $offset = $globalIdx * RcbFileParser::ALLTIME_ENTRY_SIZE;
            $line = substr_replace($line, $entrySpec['data'], $offset, strlen($entrySpec['data']));
        }

        return $line;
    }

    public function testParseAlltimeSingleSeasonEntry(): void
    {
        $entry = $this->buildAlltimeSingleSeasonEntry('Stephen Curry', 3851, 3611, 19, 2005);
        $result = RcbFileParser::parseAlltimeSingleSeasonEntry($entry);

        $this->assertNotNull($result);
        $this->assertSame('Stephen Curry', $result['player_name']);
        $this->assertSame(3851, $result['car_block_id']);
        $this->assertSame(3611, $result['stat_raw']);
        $this->assertSame(19, $result['team_of_record']);
        $this->assertSame(2005, $result['season_year']);
    }

    public function testParseAlltimeCareerEntry(): void
    {
        $entry = $this->buildAlltimeCareerEntry('Michael Jordan', 1230, 35042, 3047, 6);
        $result = RcbFileParser::parseAlltimeCareerEntry($entry);

        $this->assertNotNull($result);
        $this->assertSame('Michael Jordan', $result['player_name']);
        $this->assertSame(1230, $result['car_block_id']);
        $this->assertSame(35042, $result['career_total']);
        $this->assertSame(3047, $result['stat_raw']);
        $this->assertSame(6, $result['team_of_record']);
    }

    public function testParseCurrentSeasonEntry(): void
    {
        $entry = $this->buildCurrentSeasonEntry('PG', 'Stephen Curry', 3851, 73, 2006);
        $result = RcbFileParser::parseCurrentSeasonEntry($entry);

        $this->assertNotNull($result);
        $this->assertSame('Stephen Curry', $result['player_name']);
        $this->assertSame('PG', $result['player_position']);
        $this->assertSame(3851, $result['car_block_id']);
        $this->assertSame(73, $result['stat_value']);
        $this->assertSame(2006, $result['season_year']);
    }

    public function testParseEmptySingleSeasonEntryReturnsNull(): void
    {
        $entry = str_repeat(' ', RcbFileParser::ALLTIME_ENTRY_SIZE);
        $this->assertNull(RcbFileParser::parseAlltimeSingleSeasonEntry($entry));
    }

    public function testParseEmptyCareerEntryReturnsNull(): void
    {
        $entry = str_repeat(' ', RcbFileParser::ALLTIME_ENTRY_SIZE);
        $this->assertNull(RcbFileParser::parseAlltimeCareerEntry($entry));
    }

    public function testParseEmptyCurrentSeasonEntryReturnsNull(): void
    {
        $entry = str_repeat(' ', RcbFileParser::SEASON_ENTRY_SIZE);
        $this->assertNull(RcbFileParser::parseCurrentSeasonEntry($entry));
    }

    public function testSingleSeasonEntryWithZeroStatReturnsNull(): void
    {
        $entry = $this->buildAlltimeSingleSeasonEntry('Karl Malone', 77042, 0, 5, 1998);
        $this->assertNull(RcbFileParser::parseAlltimeSingleSeasonEntry($entry));
    }

    public function testSingleSeasonEntryWithDigitsInNameReturnsNull(): void
    {
        $entry = $this->buildAlltimeSingleSeasonEntry('12191989 121981', 77042, 96, 5, 1998);
        $this->assertNull(RcbFileParser::parseAlltimeSingleSeasonEntry($entry));
    }

    public function testCareerEntryWithZeroStatReturnsNull(): void
    {
        $entry = $this->buildAlltimeCareerEntry('Karl Malone', 77042, 500, 0, 5);
        $this->assertNull(RcbFileParser::parseAlltimeCareerEntry($entry));
    }

    public function testCareerEntryWithDigitsInNameReturnsNull(): void
    {
        $entry = $this->buildAlltimeCareerEntry('0 0 0 0 02278131', 77042, 500, 96, 5);
        $this->assertNull(RcbFileParser::parseAlltimeCareerEntry($entry));
    }

    public function testCurrentSeasonEntryWithZeroStatReturnsNull(): void
    {
        $entry = $this->buildCurrentSeasonEntry('PG', 'Karl Malone', 3851, 0, 2006);
        $this->assertNull(RcbFileParser::parseCurrentSeasonEntry($entry));
    }

    public function testCurrentSeasonEntryWithDigitsInNameReturnsNull(): void
    {
        $entry = $this->buildCurrentSeasonEntry('PG', '12191989 Player', 3851, 73, 2006);
        $this->assertNull(RcbFileParser::parseCurrentSeasonEntry($entry));
    }

    public function testValidEntryWithAlphabeticNameAndNonzeroStatPasses(): void
    {
        $entry = $this->buildAlltimeSingleSeasonEntry('Karl Malone', 500, 2921, 14, 1989);
        $result = RcbFileParser::parseAlltimeSingleSeasonEntry($entry);

        $this->assertNotNull($result);
        $this->assertSame('Karl Malone', $result['player_name']);
        $this->assertSame(2921, $result['stat_raw']);
    }

    public function testGarbageEntryWithNonzeroCarBlockIdAndZeroStatReturnsNull(): void
    {
        // Previously this passed because carBlockId !== 0
        $entry = $this->buildAlltimeSingleSeasonEntry('Garbage Name', 77042, 0, 5, 1998);
        $this->assertNull(RcbFileParser::parseAlltimeSingleSeasonEntry($entry));
    }

    public function testDecodeStatValueForAverage(): void
    {
        // PPG: 3611 → 36.11
        $this->assertSame(36.11, RcbFileParser::decodeStatValue(3611, 'ppg'));

        // RPG: 1253 → 12.53
        $this->assertSame(12.53, RcbFileParser::decodeStatValue(1253, 'rpg'));
    }

    public function testDecodeStatValueForPercentage(): void
    {
        // FG%: 6708 → 0.6708
        $this->assertSame(0.6708, RcbFileParser::decodeStatValue(6708, 'fg_pct'));

        // FT%: 9250 → 0.9250
        $this->assertSame(0.925, RcbFileParser::decodeStatValue(9250, 'ft_pct'));

        // 3P%: 4500 → 0.4500
        $this->assertSame(0.45, RcbFileParser::decodeStatValue(4500, 'three_pct'));
    }

    public function testParseFileWithLeagueWideSingleSeasonRecord(): void
    {
        // Place a single-season PPG record at group 0, entry 0 (line 0 = rank #1)
        $singleSeasonEntry = $this->buildAlltimeSingleSeasonEntry('Stephen Curry', 3851, 3611, 19, 2005);
        $line0 = $this->buildAlltimeLine([
            ['group' => 0, 'entry' => 0, 'data' => $singleSeasonEntry],
        ]);

        $file = $this->buildRcbFile([$line0], []);
        $tmpFile = tempnam(sys_get_temp_dir(), 'rcb_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $file);

        try {
            $result = RcbFileParser::parseFile($tmpFile);
            $alltime = $result['alltime'];

            // Should have at least 1 record
            $this->assertNotSame([], $alltime);

            // Find the league PPG record
            $ppgRecords = array_filter($alltime, static fn (array $r): bool => $r['stat_category'] === 'ppg' && $r['scope'] === 'league');

            $this->assertCount(1, $ppgRecords);
            $record = array_values($ppgRecords)[0];

            $this->assertSame('league', $record['scope']);
            $this->assertSame(0, $record['team_id']);
            $this->assertSame('single_season', $record['record_type']);
            $this->assertSame('ppg', $record['stat_category']);
            $this->assertSame(1, $record['ranking']);
            $this->assertSame('Stephen Curry', $record['player_name']);
            $this->assertSame(3851, $record['car_block_id']);
            $this->assertSame(36.11, $record['stat_value']);
            $this->assertSame(3611, $record['stat_raw']);
            $this->assertSame(19, $record['team_of_record']);
            $this->assertSame(2005, $record['season_year']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileWithLeagueCareerRecord(): void
    {
        // Place a career PTS record at group 0, entry 1 (line 0 = rank #1)
        $careerEntry = $this->buildAlltimeCareerEntry('Michael Jordan', 1230, 35042, 3047, 6);
        $line0 = $this->buildAlltimeLine([
            ['group' => 0, 'entry' => 1, 'data' => $careerEntry],
        ]);

        $file = $this->buildRcbFile([$line0], []);
        $tmpFile = tempnam(sys_get_temp_dir(), 'rcb_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $file);

        try {
            $result = RcbFileParser::parseFile($tmpFile);
            $alltime = $result['alltime'];

            $ptsRecords = array_filter($alltime, static fn (array $r): bool => $r['stat_category'] === 'pts' && $r['scope'] === 'league');

            $this->assertCount(1, $ptsRecords);
            $record = array_values($ptsRecords)[0];

            $this->assertSame('league', $record['scope']);
            $this->assertSame('career', $record['record_type']);
            $this->assertSame('pts', $record['stat_category']);
            $this->assertSame(1, $record['ranking']);
            $this->assertSame('Michael Jordan', $record['player_name']);
            $this->assertSame(30.47, $record['stat_value']);
            $this->assertSame(35042, $record['career_total']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileWithTeamSingleSeasonRecord(): void
    {
        // Place a team single-season PPG record at group 2 (Heat), entry 0
        $entry = $this->buildAlltimeSingleSeasonEntry('Dwyane Wade', 500, 2935, 2, 2004);
        $line0 = $this->buildAlltimeLine([
            ['group' => 2, 'entry' => 0, 'data' => $entry],
        ]);

        $file = $this->buildRcbFile([$line0], []);
        $tmpFile = tempnam(sys_get_temp_dir(), 'rcb_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $file);

        try {
            $result = RcbFileParser::parseFile($tmpFile);
            $alltime = $result['alltime'];

            $teamRecords = array_filter($alltime, static fn (array $r): bool => $r['scope'] === 'team' && $r['team_id'] === 2);

            $this->assertNotSame([], $teamRecords);
            $record = array_values($teamRecords)[0];

            $this->assertSame('team', $record['scope']);
            $this->assertSame(2, $record['team_id']);
            $this->assertSame('single_season', $record['record_type']);
            $this->assertSame('ppg', $record['stat_category']);
            $this->assertSame('Dwyane Wade', $record['player_name']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileSkipsTeamOddEntries(): void
    {
        // Odd entries in team groups (1-28) are team season records, should be skipped
        $entry = $this->buildAlltimeCareerEntry('Team Record', 0, 0, 1234, 0);
        $line0 = $this->buildAlltimeLine([
            ['group' => 1, 'entry' => 1, 'data' => $entry],
        ]);

        $file = $this->buildRcbFile([$line0], []);
        $tmpFile = tempnam(sys_get_temp_dir(), 'rcb_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $file);

        try {
            $result = RcbFileParser::parseFile($tmpFile);
            // Team career records should not appear (odd entries in team groups are skipped)
            $teamCareerRecords = array_filter($result['alltime'], static fn (array $r): bool => $r['scope'] === 'team' && $r['record_type'] === 'career');

            $this->assertSame([], array_values($teamCareerRecords));
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileWithCurrentSeasonRecord(): void
    {
        // Build a season line with an entry at position 0 (away PTS rank #1)
        $entry = $this->buildCurrentSeasonEntry('PG', 'Stephen Curry', 3851, 73, 2006);
        $totalChars = RcbFileParser::ENTRIES_PER_SEASON_LINE * RcbFileParser::SEASON_ENTRY_SIZE;
        $seasonLine = str_repeat(' ', $totalChars);
        $seasonLine = substr_replace($seasonLine, $entry, 0, strlen($entry));

        $file = $this->buildRcbFile([], [$seasonLine]);
        $tmpFile = tempnam(sys_get_temp_dir(), 'rcb_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $file);

        try {
            $result = RcbFileParser::parseFile($tmpFile);
            $season = $result['currentSeason'];

            $this->assertNotSame([], $season);

            $record = $season[0];
            $this->assertSame('league', $record['scope']);
            $this->assertSame(0, $record['team_id']);
            $this->assertSame('away', $record['context']);
            $this->assertSame('pts', $record['stat_category']);
            $this->assertSame(1, $record['ranking']);
            $this->assertSame('Stephen Curry', $record['player_name']);
            $this->assertSame('PG', $record['player_position']);
            $this->assertSame(73, $record['stat_value']);
            $this->assertSame(2006, $record['season_year']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RCB file not found');
        RcbFileParser::parseFile('/nonexistent/file.rcb');
    }

    public function testParseFileThrowsForTooFewLines(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rcb_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, "line1\r\nline2\r\n");

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('expected at least');
            RcbFileParser::parseFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testShortEntryReturnsNull(): void
    {
        $this->assertNull(RcbFileParser::parseAlltimeSingleSeasonEntry('too short'));
        $this->assertNull(RcbFileParser::parseAlltimeCareerEntry('too short'));
        $this->assertNull(RcbFileParser::parseCurrentSeasonEntry('too short'));
    }

    public function testDecodeStatValueForCareerPerGameStats(): void
    {
        // Career PTS per game: 3047 → 30.47
        $this->assertSame(30.47, RcbFileParser::decodeStatValue(3047, 'pts'));

        // Career TRB per game: 1100 → 11.00
        $this->assertSame(11.0, RcbFileParser::decodeStatValue(1100, 'trb'));
    }

    public function testMultipleRankingLines(): void
    {
        // Rank #1 and #2 for league PPG
        $entry1 = $this->buildAlltimeSingleSeasonEntry('Stephen Curry', 3851, 3611, 19, 2005);
        $entry2 = $this->buildAlltimeSingleSeasonEntry('Kevin Durant', 2000, 3480, 21, 2003);

        $line0 = $this->buildAlltimeLine([['group' => 0, 'entry' => 0, 'data' => $entry1]]);
        $line1 = $this->buildAlltimeLine([['group' => 0, 'entry' => 0, 'data' => $entry2]]);

        $file = $this->buildRcbFile([$line0, $line1], []);
        $tmpFile = tempnam(sys_get_temp_dir(), 'rcb_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $file);

        try {
            $result = RcbFileParser::parseFile($tmpFile);
            $ppgRecords = array_values(array_filter(
                $result['alltime'],
                static fn (array $r): bool => $r['stat_category'] === 'ppg' && $r['scope'] === 'league'
            ));

            $this->assertCount(2, $ppgRecords);
            $this->assertSame(1, $ppgRecords[0]['ranking']);
            $this->assertSame('Stephen Curry', $ppgRecords[0]['player_name']);
            $this->assertSame(2, $ppgRecords[1]['ranking']);
            $this->assertSame('Kevin Durant', $ppgRecords[1]['player_name']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testSingleSeasonEntryConvertsWindows1252ToUtf8(): void
    {
        // \x9A = š in Windows-1252
        $entry = $this->buildAlltimeSingleSeasonEntry("Kre\x9Aimir Cosic", 100, 2500, 5, 2000);
        $result = RcbFileParser::parseAlltimeSingleSeasonEntry($entry);

        $this->assertNotNull($result);
        $this->assertSame('Krešimir Cosic', $result['player_name']);
    }

    public function testCareerEntryConvertsWindows1252ToUtf8(): void
    {
        // \x9E = ž in Windows-1252
        $entry = $this->buildAlltimeCareerEntry("Dra\x9Een Dalipagic", 200, 15000, 1800, 3);
        $result = RcbFileParser::parseAlltimeCareerEntry($entry);

        $this->assertNotNull($result);
        $this->assertSame('Dražen Dalipagic', $result['player_name']);
    }

    public function testCurrentSeasonEntryConvertsWindows1252ToUtf8(): void
    {
        // \xF3 = ó in Windows-1252, \xE9 = é in Windows-1252
        $entry = $this->buildCurrentSeasonEntry('PG', "Ram\xF3n Fern\xE1ndez", 300, 45, 2005);
        $result = RcbFileParser::parseCurrentSeasonEntry($entry);

        $this->assertNotNull($result);
        $this->assertSame('Ramón Fernández', $result['player_name']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('percentageStatCategoryProvider')]
    public function testPercentageStatsUse10000Divisor(string $category): void
    {
        $value = RcbFileParser::decodeStatValue(5000, $category);
        $this->assertSame(0.5, $value);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function percentageStatCategoryProvider(): array
    {
        return [
            'fg_pct' => ['fg_pct'],
            'ft_pct' => ['ft_pct'],
            'three_pct' => ['three_pct'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('averageStatCategoryProvider')]
    public function testAverageStatsUse100Divisor(string $category): void
    {
        $value = RcbFileParser::decodeStatValue(2500, $category);
        $this->assertSame(25.0, $value);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function averageStatCategoryProvider(): array
    {
        return [
            'ppg' => ['ppg'],
            'rpg' => ['rpg'],
            'apg' => ['apg'],
            'spg' => ['spg'],
            'bpg' => ['bpg'],
            'pts' => ['pts'],
            'trb' => ['trb'],
            'ast' => ['ast'],
            'stl' => ['stl'],
            'blk' => ['blk'],
        ];
    }
}
