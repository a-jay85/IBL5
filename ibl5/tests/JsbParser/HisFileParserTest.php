<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\HisFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\HisFileParser
 */
class HisFileParserTest extends TestCase
{
    /**
     * Build a synthetic .his file with known season data.
     *
     * @param list<array{lines: list<string>}> $seasons Each season's team lines
     */
    private function buildHisFile(array $seasons): string
    {
        $content = '';
        foreach ($seasons as $season) {
            foreach ($season['lines'] as $line) {
                $content .= $line . "\r\n";
            }
            $content .= "\r\n"; // blank line between seasons
        }
        return $content;
    }

    public function testParseFileReturnsSeasonsArray(): void
    {
        $hisData = $this->buildHisFile([
            ['lines' => [
                'Clippers (62-20)  defeat the  Raptors (59-23) 4 games to 3 in the championship (1988)',
                'Raptors (59-23) lose to the Clippers (62-20) 4 games to 3 in the championship (1988)',
                'Celtics (24-58) (1988)',
            ]],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'his_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $hisData);

        try {
            $result = HisFileParser::parseFile($tmpFile);

            $this->assertNotEmpty($result);
            $this->assertArrayHasKey('year', $result[0]);
            $this->assertArrayHasKey('teams', $result[0]);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileIncludesMultipleSeasons(): void
    {
        $seasons = [];
        for ($y = 1988; $y <= 1999; $y++) {
            $seasons[] = ['lines' => [
                "Clippers (62-20)  defeat the  Raptors (59-23) 4 games to 3 in the championship ({$y})",
                "Raptors (59-23) lose to the Clippers (62-20) 4 games to 3 in the championship ({$y})",
                "Celtics (24-58) ({$y})",
            ]];
        }

        $hisData = $this->buildHisFile($seasons);

        $tmpFile = tempnam(sys_get_temp_dir(), 'his_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $hisData);

        try {
            $result = HisFileParser::parseFile($tmpFile);

            $this->assertCount(12, $result);
            $this->assertSame(1988, $result[0]['year']);
            $this->assertSame(1999, $result[11]['year']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseTeamLineWithChampionship(): void
    {
        $line = 'Clippers (62-20)  defeat the  Raptors (59-23) 4 games to 3 in the championship (1988)';
        $result = HisFileParser::parseTeamLine($line);

        $this->assertNotNull($result);
        $this->assertSame('Clippers', $result['name']);
        $this->assertSame(62, $result['wins']);
        $this->assertSame(20, $result['losses']);
        $this->assertSame(1988, $result['year']);
        $this->assertSame(1, $result['made_playoffs']);
        $this->assertSame(1, $result['won_championship']);
        $this->assertSame('championship', $result['playoff_round_reached']);
    }

    public function testParseTeamLineWithChampionshipLoss(): void
    {
        $line = 'Raptors (59-23) lose to the Clippers (62-20) 4 games to 3 in the championship (1988)';
        $result = HisFileParser::parseTeamLine($line);

        $this->assertNotNull($result);
        $this->assertSame('Raptors', $result['name']);
        $this->assertSame(59, $result['wins']);
        $this->assertSame(23, $result['losses']);
        $this->assertSame(1, $result['made_playoffs']);
        $this->assertSame(0, $result['won_championship']);
        $this->assertSame('championship', $result['playoff_round_reached']);
    }

    public function testParseTeamLineWithSemiFinals(): void
    {
        $line = 'Grizzlies (60-22) lose to the Clippers (62-20) 4 games to 3 in the semi-finals (1988)';
        $result = HisFileParser::parseTeamLine($line);

        $this->assertNotNull($result);
        $this->assertSame('Grizzlies', $result['name']);
        $this->assertSame(1, $result['made_playoffs']);
        $this->assertSame('semi-finals', $result['playoff_round_reached']);
    }

    public function testParseTeamLineWithQuarterFinals(): void
    {
        $line = 'Heat (59-23) lose to the Pacers (54-28) 4 games to 3 in the quarter-finals (1988)';
        $result = HisFileParser::parseTeamLine($line);

        $this->assertNotNull($result);
        $this->assertSame('Heat', $result['name']);
        $this->assertSame(1, $result['made_playoffs']);
        $this->assertSame('quarter-finals', $result['playoff_round_reached']);
    }

    public function testParseTeamLineWithFirstRound(): void
    {
        $line = 'Knicks (50-32) lose to the Nets (50-32) 3 games to 2 in the first round (1988)';
        $result = HisFileParser::parseTeamLine($line);

        $this->assertNotNull($result);
        $this->assertSame('Knicks', $result['name']);
        $this->assertSame(1, $result['made_playoffs']);
        $this->assertSame('first round', $result['playoff_round_reached']);
    }

    public function testParseTeamLineNoPlayoffs(): void
    {
        $line = 'Celtics (24-58) (1988)';
        $result = HisFileParser::parseTeamLine($line);

        $this->assertNotNull($result);
        $this->assertSame('Celtics', $result['name']);
        $this->assertSame(24, $result['wins']);
        $this->assertSame(58, $result['losses']);
        $this->assertSame(0, $result['made_playoffs']);
        $this->assertSame(0, $result['won_championship']);
        $this->assertSame('', $result['playoff_round_reached']);
    }

    public function testParseTeamLineReturnsNullForEmptyLine(): void
    {
        $this->assertNull(HisFileParser::parseTeamLine(''));
        $this->assertNull(HisFileParser::parseTeamLine('   '));
    }

    public function testParseTeamLineReturnsNullForInvalidFormat(): void
    {
        $this->assertNull(HisFileParser::parseTeamLine('Some random text'));
        $this->assertNull(HisFileParser::parseTeamLine('No parentheses here'));
    }

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HIS file not found');

        HisFileParser::parseFile('/nonexistent/file.his');
    }

    public function testAllTeamsHavePositiveWins(): void
    {
        $hisData = $this->buildHisFile([
            ['lines' => [
                'Clippers (62-20)  defeat the  Raptors (59-23) 4 games to 3 in the championship (1988)',
                'Raptors (59-23) lose to the Clippers (62-20) 4 games to 3 in the championship (1988)',
                'Celtics (24-58) (1988)',
                'Heat (41-41) (1988)',
            ]],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'his_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $hisData);

        try {
            $result = HisFileParser::parseFile($tmpFile);

            foreach ($result as $season) {
                foreach ($season['teams'] as $team) {
                    $totalGames = $team['wins'] + $team['losses'];
                    $this->assertGreaterThan(0, $totalGames, $team['name'] . ' in ' . $season['year'] . ' should have played games');
                }
            }
        } finally {
            unlink($tmpFile);
        }
    }

    public function testEachSeasonHasExactlyOneChampion(): void
    {
        $hisData = $this->buildHisFile([
            ['lines' => [
                'Clippers (62-20)  defeat the  Raptors (59-23) 4 games to 3 in the championship (1988)',
                'Raptors (59-23) lose to the Clippers (62-20) 4 games to 3 in the championship (1988)',
                'Celtics (24-58) (1988)',
            ]],
            ['lines' => [
                'Heat (55-27)  defeat the  Nets (50-32) 4 games to 1 in the championship (1989)',
                'Nets (50-32) lose to the Heat (55-27) 4 games to 1 in the championship (1989)',
                'Lakers (30-52) (1989)',
            ]],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'his_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $hisData);

        try {
            $result = HisFileParser::parseFile($tmpFile);

            foreach ($result as $season) {
                $champions = array_filter(
                    $season['teams'],
                    static fn (array $team): bool => $team['won_championship'] === 1
                );
                $this->assertCount(
                    1,
                    $champions,
                    'Season ' . $season['year'] . ' should have exactly 1 champion, found ' . count($champions)
                );
            }
        } finally {
            unlink($tmpFile);
        }
    }
}
