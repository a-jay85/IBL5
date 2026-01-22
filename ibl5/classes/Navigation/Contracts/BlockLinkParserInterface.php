<?php

declare(strict_types=1);

namespace Navigation\Contracts;

/**
 * Interface for Block Link Parser
 *
 * Extracts navigation links from HTML block content using DOM parsing.
 */
interface BlockLinkParserInterface
{
    /**
     * Extract all links from HTML block content
     *
     * @param string $blockHtml Raw HTML from a block
     * @return array<int, array{href: string, text: string, title: string|null}>
     */
    public function extractLinks(string $blockHtml): array;

    /**
     * Extract links with metadata about the block source
     *
     * @param string $blockHtml Raw HTML from a block
     * @param string $blockName Name/identifier of the source block
     * @return array<int, array{href: string, text: string, title: string|null, block: string}>
     */
    public function extractLinksWithSource(string $blockHtml, string $blockName): array;

    /**
     * Filter links by pattern
     *
     * @param array<int, array{href: string, text: string, title: string|null}> $links
     * @param string $pattern Regex pattern to match against href
     * @return array<int, array{href: string, text: string, title: string|null}>
     */
    public function filterLinks(array $links, string $pattern): array;
}
