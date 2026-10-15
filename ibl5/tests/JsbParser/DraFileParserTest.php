<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\DraFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\DraFileParser
 */
class DraFileParserTest extends TestCase
{
    /**
     * Build a synthetic .dra file from structured draft data.
     *
     * @param list<array{year: int, picks: list<array{round: int, pick: int, team: string, pos: string, name: string}>}> $drafts
     */
    private function buildDraFile(array $drafts): string
    {
        $content = '';
        foreach ($drafts as $draft) {
            $content .= " ***** {$draft['year']} rookie draft *****\r\n";
            // Add some lottery odds lines
            $content .= "Celtics (250) chances\r\n";
            $content .= "Heat (200) chances\r\n";

            $currentRound = 0;
            foreach ($draft['picks'] as $pick) {
                if ($pick['round'] !== $currentRound) {
                    if ($currentRound > 0) {
                        $content .= "* End of Round {$currentRound} *\r\n";
                    }
                    $currentRound = $pick['round'];
                }
                $content .= "Round {$pick['round']} Pick {$pick['pick']}\r\n";
                // Position is left-padded to 2 chars (e.g., " C" for center, "PF" for power forward)
                $pos = str_pad($pick['pos'], 2, ' ', STR_PAD_LEFT);
                $content .= "{$pick['team']}: {$pos} {$pick['name']}\r\n";
            }
            if ($currentRound > 0) {
                $content .= "* End of Round {$currentRound} *\r\n";
            }
            $content .= "* End of Draft *\r\n";
            // Add padding between drafts
            $content .= str_repeat(' ', 40) . "\r\n";
        }
        return $content;
    }

    public function testParsesMultiYearDraft(): void
    {
        $draData = $this->buildDraFile([
            ['year' => 1988, 'picks' => [
                ['round' => 1, 'pick' => 1, 'team' => 'Celtics', 'pos' => 'PF', 'name' => 'John Smith'],
                ['round' => 1, 'pick' => 2, 'team' => 'Heat', 'pos' => 'SG', 'name' => 'Jane Doe'],
                ['round' => 2, 'pick' => 1, 'team' => 'Celtics', 'pos' => 'C', 'name' => 'Bob Jones'],
            ]],
            ['year' => 1989, 'picks' => [
                ['round' => 1, 'pick' => 1, 'team' => 'Knicks', 'pos' => 'PG', 'name' => 'Alice Brown'],
            ]],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'dra_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $draData);

        try {
            $result = DraFileParser::parseFile($tmpFile);

            $this->assertCount(2, $result);
            $this->assertSame(1988, $result[0]['draft_year']);
            $this->assertCount(3, $result[0]['picks']);
            $this->assertSame(1989, $result[1]['draft_year']);
            $this->assertCount(1, $result[1]['picks']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testExtractsPickDetails(): void
    {
        $draData = $this->buildDraFile([
            ['year' => 2000, 'picks' => [
                ['round' => 1, 'pick' => 5, 'team' => 'Lakers', 'pos' => 'SF', 'name' => 'Test Player'],
            ]],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'dra_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $draData);

        try {
            $result = DraFileParser::parseFile($tmpFile);

            $pick = $result[0]['picks'][0];
            $this->assertSame(1, $pick['round']);
            $this->assertSame(5, $pick['pick']);
            $this->assertSame('Lakers', $pick['team_name']);
            $this->assertSame('SF', $pick['pos']);
            $this->assertSame('Test Player', $pick['player_name']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testNormalizesCenterPosition(): void
    {
        $draData = $this->buildDraFile([
            ['year' => 1995, 'picks' => [
                ['round' => 1, 'pick' => 1, 'team' => 'Bulls', 'pos' => 'C', 'name' => 'Big Man'],
            ]],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'dra_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $draData);

        try {
            $result = DraFileParser::parseFile($tmpFile);

            $this->assertSame('C', $result[0]['picks'][0]['pos']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testConvertsCP1252ToUtf8(): void
    {
        // Build a file with a CP1252 character (e.g., é = 0xE9 in CP1252)
        $content = " ***** 2000 rookie draft *****\r\n";
        $content .= "Round 1 Pick 1\r\n";
        $cp1252Name = "Ren" . chr(0xE9) . " Test";
        $content .= "Heat:  C {$cp1252Name}\r\n";
        $content .= "* End of Round 1 *\r\n";
        $content .= "* End of Draft *\r\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'dra_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $content);

        try {
            $result = DraFileParser::parseFile($tmpFile);

            $this->assertSame('René Test', $result[0]['picks'][0]['player_name']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testSkipsLotteryOddsLines(): void
    {
        $draData = $this->buildDraFile([
            ['year' => 1990, 'picks' => [
                ['round' => 1, 'pick' => 1, 'team' => 'Nets', 'pos' => 'PG', 'name' => 'Only Pick'],
            ]],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'dra_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $draData);

        try {
            $result = DraFileParser::parseFile($tmpFile);

            // Should only have the one pick, not the lottery lines
            $this->assertCount(1, $result[0]['picks']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testHandlesYearHeaderInPaddingLine(): void
    {
        // Year header preceded by null bytes (as seen in real files)
        $content = str_repeat("\0", 20) . " ***** 2005 rookie draft *****\r\n";
        $content .= "Round 1 Pick 1\r\n";
        $content .= "Suns: PF Draft Pick\r\n";
        $content .= "* End of Round 1 *\r\n";
        $content .= "* End of Draft *\r\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'dra_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $content);

        try {
            $result = DraFileParser::parseFile($tmpFile);

            $this->assertCount(1, $result);
            $this->assertSame(2005, $result[0]['draft_year']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('DRA file not found');

        DraFileParser::parseFile('/nonexistent/file.dra');
    }

    public function testParseAcceptsInMemoryData(): void
    {
        $data = "***** 2025 rookie draft *****\nRound 1 Pick 1\nLakers: SF John Doe\n";
        $result = DraFileParser::parse($data);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(2025, $result[0]['draft_year']);
    }

    private static function header(int $year): string
    {
        return "***** {$year} rookie draft *****\n";
    }

    /**
     * @param list<array{0: int, 1: int, 2: string, 3: string, 4: string}> $picks
     * @return array{draft_year: int, picks: list<array{round: int, pick: int, team_name: string, pos: string, player_name: string}>}
     */
    private static function draft(int $year, array $picks): array
    {
        $out = [];
        foreach ($picks as [$round, $pick, $team, $pos, $name]) {
            $out[] = ['round' => $round, 'pick' => $pick, 'team_name' => $team, 'pos' => $pos, 'player_name' => $name];
        }
        return ['draft_year' => $year, 'picks' => $out];
    }

    public function testParseReturnsEmptyListForEmptyInput(): void
    {
        self::assertSame([], DraFileParser::parse(''));
    }

    public function testIgnoresLinesBeforeFirstYearHeader(): void
    {
        self::assertSame(
            [self::draft(2001, [[1, 2, 'Heat', 'PG', 'Real Pick']])],
            DraFileParser::parse("Round 1 Pick 1\nLakers: SF Early Bird\n" . self::header(2001) . "Round 1 Pick 2\nHeat: PG Real Pick\n")
        );
    }

    public function testDropsYearHeadersWithNoPicks(): void
    {
        self::assertSame(
            [self::draft(1991, [[1, 1, 'Bulls', 'C', 'Big Man']])],
            DraFileParser::parse(self::header(1990) . "Celtics: 250 chances\n* End of Draft *\n" . self::header(1991) . "Round 1 Pick 1\nBulls:  C Big Man\n" . self::header(1992))
        );
    }

    public function testDuplicateYearHeadersProduceSeparateEntries(): void
    {
        self::assertSame(
            [
                self::draft(2003, [[1, 1, 'Heat', 'PG', 'First Pass']]),
                self::draft(2003, [[1, 1, 'Jazz', 'SF', 'Second Pass']]),
            ],
            DraFileParser::parse(self::header(2003) . "Round 1 Pick 1\nHeat: PG First Pass\n" . self::header(2003) . "Round 1 Pick 1\nJazz: SF Second Pass\n")
        );
    }

    public function testStripsMidLineNullBytes(): void
    {
        self::assertSame(
            [self::draft(2004, [[1, 1, 'Kings', 'PF', 'Name Here']])],
            DraFileParser::parse(self::header(2004) . "Round 1 Pick 1\nKin\0gs: PF Na\0me Here\n")
        );
    }

    public function testNullPaddingOnlyLineClearsPickExpectation(): void
    {
        self::assertSame(
            [self::draft(2004, [[1, 2, 'Jazz', 'SG', 'Kept Pick']])],
            DraFileParser::parse(self::header(2004) . "Round 1 Pick 1\n\0\0\0\0\nHeat:  C Lost Pick\nRound 1 Pick 2\nJazz: SG Kept Pick\n")
        );
    }

    public function testSplitsOnCrlfAndLoneCr(): void
    {
        self::assertSame(
            [self::draft(2006, [[1, 1, 'Spurs', 'SF', 'Lone Cr'], [1, 2, 'Nets', 'PG', 'Crlf Pick']])],
            DraFileParser::parse("***** 2006 rookie draft *****\r\nRound 1 Pick 1\rSpurs: SF Lone Cr\r\nRound 1 Pick 2\r\nNets: PG Crlf Pick\r")
        );
    }

    public function testChancesLineDoesNotClearPickExpectation(): void
    {
        self::assertSame(
            [self::draft(2007, [[1, 1, 'Celtics', 'C', 'After Chances']])],
            DraFileParser::parse(self::header(2007) . "Round 1 Pick 1\nCeltics: 250 chances\nCeltics:  C After Chances\n")
        );
    }

    public function testEndOfMarkerClearsPickExpectation(): void
    {
        self::assertSame(
            [self::draft(2008, [[1, 2, 'Jazz', 'SF', 'Kept']])],
            DraFileParser::parse(self::header(2008) . "Round 1 Pick 1\n* End of Round 1 *\nHeat: PG Orphan\nRound 1 Pick 2\nJazz: SF Kept\n")
        );
    }

    public function testWhitespaceOnlyLineClearsPickExpectation(): void
    {
        self::assertSame(
            [self::draft(2008, [[1, 2, 'Jazz', 'SF', 'Kept']])],
            DraFileParser::parse(self::header(2008) . "Round 1 Pick 1\n   \nHeat: PG Orphan\nRound 1 Pick 2\nJazz: SF Kept\n")
        );
    }

    public function testOnlyOnePickLineParsedPerPickHeader(): void
    {
        self::assertSame(
            [self::draft(2009, [[1, 1, 'Heat', 'PG', 'First']])],
            DraFileParser::parse(self::header(2009) . "Round 1 Pick 1\nHeat: PG First\nJazz: SF Second\n")
        );
    }

    public function testLineWithoutColonDoesNotConsumePickHeader(): void
    {
        self::assertSame(
            [self::draft(2009, [[1, 1, 'Heat', 'PG', 'After']])],
            DraFileParser::parse(self::header(2009) . "Round 1 Pick 1\nNoColonHere\nHeat: PG After\n")
        );
    }

    public function testRemainderLengthBoundaryAtThreeCharacters(): void
    {
        self::assertSame(
            [self::draft(2010, [[1, 2, 'Heat', 'PG', ''], [1, 3, 'Suns', 'PG', 'X']])],
            DraFileParser::parse(self::header(2010) . "Round 1 Pick 1\nHeat: C\nJazz: SF Not Parsed\nRound 1 Pick 2\nHeat: PG\nRound 1 Pick 3\nSuns:PGX\nRound 1 Pick 4\nMagic:PG\n")
        );
    }

    public function testPositionIsTwoCharsAfterLtrimAndTeamSplitsOnFirstColon(): void
    {
        self::assertSame(
            [self::draft(2011, [[2, 7, 'Heat', 'PG', 'John Doe'], [2, 8, 'Jazz', 'SF', 'A:B']])],
            DraFileParser::parse(self::header(2011) . "Round 2 Pick 7\nHeat  : PGJohn Doe\nRound 2 Pick 8\nJazz: SF A:B\n")
        );
    }

    public function testRoundZeroPickHeaderNeverYieldsPick(): void
    {
        self::assertSame(
            [self::draft(2012, [[1, 2, 'Jazz', 'SF', 'Kept']])],
            DraFileParser::parse(self::header(2012) . "Round 0 Pick 1\nHeat: PG Zero Round\nRound 1 Pick 2\nJazz: SF Kept\n")
        );
    }

    public function testYearHeaderResetsPendingPickHeader(): void
    {
        self::assertSame(
            [self::draft(2014, [[1, 1, 'Jazz', 'SF', 'Kept']])],
            DraFileParser::parse(self::header(2013) . "Round 1 Pick 1\n" . self::header(2014) . "Heat: PG Orphan\nRound 1 Pick 1\nJazz: SF Kept\n")
        );
    }

    public function testRoundHeaderRegexIsAnchoredAtBothEnds(): void
    {
        self::assertSame(
            [
                self::draft(2015, [[1, 1, 'Heat', 'PG', 'Round 2 Pick 3']]),
                self::draft(2016, [[1, 1, 'Round 4 Pick 5 Jazz', 'SF', 'Odd']]),
            ],
            DraFileParser::parse(self::header(2015) . "Round 1 Pick 1\nHeat: PG Round 2 Pick 3\n" . self::header(2016) . "Round 1 Pick 1\nRound 4 Pick 5 Jazz: SF Odd\n")
        );
    }
}