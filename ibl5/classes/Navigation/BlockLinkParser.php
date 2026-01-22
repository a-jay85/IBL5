<?php

declare(strict_types=1);

namespace Navigation;

use Navigation\Contracts\BlockLinkParserInterface;

/**
 * Block Link Parser
 *
 * Extracts navigation links from HTML block content using DOM parsing.
 * Used to build mobile menus from desktop sidebar blocks.
 */
class BlockLinkParser implements BlockLinkParserInterface
{
    /**
     * @inheritDoc
     */
    public function extractLinks(string $blockHtml): array
    {
        if (trim($blockHtml) === '') {
            return [];
        }

        $links = [];

        // Suppress DOM parsing errors for malformed HTML
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument();

            // Load HTML with proper encoding
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $blockHtml . '</body></html>';
            $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $xpath = new \DOMXPath($dom);
            $anchors = $xpath->query('//a[@href]');

            if ($anchors === false) {
                return [];
            }

            foreach ($anchors as $anchor) {
                if (!$anchor instanceof \DOMElement) {
                    continue;
                }

                $href = $anchor->getAttribute('href');
                $text = trim($anchor->textContent);
                $title = $anchor->getAttribute('title') ?: null;

                // Skip empty links, javascript links, and anchors
                if ($href === '' || $text === '') {
                    continue;
                }

                if (str_starts_with($href, 'javascript:') || str_starts_with($href, '#')) {
                    continue;
                }

                // Skip image-only links (no text content)
                if ($this->isImageOnlyLink($anchor)) {
                    continue;
                }

                $links[] = [
                    'href' => $href,
                    'text' => $text,
                    'title' => $title !== '' ? $title : null,
                ];
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        return $this->deduplicateLinks($links);
    }

    /**
     * @inheritDoc
     */
    public function extractLinksWithSource(string $blockHtml, string $blockName): array
    {
        $links = $this->extractLinks($blockHtml);

        return array_map(
            fn(array $link) => array_merge($link, ['block' => $blockName]),
            $links
        );
    }

    /**
     * @inheritDoc
     */
    public function filterLinks(array $links, string $pattern): array
    {
        return array_filter(
            $links,
            fn(array $link) => preg_match($pattern, $link['href']) === 1
        );
    }

    /**
     * Check if a link contains only an image (no text)
     */
    private function isImageOnlyLink(\DOMElement $anchor): bool
    {
        // Check if the only child is an img element
        $childNodes = $anchor->childNodes;

        if ($childNodes->length === 1) {
            $child = $childNodes->item(0);
            if ($child instanceof \DOMElement && $child->tagName === 'img') {
                return true;
            }
        }

        // Check if text content is just whitespace
        $textContent = trim($anchor->textContent);
        return $textContent === '';
    }

    /**
     * Remove duplicate links (same href)
     *
     * @param array<int, array{href: string, text: string, title: string|null}> $links
     * @return array<int, array{href: string, text: string, title: string|null}>
     */
    private function deduplicateLinks(array $links): array
    {
        $seen = [];
        $result = [];

        foreach ($links as $link) {
            $key = $link['href'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $link;
            }
        }

        return $result;
    }

    /**
     * Extract links matching a module pattern
     *
     * @param string $blockHtml Raw HTML from a block
     * @param string $moduleName Module name to match
     * @return array<int, array{href: string, text: string, title: string|null}>
     */
    public function extractModuleLinks(string $blockHtml, string $moduleName): array
    {
        $links = $this->extractLinks($blockHtml);
        return $this->filterLinks($links, '/name=' . preg_quote($moduleName, '/') . '/i');
    }

    /**
     * Categorize links by type
     *
     * @param array<int, array{href: string, text: string, title: string|null}> $links
     * @return array{team: array, stats: array, site: array, account: array, other: array}
     */
    public function categorizeLinks(array $links): array
    {
        $categories = [
            'team' => [],
            'stats' => [],
            'site' => [],
            'account' => [],
            'other' => [],
        ];

        $patterns = [
            'team' => '/name=(Team|Trade|FreeAgency|Waivers|Draft|DepthChart|Extension)/i',
            'stats' => '/name=(Stats|Leaders|Leaderboards|BoxScores|Schedule|Standings)/i',
            'account' => '/name=(Your_Account|Private_Messages)/i',
            'site' => '/name=(News|Topics|Web_Links|Forums|Search)/i',
        ];

        foreach ($links as $link) {
            $categorized = false;
            foreach ($patterns as $category => $pattern) {
                if (preg_match($pattern, $link['href'])) {
                    $categories[$category][] = $link;
                    $categorized = true;
                    break;
                }
            }
            if (!$categorized) {
                $categories['other'][] = $link;
            }
        }

        return $categories;
    }
}
