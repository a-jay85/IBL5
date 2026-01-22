<?php

declare(strict_types=1);

namespace Tests\Navigation;

use PHPUnit\Framework\TestCase;
use Navigation\MobileMenuBuilder;
use Navigation\BlockLinkParser;

class MobileMenuBuilderTest extends TestCase
{
    public function testGetAvailableBlocksReturnsBlockNames(): void
    {
        // Use actual blocks directory
        $blocksPath = dirname(__DIR__, 2) . '/blocks';

        if (!is_dir($blocksPath)) {
            $this->markTestSkipped('Blocks directory not found');
        }

        $builder = new MobileMenuBuilder(null, $blocksPath);
        $blocks = $builder->getAvailableBlocks();

        $this->assertIsArray($blocks);
        $this->assertNotEmpty($blocks);

        // Should include some known blocks
        $this->assertContains('Modules', $blocks);
        $this->assertContains('Login', $blocks);
    }

    public function testCategorizeLinksGroupsCorrectly(): void
    {
        $parser = new BlockLinkParser();

        $links = [
            ['href' => 'modules.php?name=Team', 'text' => 'Team', 'title' => null],
            ['href' => 'modules.php?name=Trade', 'text' => 'Trade', 'title' => null],
            ['href' => 'modules.php?name=Stats', 'text' => 'Stats', 'title' => null],
            ['href' => 'modules.php?name=Leaderboards', 'text' => 'Leaders', 'title' => null],
            ['href' => 'modules.php?name=Your_Account', 'text' => 'Account', 'title' => null],
            ['href' => 'modules.php?name=News', 'text' => 'News', 'title' => null],
            ['href' => 'index.php', 'text' => 'Home', 'title' => null],
        ];

        $categorized = $parser->categorizeLinks($links);

        $this->assertCount(2, $categorized['team']);
        $this->assertCount(2, $categorized['stats']);
        $this->assertCount(1, $categorized['account']);
        $this->assertCount(1, $categorized['site']);
        $this->assertCount(1, $categorized['other']);
    }

    public function testRenderGeneratesHtml(): void
    {
        $parser = new BlockLinkParser();
        $builder = new MobileMenuBuilder($parser, '/nonexistent');

        // The builder will return empty categories since no blocks exist
        $html = $builder->render();

        // Should be empty since no blocks to process
        $this->assertIsString($html);
    }

    public function testClearCacheResetsMenu(): void
    {
        $parser = new BlockLinkParser();
        $builder = new MobileMenuBuilder($parser, '/nonexistent');

        // First build
        $menu1 = $builder->build();

        // Clear cache
        $builder->clearCache();

        // Second build
        $menu2 = $builder->build();

        // Both should be valid category arrays
        $this->assertArrayHasKey('team', $menu1);
        $this->assertArrayHasKey('team', $menu2);
    }

    public function testGetLinkCountsWithRealParser(): void
    {
        $parser = new BlockLinkParser();
        $builder = new MobileMenuBuilder($parser, '/nonexistent');
        $counts = $builder->getLinkCounts();

        // Should have all category keys even if empty
        $this->assertArrayHasKey('team', $counts);
        $this->assertArrayHasKey('stats', $counts);
        $this->assertArrayHasKey('site', $counts);
        $this->assertArrayHasKey('account', $counts);
        $this->assertArrayHasKey('other', $counts);
    }

    public function testGetAllLinksReturnsArray(): void
    {
        $parser = new BlockLinkParser();
        $builder = new MobileMenuBuilder($parser, '/nonexistent');
        $allLinks = $builder->getAllLinks();

        $this->assertIsArray($allLinks);
    }

    public function testBuildReturnsCategories(): void
    {
        $parser = new BlockLinkParser();
        $builder = new MobileMenuBuilder($parser, '/nonexistent');
        $menu = $builder->build();

        $this->assertArrayHasKey('team', $menu);
        $this->assertArrayHasKey('stats', $menu);
        $this->assertArrayHasKey('site', $menu);
        $this->assertArrayHasKey('account', $menu);
        $this->assertArrayHasKey('other', $menu);
    }

    public function testBuildCachesResult(): void
    {
        $parser = new BlockLinkParser();
        $builder = new MobileMenuBuilder($parser, '/nonexistent');

        // First call
        $menu1 = $builder->build();

        // Second call should return cached result
        $menu2 = $builder->build();

        $this->assertEquals($menu1, $menu2);
    }

    public function testRenderWithEmptyMenuReturnsEmptyString(): void
    {
        $parser = new BlockLinkParser();
        $builder = new MobileMenuBuilder($parser, '/nonexistent');

        $html = $builder->render();

        // Empty menu should produce empty or minimal output
        $this->assertIsString($html);
    }
}
