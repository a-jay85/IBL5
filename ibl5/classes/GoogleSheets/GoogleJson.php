<?php

declare(strict_types=1);

namespace GoogleSheets;

/**
 * Decode a Google response body. Malformed or empty JSON yields null, which every
 * caller already treats as "field missing".
 */
final class GoogleJson
{
    public static function decode(string $body): mixed
    {
        if ($body === '') {
            return null;
        }
        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
