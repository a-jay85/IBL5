<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerImageHelperInterface - Contract for player image URL generation
 * 
 * Defines the interface for safely generating player image URLs.
 * Prevents 404 errors by validating playerID before generating paths.
 */
interface PlayerImageHelperInterface
{
    /**
     * Generate a safe player image URL
     * 
     * Validates that playerID is a valid positive integer before generating the URL.
     * If playerID is missing, null, zero, or otherwise invalid, returns a placeholder
     * data URI (1x1 transparent PNG) instead of a URL that would result in a 404 error.
     * 
     * Valid playerID returns a path like: "./images/player/123.jpg"
     * Invalid playerID returns a base64-encoded data URI for transparent pixel.
     * 
     * @param int|string|null $playerID The player's ID to use in the image path
     * @param string $basePath Optional base path prefix (default: './images/player/')
     * @return string Safe image URL (e.g., "./images/player/123.jpg") or placeholder data URI
     * 
     * @example
     * // Valid playerID
     * $url = PlayerImageHelper::getImageUrl(123);  // "./images/player/123.jpg"
     * 
     * // Invalid playerID returns data URI (prevents 404)
     * $url = PlayerImageHelper::getImageUrl(null);   // data:image/png;base64,...
     * $url = PlayerImageHelper::getImageUrl('');     // data:image/png;base64,...
     * $url = PlayerImageHelper::getImageUrl(0);      // data:image/png;base64,...
     * $url = PlayerImageHelper::getImageUrl(-5);     // data:image/png;base64,...
     * 
     * @see https://www.w3schools.com/CSS/css_rwd_images.asp - Responsive images best practices
     */
    public static function getImageUrl($playerID, string $basePath = './images/player/'): string;

    /**
     * Render a player thumbnail <img> tag with lazy loading
     *
     * Returns a complete <img> element with class="ibl-player-photo", 24x24 dimensions,
     * and loading="lazy". Uses getImageUrl() internally for safe URL generation.
     *
     * @param int|string|null $playerID The player's ID
     * @param string $basePath Optional base path prefix (default: './images/player/')
     * @return string Complete <img> HTML tag
     */
    public static function renderThumbnail($playerID, string $basePath = './images/player/'): string;

    /**
     * Render a complete player name table cell with photo thumbnail
     *
     * Returns a <td> element with sticky-col positioning, player photo thumbnail,
     * linked player name, and optional starter highlighting. Used across all table
     * types (Ratings, Season Totals, Season Averages, Per 36 Minutes, Sim Averages,
     * Contracts) for consistent player name cell rendering.
     *
     * @param int $playerID The player's ID
     * @param string $displayName The player's display name (already decorated/sanitized)
     * @param array<int> $starterPids Array of starter player IDs for highlighting
     * @return string Complete <td> HTML element
     */
    public static function renderPlayerCell(int $playerID, string $displayName, array $starterPids = []): string;

    /**
     * Resolve a player's display name and thumbnail, handling pipe-delimited names.
     *
     * Names containing '|' indicate the player should not show a photo thumbnail.
     * The pipe character and any HTML tags are stripped from the returned display name.
     * For normal names, a thumbnail is generated via renderThumbnail().
     *
     * @param int $playerID The player's ID
     * @param string $rawName The raw player name (may contain '|' and HTML tags)
     * @return array{thumbnail: string, name: string} Thumbnail HTML (or '') and cleaned name
     */
    public static function resolvePlayerDisplay(int $playerID, string $rawName): array;
}
