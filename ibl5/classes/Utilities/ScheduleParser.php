<?php

declare(strict_types=1);

namespace Utilities;

/**
 * ScheduleParser - Parse schedule-related HTML elements
 * 
 * Extracts box IDs and other data from JSB HTML export files
 */
class ScheduleParser
{
    /**
     * Extract box ID from a box score link (e.g., "box12345.htm" -> "12345")
     */
    public static function extractBoxID(string $boxHREF): string
    {
        return ltrim(rtrim($boxHREF, '.htm'), 'box');
    }
}
