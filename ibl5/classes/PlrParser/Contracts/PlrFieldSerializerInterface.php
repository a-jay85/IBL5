<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

/**
 * Interface for serializing PHP values into fixed-width JSB .plr field format.
 *
 * JSB uses right-justified, space-padded fields for all numeric and name values.
 * Encoding is Windows-1252 (CP1252) for player names with accented characters.
 */
interface PlrFieldSerializerInterface
{
    /**
     * Format an integer as a right-justified, space-padded string.
     *
     * @param int $value The integer value to format
     * @param int $width The fixed width of the field
     * @return string Right-justified, space-padded string of exactly $width characters
     * @throws \OverflowException If the string representation exceeds $width characters
     */
    public static function formatInt(int $value, int $width): string;

    /**
     * Format a string as right-justified, space-padded to a fixed width.
     *
     * @param string $value The string value
     * @param int $width The fixed width of the field
     * @return string Right-justified, space-padded string of exactly $width characters
     * @throws \OverflowException If the value exceeds $width characters
     */
    public static function formatRightString(string $value, int $width): string;

    /**
     * Convert a UTF-8 string to Windows-1252 (CP1252) encoding.
     *
     * JSB uses CP1252 for player names with accented characters (e.g., José → Jos\xe9).
     *
     * @param string $utf8String The UTF-8 encoded string
     * @return string The CP1252 encoded string
     */
    public static function toCP1252(string $utf8String): string;

    /**
     * Convert a Windows-1252 (CP1252) encoded string to UTF-8.
     *
     * The canonical read-direction decode for the JSB parser cluster: all .plr,
     * .rcb, .dra, and .awa name fields route through here so the decode semantics
     * stay consistent (mb_convert_encoding with a null-safe fallback). Must be
     * called after fixed-width substr/trim extraction, never on whole-file bytes.
     *
     * @param string $cp1252String The Windows-1252 (CP1252) encoded string
     * @return string The UTF-8 encoded string
     */
    public static function toUtf8(string $cp1252String): string;
}
