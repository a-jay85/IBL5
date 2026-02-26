<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\PlrFieldSerializerInterface;

/**
 * Serializes PHP values into fixed-width JSB .plr field format.
 *
 * All JSB numeric fields are right-justified and space-padded. This matches the
 * formatting confirmed in legacy scripts (e.g., plrAdvanceBirdYears.php).
 */
class PlrFieldSerializer implements PlrFieldSerializerInterface
{
    /**
     * @see PlrFieldSerializerInterface::formatInt()
     */
    public static function formatInt(int $value, int $width): string
    {
        $str = (string) $value;
        $len = strlen($str);

        if ($len > $width) {
            throw new \OverflowException(
                'Integer value ' . $value . ' requires ' . $len . ' characters but field width is ' . $width
            );
        }

        return str_pad($str, $width, ' ', STR_PAD_LEFT);
    }

    /**
     * @see PlrFieldSerializerInterface::formatRightString()
     */
    public static function formatRightString(string $value, int $width): string
    {
        $len = strlen($value);

        if ($len > $width) {
            throw new \OverflowException(
                'String value "' . $value . '" is ' . $len . ' characters but field width is ' . $width
            );
        }

        return str_pad($value, $width, ' ', STR_PAD_LEFT);
    }

    /**
     * @see PlrFieldSerializerInterface::toCP1252()
     */
    public static function toCP1252(string $utf8String): string
    {
        $result = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');
        if (!is_string($result)) {
            return $utf8String;
        }
        return $result;
    }
}
