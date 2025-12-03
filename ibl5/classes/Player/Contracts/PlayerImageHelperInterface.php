<?php

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
}
