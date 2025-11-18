<?php

declare(strict_types=1);

namespace Player;

/**
 * PlayerImageHelper - Safely generates player image URLs
 * 
 * Provides a centralized method to generate player image URLs with validation
 * to prevent 404 errors when playerID is missing, null, or invalid.
 * 
 * This helper ensures consistent handling of player images across the application.
 * When a playerID is invalid, returns a data URI for a 1x1 transparent pixel instead
 * of a URL that would result in a 404 error.
 * 
 * @package Player
 */
class PlayerImageHelper
{
    /**
     * Data URI for a 1x1 fully transparent PNG pixel (prevents 404 errors without needing a file)
     * This is a valid PNG image that can be used as a placeholder.
     */
    private const PLACEHOLDER_DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==';
    
    /**
     * Generate a safe player image URL
     * 
     * Validates that playerID is a valid positive integer before generating the URL.
     * Returns a data URI placeholder (1x1 transparent pixel) if playerID is missing, null, or invalid.
     * This approach prevents 404 errors entirely.
     * 
     * @param int|string|null $playerID The player's ID to use in the image path
     * @param string $basePath Optional base path prefix (default: './images/player/')
     * @return string Safe image URL or placeholder data URI if playerID is invalid
     * 
     * @example
     * // Valid playerID
     * echo PlayerImageHelper::getImageUrl(123);  // './images/player/123.jpg'
     * 
     * // Null or missing playerID returns data URI (no 404 error)
     * echo PlayerImageHelper::getImageUrl(null);  // data:image/png;base64,...
     * echo PlayerImageHelper::getImageUrl('');    // data:image/png;base64,...
     * echo PlayerImageHelper::getImageUrl(0);     // data:image/png;base64,...
     */
    public static function getImageUrl($playerID, string $basePath = './images/player/'): string
    {
        // Validate playerID is a positive integer
        if (!self::isValidPlayerID($playerID)) {
            return self::PLACEHOLDER_DATA_URI;
        }
        
        // Cast to int and ensure it's positive
        $playerID = (int) $playerID;
        if ($playerID <= 0) {
            return self::PLACEHOLDER_DATA_URI;
        }
        
        return htmlspecialchars($basePath . $playerID . '.jpg', ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Check if a given playerID is valid
     * 
     * @param int|string|null $playerID The playerID to validate
     * @return bool True if playerID is valid and safe to use in URL, false otherwise
     * 
     * A valid playerID must be:
     * - Not null
     * - Not an empty string
     * - Numeric (convertible to int)
     * - Greater than zero when converted to int
     */
    public static function isValidPlayerID($playerID): bool
    {
        // Null or empty string
        if ($playerID === null || $playerID === '') {
            return false;
        }
        
        // Must be numeric (int or string representation of int)
        if (!is_numeric($playerID)) {
            return false;
        }
        
        // Must convert to positive integer
        $intID = (int) $playerID;
        return $intID > 0;
    }
}
