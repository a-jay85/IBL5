<?php

declare(strict_types=1);

namespace Navigation\Contracts;

/**
 * Interface for Mobile Menu Builder
 *
 * Builds mobile navigation menus by extracting and grouping links from blocks.
 */
interface MobileMenuBuilderInterface
{
    /**
     * Build mobile menu from all blocks
     *
     * @return array{team: array, stats: array, site: array, account: array, other: array}
     */
    public function build(): array;

    /**
     * Build mobile menu from specific blocks
     *
     * @param array<string> $blockNames List of block names to process
     * @return array{team: array, stats: array, site: array, account: array, other: array}
     */
    public function buildFromBlocks(array $blockNames): array;

    /**
     * Get all available block names
     *
     * @return array<string>
     */
    public function getAvailableBlocks(): array;

    /**
     * Render the mobile menu as HTML
     *
     * @return string HTML content
     */
    public function render(): string;
}
