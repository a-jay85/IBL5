<?php

declare(strict_types=1);

namespace Navigation;

use Navigation\Contracts\MobileMenuBuilderInterface;
use Navigation\Contracts\BlockLinkParserInterface;

/**
 * Mobile Menu Builder
 *
 * Builds mobile navigation menus by extracting and grouping links from
 * all sidebar blocks. Uses BlockLinkParser to extract links from block HTML.
 */
class MobileMenuBuilder implements MobileMenuBuilderInterface
{
    private BlockLinkParserInterface $linkParser;
    private string $blocksPath;

    /** @var array<string, string> Block name to title mapping */
    private array $blockTitles = [
        'Modules' => 'Navigation',
        'Team_Functions' => 'Team',
        'Leaders' => 'Leaders',
        'Standings' => 'Standings',
        'User_Info' => 'Account',
        'Login' => 'Login',
        'Search' => 'Search',
    ];

    /** @var array{team: array, stats: array, site: array, account: array, other: array}|null */
    private ?array $cachedMenu = null;

    public function __construct(
        ?BlockLinkParserInterface $linkParser = null,
        string $blocksPath = ''
    ) {
        $this->linkParser = $linkParser ?? new BlockLinkParser();
        $this->blocksPath = $blocksPath ?: dirname(__DIR__, 2) . '/blocks';
    }

    /**
     * @inheritDoc
     */
    public function build(): array
    {
        if ($this->cachedMenu !== null) {
            return $this->cachedMenu;
        }

        $allLinks = [];
        $blockNames = $this->getAvailableBlocks();

        foreach ($blockNames as $blockName) {
            $blockHtml = $this->renderBlock($blockName);
            if ($blockHtml !== '') {
                $links = $this->linkParser->extractLinksWithSource($blockHtml, $blockName);
                $allLinks = array_merge($allLinks, $links);
            }
        }

        $this->cachedMenu = $this->linkParser->categorizeLinks($allLinks);
        return $this->cachedMenu;
    }

    /**
     * @inheritDoc
     */
    public function buildFromBlocks(array $blockNames): array
    {
        $allLinks = [];

        foreach ($blockNames as $blockName) {
            $blockHtml = $this->renderBlock($blockName);
            if ($blockHtml !== '') {
                $links = $this->linkParser->extractLinksWithSource($blockHtml, $blockName);
                $allLinks = array_merge($allLinks, $links);
            }
        }

        return $this->linkParser->categorizeLinks($allLinks);
    }

    /**
     * @inheritDoc
     */
    public function getAvailableBlocks(): array
    {
        $blocks = [];
        $pattern = $this->blocksPath . '/block-*.php';

        foreach (glob($pattern) ?: [] as $file) {
            $filename = basename($file);
            // Extract block name from "block-Name.php"
            if (preg_match('/^block-(.+)\.php$/', $filename, $matches)) {
                $blocks[] = $matches[1];
            }
        }

        return $blocks;
    }

    /**
     * Render a block to HTML
     *
     * @param string $blockName Block name (without "block-" prefix)
     * @return string HTML output
     */
    private function renderBlock(string $blockName): string
    {
        $blockFile = $this->blocksPath . '/block-' . $blockName . '.php';

        if (!file_exists($blockFile)) {
            return '';
        }

        // Capture block output
        ob_start();

        try {
            // Set up globals that blocks expect
            global $db, $mysqli_db, $prefix, $user_prefix, $user, $admin, $cookie;

            // Include the block file
            include $blockFile;
        } catch (\Throwable $e) {
            // Log error but don't break menu
            error_log("Error rendering block {$blockName}: " . $e->getMessage());
        }

        return ob_get_clean() ?: '';
    }

    /**
     * @inheritDoc
     */
    public function render(): string
    {
        $menu = $this->build();
        $html = '';

        $groupOrder = ['team', 'stats', 'site', 'account', 'other'];
        $groupTitles = [
            'team' => 'Team Management',
            'stats' => 'Stats & Standings',
            'site' => 'Site',
            'account' => 'Your Account',
            'other' => 'Other',
        ];

        foreach ($groupOrder as $group) {
            $links = $menu[$group] ?? [];
            if (empty($links)) {
                continue;
            }

            $title = $groupTitles[$group];
            $html .= "<div class=\"nav-group\">\n";
            $html .= "<div class=\"nav-group-title\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</div>\n";

            foreach ($links as $link) {
                $href = htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8');
                $text = htmlspecialchars($link['text'], ENT_QUOTES, 'UTF-8');
                $html .= "<a href=\"{$href}\" class=\"nav-link\">{$text}</a>\n";
            }

            $html .= "</div>\n";
        }

        return $html;
    }

    /**
     * Clear the cached menu
     */
    public function clearCache(): void
    {
        $this->cachedMenu = null;
    }

    /**
     * Get a flat list of all links
     *
     * @return array<int, array{href: string, text: string, title: string|null}>
     */
    public function getAllLinks(): array
    {
        $menu = $this->build();
        $allLinks = [];

        foreach ($menu as $links) {
            $allLinks = array_merge($allLinks, $links);
        }

        return $allLinks;
    }

    /**
     * Get link count by category
     *
     * @return array<string, int>
     */
    public function getLinkCounts(): array
    {
        $menu = $this->build();
        $counts = [];

        foreach ($menu as $category => $links) {
            $counts[$category] = count($links);
        }

        return $counts;
    }
}
