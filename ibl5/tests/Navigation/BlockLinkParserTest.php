<?php

declare(strict_types=1);

namespace Tests\Navigation;

use PHPUnit\Framework\TestCase;
use Navigation\BlockLinkParser;

class BlockLinkParserTest extends TestCase
{
    private BlockLinkParser $parser;

    protected function setUp(): void
    {
        $this->parser = new BlockLinkParser();
    }

    public function testExtractLinksFromSimpleHtml(): void
    {
        $html = '<a href="index.php">Home</a>';
        $links = $this->parser->extractLinks($html);

        $this->assertCount(1, $links);
        $this->assertEquals('index.php', $links[0]['href']);
        $this->assertEquals('Home', $links[0]['text']);
    }

    public function testExtractMultipleLinks(): void
    {
        $html = '
            <div>
                <a href="page1.php">Page 1</a>
                <a href="page2.php">Page 2</a>
                <a href="page3.php">Page 3</a>
            </div>
        ';
        $links = $this->parser->extractLinks($html);

        $this->assertCount(3, $links);
        $this->assertEquals('page1.php', $links[0]['href']);
        $this->assertEquals('page2.php', $links[1]['href']);
        $this->assertEquals('page3.php', $links[2]['href']);
    }

    public function testExtractLinksWithTitle(): void
    {
        $html = '<a href="page.php" title="Click here">Link</a>';
        $links = $this->parser->extractLinks($html);

        $this->assertCount(1, $links);
        $this->assertEquals('Click here', $links[0]['title']);
    }

    public function testSkipsJavascriptLinks(): void
    {
        $html = '
            <a href="javascript:void(0)">Click me</a>
            <a href="javascript:alert()">Alert</a>
            <a href="page.php">Valid Link</a>
        ';
        $links = $this->parser->extractLinks($html);

        $this->assertCount(1, $links);
        $this->assertEquals('page.php', $links[0]['href']);
    }

    public function testSkipsAnchorLinks(): void
    {
        $html = '
            <a href="#section1">Section 1</a>
            <a href="#top">Top</a>
            <a href="page.php">Valid Link</a>
        ';
        $links = $this->parser->extractLinks($html);

        $this->assertCount(1, $links);
        $this->assertEquals('page.php', $links[0]['href']);
    }

    public function testSkipsEmptyLinks(): void
    {
        $html = '
            <a href="">Empty</a>
            <a href="page.php"></a>
            <a href="valid.php">Valid</a>
        ';
        $links = $this->parser->extractLinks($html);

        $this->assertCount(1, $links);
        $this->assertEquals('valid.php', $links[0]['href']);
    }

    public function testDeduplicatesLinks(): void
    {
        $html = '
            <a href="page.php">Link 1</a>
            <a href="page.php">Link 2</a>
            <a href="other.php">Other</a>
        ';
        $links = $this->parser->extractLinks($html);

        $this->assertCount(2, $links);
    }

    public function testExtractLinksWithSource(): void
    {
        $html = '<a href="page.php">Link</a>';
        $links = $this->parser->extractLinksWithSource($html, 'TestBlock');

        $this->assertCount(1, $links);
        $this->assertEquals('TestBlock', $links[0]['block']);
    }

    public function testFilterLinks(): void
    {
        $links = [
            ['href' => 'modules.php?name=Team', 'text' => 'Team', 'title' => null],
            ['href' => 'modules.php?name=Stats', 'text' => 'Stats', 'title' => null],
            ['href' => 'index.php', 'text' => 'Home', 'title' => null],
        ];

        $filtered = $this->parser->filterLinks($links, '/name=Team/');

        $this->assertCount(1, $filtered);
    }

    public function testCategorizeLinks(): void
    {
        $links = [
            ['href' => 'modules.php?name=Team', 'text' => 'Team', 'title' => null],
            ['href' => 'modules.php?name=Trade', 'text' => 'Trade', 'title' => null],
            ['href' => 'modules.php?name=Stats', 'text' => 'Stats', 'title' => null],
            ['href' => 'modules.php?name=Leaderboards', 'text' => 'Leaders', 'title' => null],
            ['href' => 'modules.php?name=Your_Account', 'text' => 'Account', 'title' => null],
            ['href' => 'modules.php?name=News', 'text' => 'News', 'title' => null],
            ['href' => 'index.php', 'text' => 'Home', 'title' => null],
        ];

        $categorized = $this->parser->categorizeLinks($links);

        $this->assertCount(2, $categorized['team']);
        $this->assertCount(2, $categorized['stats']);
        $this->assertCount(1, $categorized['account']);
        $this->assertCount(1, $categorized['site']);
        $this->assertCount(1, $categorized['other']);
    }

    public function testHandlesEmptyInput(): void
    {
        $links = $this->parser->extractLinks('');
        $this->assertCount(0, $links);
    }

    public function testHandlesMalformedHtml(): void
    {
        $html = '<a href="page.php">Unclosed link<a href="other.php">Other</a>';
        $links = $this->parser->extractLinks($html);

        // Should still extract links despite malformed HTML
        $this->assertGreaterThan(0, count($links));
    }

    public function testExtractModuleLinks(): void
    {
        $html = '
            <a href="modules.php?name=Team&op=view">View Team</a>
            <a href="modules.php?name=Team&op=edit">Edit Team</a>
            <a href="modules.php?name=Stats">Stats</a>
        ';

        $links = $this->parser->extractModuleLinks($html, 'Team');

        $this->assertCount(2, $links);
    }

    public function testHandlesNestedLinks(): void
    {
        $html = '
            <div>
                <p><a href="page1.php">Link 1</a></p>
                <ul>
                    <li><a href="page2.php">Link 2</a></li>
                    <li><a href="page3.php">Link 3</a></li>
                </ul>
            </div>
        ';
        $links = $this->parser->extractLinks($html);

        $this->assertCount(3, $links);
    }

    public function testPreservesLinkTextWithWhitespace(): void
    {
        $html = '<a href="page.php">  Link with spaces  </a>';
        $links = $this->parser->extractLinks($html);

        $this->assertEquals('Link with spaces', $links[0]['text']);
    }
}
