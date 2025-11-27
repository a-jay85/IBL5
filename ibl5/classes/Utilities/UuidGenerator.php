<?php

declare(strict_types=1);

namespace Utilities;

/**
 * UUID Generator Utility
 *
 * Centralized static utility for generating UUID v4 identifiers.
 * Used throughout the application for creating unique IDs for database records.
 *
 * @package Utilities
 */
class UuidGenerator
{
    /**
     * Generate a UUID v4 string
     *
     * Generates a random UUID v4 format identifier (8-4-4-4-12 hex digits).
     * Safe for use in database inserts and public API identifiers.
     *
     * @return string UUID in format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     */
    public static function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
