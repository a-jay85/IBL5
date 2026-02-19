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
    private string $hisFilePath;

    protected function setUp(): void
    {
        $this->hisFilePath = dirname(__DIR__, 2) . '/IBL5.his';
    }

    public function testParseFileReturnsSeasonsArray(): void
    {
        if (!file_exists($this->hisFilePath)) {
            $this->markTestSkipped('.his file not available');
        }

        $result = HisFileParser::parseFile($this->hisFilePath);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('year', $result[0]);
        $this->assertArrayHasKey('teams', $result[0]);
    }

    public function testParseFileIncludesMultipleSeasons(): void
    {
        if (!file_exists($this->hisFilePath)) {
            $this->markTestSkipped('.his file not available');
        }

        $result = HisFileParser::parseFile($this->hisFilePath);

        // Should have many seasons (1988 onward)
        $this->assertGreaterThan(10, count($result));

        // First season should be 1988
        $this->assertSame(1988, $result[0]['year']);
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
        if (!file_exists($this->hisFilePath)) {
            $this->markTestSkipped('.his file not available');
        }

        $result = HisFileParser::parseFile($this->hisFilePath);

        foreach ($result as $season) {
            foreach ($season['teams'] as $team) {
                $totalGames = $team['wins'] + $team['losses'];
                $this->assertGreaterThan(0, $totalGames, $team['name'] . ' in ' . $season['year'] . ' should have played games');
            }
        }
    }

    public function testEachSeasonHasExactlyOneChampion(): void
    {
        if (!file_exists($this->hisFilePath)) {
            $this->markTestSkipped('.his file not available');
        }

        $result = HisFileParser::parseFile($this->hisFilePath);

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
    }
}
