<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Updater\ScheduleHtmParser;

/**
 * @covers \Updater\ScheduleHtmParser
 */
class ScheduleHtmParserTest extends TestCase
{
    public function testParsesPlayedPlayoffGames(): void
    {
        $html = '<html><body><table>'
            . '<tr><th>Post 1 2007</th></tr>'
            . '<tr><th>visitor</th><th>score</th><th>home</th><th>score</th></tr>'
            . '<tr><td><a href="Hawks.htm">Hawks</a></td><td><a href="box6000.htm">142</a></td>'
            . '<td><a href="Braves.htm">Braves</a></td><td><a href="box6000.htm">132</a></td></tr>'
            . '</table></body></html>';

        $games = ScheduleHtmParser::parsePlayoffGames($html);

        $this->assertCount(1, $games);
        $this->assertSame('Post 1 2007', $games[0]['date_label']);
        $this->assertSame('Hawks', $games[0]['visitor']);
        $this->assertSame('Braves', $games[0]['home']);
        $this->assertSame(142, $games[0]['visitor_score']);
        $this->assertSame(132, $games[0]['home_score']);
        $this->assertSame(6000, $games[0]['box_id']);
        $this->assertTrue($games[0]['played']);
    }

    public function testParsesUnplayedPlayoffGames(): void
    {
        $html = '<html><body><table>'
            . '<tr><th>Post 4 2007</th></tr>'
            . '<tr><th>visitor</th><th>score</th><th>home</th><th>score</th></tr>'
            . '<tr><td><a href="Braves.htm">Braves</a></td><td></td>'
            . '<td><a href="Hawks.htm">Hawks</a></td><td></td></tr>'
            . '</table></body></html>';

        $games = ScheduleHtmParser::parsePlayoffGames($html);

        $this->assertCount(1, $games);
        $this->assertSame('Post 4 2007', $games[0]['date_label']);
        $this->assertSame('Braves', $games[0]['visitor']);
        $this->assertSame('Hawks', $games[0]['home']);
        $this->assertSame(0, $games[0]['visitor_score']);
        $this->assertSame(0, $games[0]['home_score']);
        $this->assertNull($games[0]['box_id']);
        $this->assertFalse($games[0]['played']);
    }

    public function testIgnoresRegularSeasonGames(): void
    {
        $html = '<html><body><table>'
            . '<tr><th>November 2 2006</th></tr>'
            . '<tr><th>visitor</th><th>score</th><th>home</th><th>score</th></tr>'
            . '<tr><td><a href="Warriors.htm">Warriors</a></td><td><a href="box515.htm">146</a></td>'
            . '<td><a href="Hawks.htm">Hawks</a></td><td><a href="box515.htm">130</a></td></tr>'
            . '<tr><th>Post 1 2007</th></tr>'
            . '<tr><th>visitor</th><th>score</th><th>home</th><th>score</th></tr>'
            . '<tr><td><a href="Hawks.htm">Hawks</a></td><td><a href="box6000.htm">142</a></td>'
            . '<td><a href="Braves.htm">Braves</a></td><td><a href="box6000.htm">132</a></td></tr>'
            . '</table></body></html>';

        $games = ScheduleHtmParser::parsePlayoffGames($html);

        $this->assertCount(1, $games);
        $this->assertSame('Hawks', $games[0]['visitor']);
    }

    public function testParsesMultipleDatesAndGames(): void
    {
        $html = '<html><body><table>'
            . '<tr><th>Post 1 2007</th></tr>'
            . '<tr><th>visitor</th><th>score</th><th>home</th><th>score</th></tr>'
            . '<tr><td><a href="Hawks.htm">Hawks</a></td><td><a href="box6000.htm">142</a></td>'
            . '<td><a href="Braves.htm">Braves</a></td><td><a href="box6000.htm">132</a></td></tr>'
            . '<tr><td><a href="Pacers.htm">Pacers</a></td><td><a href="box6001.htm">120</a></td>'
            . '<td><a href="Nets.htm">Nets</a></td><td><a href="box6001.htm">107</a></td></tr>'
            . '<tr><th>Post 4 2007</th></tr>'
            . '<tr><th>visitor</th><th>score</th><th>home</th><th>score</th></tr>'
            . '<tr><td><a href="Braves.htm">Braves</a></td><td></td>'
            . '<td><a href="Hawks.htm">Hawks</a></td><td></td></tr>'
            . '</table></body></html>';

        $games = ScheduleHtmParser::parsePlayoffGames($html);

        $this->assertCount(3, $games);
        $this->assertSame('Post 1 2007', $games[0]['date_label']);
        $this->assertSame('Post 1 2007', $games[1]['date_label']);
        $this->assertSame('Post 4 2007', $games[2]['date_label']);
        $this->assertTrue($games[0]['played']);
        $this->assertFalse($games[2]['played']);
    }

    public function testTrimsTeamNamesWithTrailingSpaces(): void
    {
        $html = '<html><body><table>'
            . '<tr><th>Post 1 2007</th></tr>'
            . '<tr><th>visitor</th><th>score</th><th>home</th><th>score</th></tr>'
            . '<tr><td><a href="Hawks.htm">Hawks                           </a></td>'
            . '<td><a href="box6000.htm">142</a></td>'
            . '<td><a href="Braves.htm">Braves                          </a></td>'
            . '<td><a href="box6000.htm">132</a></td></tr>'
            . '</table></body></html>';

        $games = ScheduleHtmParser::parsePlayoffGames($html);

        $this->assertCount(1, $games);
        $this->assertSame('Hawks', $games[0]['visitor']);
        $this->assertSame('Braves', $games[0]['home']);
    }

    public function testReturnsEmptyArrayForNoPlayoffGames(): void
    {
        $html = '<html><body><table>'
            . '<tr><th>November 2 2006</th></tr>'
            . '<tr><th>visitor</th><th>score</th><th>home</th><th>score</th></tr>'
            . '<tr><td><a href="Warriors.htm">Warriors</a></td><td><a href="box515.htm">146</a></td>'
            . '<td><a href="Hawks.htm">Hawks</a></td><td><a href="box515.htm">130</a></td></tr>'
            . '</table></body></html>';

        $games = ScheduleHtmParser::parsePlayoffGames($html);

        $this->assertCount(0, $games);
    }
}
