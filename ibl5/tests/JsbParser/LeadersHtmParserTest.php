<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\LeadersHtmParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\LeadersHtmParser
 */
class LeadersHtmParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/leaders_htm_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);
    }

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');
        LeadersHtmParser::parseFile('/nonexistent/Leaders.htm');
    }

    public function testParsesIndividualAwards(): void
    {
        $html = $this->buildHtml([
            $this->makeSection('most valuable player', [
                ['PG', 'Stephen Curry', 'Clippers'],
                ['SF', 'LeBron James', 'Lakers'],
                ['PF', 'Kevin Durant', 'Nets'],
                ['SG', 'Kobe Bryant', 'Celtics'],
                ['C', 'Shaquille ONeal', 'Heat'],
            ]),
        ]);

        $result = $this->parseHtml($html);

        $this->assertArrayHasKey('Most Valuable Player', $result['individual']);
        $this->assertCount(5, $result['individual']['Most Valuable Player']);
        $this->assertSame('Stephen Curry', $result['individual']['Most Valuable Player'][0]);
        $this->assertSame('Shaquille ONeal', $result['individual']['Most Valuable Player'][4]);
    }

    public function testParsesDefensivePlayerOfTheYear(): void
    {
        $html = $this->buildHtml([
            $this->makeSection('defensive player', [
                ['PF', 'Anthony Davis', 'Lakers'],
                ['C', 'Rudy Gobert', 'Jazz'],
                ['SF', 'Kawhi Leonard', 'Clippers'],
                ['PG', 'Jrue Holiday', 'Bucks'],
                ['SG', 'Marcus Smart', 'Celtics'],
            ]),
        ]);

        $result = $this->parseHtml($html);

        $this->assertArrayHasKey('Defensive Player of the Year', $result['individual']);
        $this->assertSame('Anthony Davis', $result['individual']['Defensive Player of the Year'][0]);
    }

    public function testParsesRookieOfTheYear(): void
    {
        $html = $this->buildHtml([
            $this->makeSection('rookie of the year', [
                ['PG', 'Victor Wembanyama', 'Spurs'],
                ['SG', 'Chet Holmgren', 'Thunder'],
            ]),
        ]);

        $result = $this->parseHtml($html);

        $this->assertArrayHasKey('Rookie of the Year', $result['individual']);
        $this->assertSame('Victor Wembanyama', $result['individual']['Rookie of the Year'][0]);
    }

    public function testParsesSixthManAward(): void
    {
        $html = $this->buildHtml([
            $this->makeSection('6th Man Award', [
                ['SG', 'Tyler Herro', 'Heat'],
            ]),
        ]);

        $result = $this->parseHtml($html);

        $this->assertArrayHasKey('6th Man Award', $result['individual']);
        $this->assertSame('Tyler Herro', $result['individual']['6th Man Award'][0]);
    }

    public function testParsesStatLeaders(): void
    {
        $html = $this->buildHtml([
            $this->makeStatSection('scoring leader', [
                ['PG', 'Stephen Curry', 'Clippers', '35.77'],
                ['SF', 'LeBron James', 'Lakers', '33.21'],
                ['PF', 'Kevin Durant', 'Nets', '31.50'],
                ['SG', 'Kobe Bryant', 'Celtics', '30.12'],
                ['C', 'Joel Embiid', 'Bullets', '29.88'],
            ]),
            $this->makeStatSection('rebound leader', [
                ['C', 'Nikola Jokic', 'Nuggets', '13.44'],
                ['PF', 'Giannis Antetokounmpo', 'Bucks', '12.77'],
            ]),
        ]);

        $result = $this->parseHtml($html);

        $this->assertArrayHasKey('Scoring Leader', $result['stat_leaders']);
        $this->assertCount(5, $result['stat_leaders']['Scoring Leader']);
        $this->assertSame('Stephen Curry', $result['stat_leaders']['Scoring Leader'][0]['name']);
        $this->assertSame('35.77', $result['stat_leaders']['Scoring Leader'][0]['stat']);

        $this->assertArrayHasKey('Rebounding Leader', $result['stat_leaders']);
        $this->assertCount(2, $result['stat_leaders']['Rebounding Leader']);
    }

    public function testParsesTeamSelections(): void
    {
        $html = $this->buildHtml([
            $this->makeTeamSection('All League', [
                ['PG', 'Curry', 'Clippers', 'PG', 'Haliburton', 'Pelicans', 'PG', 'Harden', 'Bulls'],
                ['SG', 'Bryant', 'Celtics', 'SG', 'Mitchell', 'Jazz', 'SG', 'Booker', 'Suns'],
                ['SF', 'James', 'Lakers', 'SF', 'Leonard', 'Clippers', 'SF', 'Tatum', 'Celtics'],
                ['PF', 'Durant', 'Nets', 'PF', 'Davis', 'Lakers', 'PF', 'Siakam', 'Raptors'],
                ['C', 'Jokic', 'Nuggets', 'C', 'Embiid', 'Bullets', 'C', 'Gobert', 'Jazz'],
            ]),
        ]);

        $result = $this->parseHtml($html);

        $this->assertArrayHasKey('All-League First Team', $result['teams']);
        $this->assertCount(5, $result['teams']['All-League First Team']);
        $this->assertSame('Curry', $result['teams']['All-League First Team'][0]);
        $this->assertSame('Jokic', $result['teams']['All-League First Team'][4]);

        $this->assertArrayHasKey('All-League Second Team', $result['teams']);
        $this->assertSame('Haliburton', $result['teams']['All-League Second Team'][0]);

        $this->assertArrayHasKey('All-League Third Team', $result['teams']);
        $this->assertSame('Harden', $result['teams']['All-League Third Team'][0]);
    }

    public function testParsesDefensiveTeams(): void
    {
        $html = $this->buildHtml([
            $this->makeTeamSection('All Defense', [
                ['PG', 'Holiday', 'Bucks', 'PG', 'Smart', 'Celtics', 'PG', 'Paul', 'Suns'],
                ['SG', 'Butler', 'Heat', 'SG', 'Green', 'Warriors', 'SG', 'Allen', 'Pelicans'],
                ['SF', 'Leonard', 'Clippers', 'SF', 'Bridges', 'Nets', 'SF', 'Anunoby', 'Raptors'],
                ['PF', 'Davis', 'Lakers', 'PF', 'Giannis', 'Bucks', 'PF', 'Williams', 'Thunder'],
                ['C', 'Gobert', 'Jazz', 'C', 'Bam', 'Heat', 'C', 'Turner', 'Pacers'],
            ]),
        ]);

        $result = $this->parseHtml($html);

        $this->assertArrayHasKey('All-Defensive Team (1st)', $result['teams']);
        $this->assertArrayHasKey('All-Defensive Team (2nd)', $result['teams']);
        $this->assertArrayHasKey('All-Defensive Team (3rd)', $result['teams']);
        $this->assertSame('Holiday', $result['teams']['All-Defensive Team (1st)'][0]);
    }

    public function testParsesRookieTeams(): void
    {
        $html = $this->buildHtml([
            $this->makeTeamSection('All Rookie', [
                ['PG', 'Wemby', 'Spurs', 'PG', 'Miller', 'Pacers', 'PG', 'Henderson', 'Kings'],
                ['SG', 'Holmgren', 'Thunder', 'SG', 'Thompson', 'Rockets', 'SG', 'Whitmore', 'Rockets'],
                ['SF', 'Ausar', 'Pistons', 'SF', 'Wallace', 'Kings', 'SF', 'Lively', 'Mavericks'],
                ['PF', 'Jaime', 'Heat', 'PF', 'Hawkins', 'Pacers', 'PF', 'Amen', 'Rockets'],
                ['C', 'Sarr', 'Bullets', 'C', 'Edey', 'Grizzlies', 'C', 'Castle', 'Spurs'],
            ]),
        ]);

        $result = $this->parseHtml($html);

        $this->assertArrayHasKey('All-Rookie Team (1st)', $result['teams']);
        $this->assertArrayHasKey('All-Rookie Team (2nd)', $result['teams']);
        $this->assertArrayHasKey('All-Rookie Team (3rd)', $result['teams']);
    }

    public function testParsesComprehensiveFile(): void
    {
        $html = $this->buildHtml([
            $this->makeSection('most valuable player', [
                ['PG', 'Player1', 'Team1'],
                ['SG', 'Player2', 'Team2'],
                ['SF', 'Player3', 'Team3'],
                ['PF', 'Player4', 'Team4'],
                ['C', 'Player5', 'Team5'],
            ]),
            $this->makeStatSection('scoring leader', [
                ['PG', 'Scorer1', 'Team1', '30.5'],
                ['SG', 'Scorer2', 'Team2', '29.1'],
                ['SF', 'Scorer3', 'Team3', '28.0'],
                ['PF', 'Scorer4', 'Team4', '27.5'],
                ['C', 'Scorer5', 'Team5', '26.3'],
            ]),
            $this->makeTeamSection('All League', [
                ['PG', 'AL1', 'T1', 'PG', 'AL6', 'T6', 'PG', 'AL11', 'T11'],
                ['SG', 'AL2', 'T2', 'SG', 'AL7', 'T7', 'SG', 'AL12', 'T12'],
                ['SF', 'AL3', 'T3', 'SF', 'AL8', 'T8', 'SF', 'AL13', 'T13'],
                ['PF', 'AL4', 'T4', 'PF', 'AL9', 'T9', 'PF', 'AL14', 'T14'],
                ['C', 'AL5', 'T5', 'C', 'AL10', 'T10', 'C', 'AL15', 'T15'],
            ]),
        ]);

        $result = $this->parseHtml($html);

        $this->assertCount(1, $result['individual']);
        $this->assertCount(1, $result['stat_leaders']);
        $this->assertCount(3, $result['teams']);
    }

    /**
     * Build a complete HTML file from section strings.
     *
     * @param list<string> $sections
     */
    private function buildHtml(array $sections): string
    {
        return '<html><body><pre><table>' . implode("\n", $sections) . '</table></pre></body></html>';
    }

    /**
     * Build an individual award section (3-column: position, name, team).
     *
     * @param list<list<string>> $rows Each row: [position, name, team]
     */
    private function makeSection(string $headerText, array $rows): string
    {
        $html = '<tr><th>po</th><th>' . $headerText . '</th><th>team</th></tr>';
        foreach ($rows as $row) {
            $html .= '<tr><td CLASS=tdp>' . $row[0] . '</td><td CLASS=tdp>' . $row[1] . '</td><td>' . $row[2] . '</td></tr>';
        }
        return $html;
    }

    /**
     * Build a stat leader section (4-column: position, name, team, stat).
     *
     * @param list<list<string>> $rows Each row: [position, name, team, stat]
     */
    private function makeStatSection(string $headerText, array $rows): string
    {
        $html = '<tr><th>po</th><th>' . $headerText . '</th><th>team</th><th>ppg</th></tr>';
        foreach ($rows as $row) {
            $html .= '<tr><td CLASS=tdp>' . $row[0] . '</td><td CLASS=tdp>' . $row[1] . '</td><td>' . $row[2] . '</td><td>' . $row[3] . '</td></tr>';
        }
        return $html;
    }

    /**
     * Build a team section (9-column: 3 teams side-by-side).
     *
     * @param list<list<string>> $rows Each row: 9 values [pos1, name1, team1, pos2, name2, team2, pos3, name3, team3]
     */
    private function makeTeamSection(string $prefix, array $rows): string
    {
        $html = '<tr><th></th><th>' . $prefix . ' 1st</th><th></th><th></th><th>' . $prefix . ' 2nd</th><th></th><th></th><th>' . $prefix . ' 3rd</th><th></th></tr>';
        $html .= '<tr><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th></tr>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            for ($i = 0; $i < 9; $i++) {
                $class = ($i % 3 === 0 || $i % 3 === 1) ? ' CLASS=tdp' : '';
                $html .= '<td' . $class . '>' . $row[$i] . '</td>';
            }
            $html .= '</tr>';
        }
        return $html;
    }

    /**
     * Write HTML to a temp file and parse it.
     *
     * @return array{individual: array<string, list<string>>, stat_leaders: array<string, list<array{name: string, stat: string}>>, teams: array<string, list<string>>}
     */
    private function parseHtml(string $html): array
    {
        $filePath = $this->tempDir . '/Leaders.htm';
        file_put_contents($filePath, $html);
        return LeadersHtmParser::parseFile($filePath);
    }
}
