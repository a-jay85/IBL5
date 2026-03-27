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
        $this->expectExceptionMessage('DRA file not found');

        DraFileParser::parseFile('/nonexistent/file.dra');
    }
}
