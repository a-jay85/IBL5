<?php

declare(strict_types=1);

namespace AwardHistory\Contracts;

/**
 * AwardHistoryValidatorInterface - Contract for player awards search validation
 * 
 * Defines the validation methods for sanitizing and validating user input
 * for player award searches. All validation methods prevent SQL injection
 * and ensure data integrity.
 */
interface AwardHistoryValidatorInterface
{
    /**
     * Validate all search parameters from form submission
     * 
     * Sanitizes and validates all search parameters, returning a normalized
     * array with safe values suitable for database queries.
     * 
     * @param array<string, mixed> $params Raw POST parameters from form
     * @return array{
     *     name: string|null,
     *     award: string|null,
     *     year: int|null,
     *     sortby: int
     * } Validated parameters:
     *     - name: Sanitized player name string or null if empty
     *     - award: Sanitized award name string or null if empty
     *     - year: Validated year integer or null if empty/invalid
     *     - sortby: Sort option (1=name, 2=award, 3=year), defaults to 3
     * 
     * IMPORTANT BEHAVIORS:
     *  - Empty/whitespace-only strings become null
     *  - Strings are trimmed and HTML tags removed
     *  - Year must be a positive integer to be valid
     *  - Invalid sortby values default to 3 (year)
     *  - Never throws exceptions – returns safe defaults
     */
    public function validateSearchParams(array $params): array;

    /**
     * Validate and sanitize a string parameter
     * 
     * @param mixed $value Raw input value
     * @return string|null Sanitized string or null if empty/invalid
     * 
     * IMPORTANT BEHAVIORS:
     *  - Trims whitespace from beginning and end
     *  - Removes HTML tags for security
     *  - Empty strings after trimming become null
     *  - Non-string values are cast to string first
     */
    public function validateStringParam(mixed $value): ?string;

    /**
     * Validate a year parameter
     * 
     * @param mixed $value Raw input value
     * @return int|null Validated year or null if invalid
     * 
     * IMPORTANT BEHAVIORS:
     *  - Must be a positive integer
     *  - Non-numeric values return null
     *  - Negative values return null
     *  - Zero returns null
     */
    public function validateYearParam(mixed $value): ?int;

    /**
     * Validate sort option parameter
     * 
     * @param mixed $value Raw input value
     * @return int Validated sort option (1, 2, or 3)
     * 
     * VALID OPTIONS:
     *  - 1: Sort by player name (ASC)
     *  - 2: Sort by award name (ASC)
     *  - 3: Sort by year (ASC) - DEFAULT
     * 
     * IMPORTANT BEHAVIORS:
     *  - Invalid values default to 3 (year)
     *  - Non-numeric values default to 3
     *  - Values outside 1-3 range default to 3
     */
    public function validateSortParam(mixed $value): int;
}
