<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerImageHelperInterface;
use Utilities\HtmlSanitizer;

/**
 * @see PlayerImageHelperInterface
 */
class PlayerImageHelper implements PlayerImageHelperInterface
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
        
        return HtmlSanitizer::e($basePath . $playerID . '.jpg');
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
    /**
     * @see PlayerImageHelperInterface::renderThumbnail()
     */
    public static function renderThumbnail($playerID, string $basePath = './images/player/'): string
    {
        $url = self::getImageUrl($playerID, $basePath);

        return '<img src="' . $url . '" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy">';
    }

    /**
     * Render a player photo img tag at specified dimensions
     *
     * @param int|string|null $playerID Player ID
     * @param string $alt Alt text for the image
     * @param int $width Image width
     * @param int $height Image height
     * @return string HTML img tag
     */
    public static function renderPhoto(int|string|null $playerID, string $alt = '', int $width = 65, int $height = 90): string
    {
        $url = self::getImageUrl($playerID);
        $safeAlt = HtmlSanitizer::safeHtmlOutput($alt);

        return '<img src="' . $url . '" alt="' . $safeAlt . '" width="' . $width . '" height="' . $height . '" loading="lazy">';
    }

    /**
     * Render a player cell with a large photo (65x90) and name link
     *
     * Used in RecordHolders and similar views that display player photos
     * at larger than thumbnail size.
     *
     * @param int $playerID Player ID
     * @param string $name Player name (will be HTML-escaped)
     * @param string $cellClass CSS class for the td element
     * @return string HTML td element with photo and linked name
     */
    public static function renderLargePlayerCell(int $playerID, string $name, string $cellClass = 'player-cell'): string
    {
        $safeName = HtmlSanitizer::safeHtmlOutput($name);
        $url = 'modules.php?name=Player&amp;pa=showpage&amp;pid=' . $playerID;
        $photo = self::renderPhoto($playerID, $name);

        return '<td class="' . $cellClass . '">'
            . '<a href="' . $url . '">' . $photo . '</a>'
            . '<a href="' . $url . '">' . $safeName . '</a>'
            . '</td>';
    }

    /**
     * @see PlayerImageHelperInterface::renderPlayerCell()
     */
    public static function renderPlayerCell(int $playerID, string $displayName, array $starterPids = [], string $nameStatusClass = ''): string
    {
        $hasPipe = str_contains($displayName, '|');
        $starterClass = in_array($playerID, $starterPids, true) ? ' is-starter' : '';
        $statusClass = $nameStatusClass !== '' ? ' ' . $nameStatusClass : '';

        // Cash rows (pipe-delimited names) get a single span — no thumbnail or abbreviation
        if ($hasPipe) {
            $cleanName = HtmlSanitizer::e(str_replace('|', '', strip_tags($displayName)));
            return '<td class="sticky-col ibl-player-cell' . $starterClass . '">'
                . '<span class="ibl-player-cell__name' . $statusClass . '">' . $cleanName . '</span>'
                . '</td>';
        }

        $thumbnail = self::renderThumbnail($playerID);
        $abbreviated = self::abbreviateFirstName($displayName);

        return '<td class="sticky-col ibl-player-cell' . $starterClass . '">'
            . '<a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=' . $playerID . '">'
            . $thumbnail
            . '<span class="ibl-player-cell__name ibl-player-cell__name--full' . $statusClass . '">' . $displayName . '</span>'
            . '<span class="ibl-player-cell__name ibl-player-cell__name--abbrev' . $statusClass . '">' . $abbreviated . '</span>'
            . '</a></td>';
    }

    /**
     * @see PlayerImageHelperInterface::renderPlayerLink()
     */
    public static function renderPlayerLink(int $playerID, string $rawName): string
    {
        $resolved = self::resolvePlayerDisplay($playerID, $rawName);
        $safeName = HtmlSanitizer::safeHtmlOutput($resolved['name']);

        return '<a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=' . $playerID . '">'
            . $resolved['thumbnail']
            . $safeName
            . '</a>';
    }

    /**
     * @see PlayerImageHelperInterface::renderFlexiblePlayerCell()
     */
    public static function renderFlexiblePlayerCell(
        int $playerID,
        string $rawName,
        string $extraClasses = '',
        array $starterPids = [],
    ): string {
        $starterClass = in_array($playerID, $starterPids, true) ? ' is-starter' : '';

        $classes = 'ibl-player-cell' . $starterClass;
        if ($extraClasses !== '') {
            $classes .= ' ' . $extraClasses;
        }

        return '<td class="' . $classes . '">'
            . self::renderPlayerLink($playerID, $rawName)
            . '</td>';
    }

    /**
     * @see PlayerImageHelperInterface::resolvePlayerDisplay()
     */
    public static function resolvePlayerDisplay(int $playerID, string $rawName): array
    {
        $hasPipe = str_contains($rawName, '|');
        $cleanName = $hasPipe ? str_replace('|', '', strip_tags($rawName)) : $rawName;
        $thumbnail = $hasPipe ? '' : self::renderThumbnail($playerID);

        return ['thumbnail' => $thumbnail, 'name' => $cleanName];
    }

    /**
     * Abbreviate a player's first name to a single initial.
     * e.g. "Andre Iguodala^" → "A. Iguodala^"
     */
    private static function abbreviateFirstName(string $displayName): string
    {
        $spacePos = strpos($displayName, ' ');
        if ($spacePos === false || $spacePos === 0) {
            return $displayName;
        }

        return mb_substr($displayName, 0, 1) . '.' . substr($displayName, $spacePos);
    }

    public static function isValidPlayerID(int|float|string|null $playerID): bool
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
