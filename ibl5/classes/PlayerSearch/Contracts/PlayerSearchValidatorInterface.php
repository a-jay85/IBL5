<?php

declare(strict_types=1);

namespace PlayerSearch\Contracts;

/**
 * PlayerSearchValidatorInterface - Contract for search parameter validation
 * 
 * Defines the validation contract that Claude and other code must follow
 * when validating player search form submissions.
 * 
 * This interface eliminates guessing about method names, signatures, and return types.
 * All validation methods return nullable values – never throw exceptions.
 */
interface PlayerSearchValidatorInterface
{
    /**
     * Validate and sanitize all search parameters from form submission
     * 
     * Returns an array with all possible search parameters as keys.
     * Values are either the validated input or null (indicating filter not applied).
     * 
     * @param array<string, mixed> $params Raw POST parameters
     * @return array<string, int|string|null> Validated parameters with these keys:
     *     - pos: string|null (PG, SG, SF, PF, C)
     *     - age: int|null
     *     - search_name: string|null
     *     - college: string|null
     *     - exp: int|null
     *     - exp_max: int|null
     *     - bird: int|null
     *     - bird_max: int|null
     *     - r_fga: int|null through r_foul: int|null (rating filters)
     *     - Clutch: int|null through intangibles: int|null (attribute ratings)
     *     - oo: int|null through td: int|null (skill ratings)
     *     - active: int|null (0, 1, or null)
     * 
     * IMPORTANT BEHAVIORS:
     *  - NEVER throws exceptions – invalid values are converted to null
     *  - Null values indicate the filter should not be applied
     *  - Position values are normalized to uppercase
     *  - Negative integers are converted to null
     *  - String lengths are limited to 64 characters
     *  - Boolean params are normalized to 0 or 1
     * 
     * Examples:
     *  - validateSearchParams(['pos' => 'pg']) returns ['pos' => 'PG', ...]
     *  - validateSearchParams(['age' => 'abc']) returns ['age' => null, ...]
     *  - validateSearchParams([]) returns all null values
     */
    public function validateSearchParams(array $params): array;

    /**
     * Validate position parameter against whitelist
     * 
     * @param mixed $value Raw position value from form (can be string, int, null, etc.)
     * @return string|null Validated position ('PG', 'SG', 'SF', 'PF', 'C') or null if invalid
     * 
     * IMPORTANT BEHAVIORS:
     *  - Input is normalized to uppercase
     *  - Only whitelisted positions are accepted
     *  - Returns null for invalid, empty, or null input
     *  - NEVER throws exceptions
     * 
     * Examples:
     *  - validatePosition('PG') returns 'PG'
     *  - validatePosition('pg') returns 'PG' (normalized)
     *  - validatePosition('INVALID') returns null
     *  - validatePosition(null) returns null
     *  - validatePosition('') returns null
     */
    public function validatePosition(mixed $value): ?string;

    /**
     * Validate integer parameter for ratings and skill values
     * 
     * @param mixed $value Raw value from form (can be string, int, null, etc.)
     * @return int|null Validated integer or null if empty/invalid/negative
     * 
     * IMPORTANT BEHAVIORS:
     *  - Negative values are converted to null
     *  - Non-numeric strings are converted to null
     *  - Empty strings and null return null
     *  - Numeric strings are cast to integers
     *  - NEVER throws exceptions
     * 
     * Examples:
     *  - validateIntegerParam('25') returns 25
     *  - validateIntegerParam(25) returns 25
     *  - validateIntegerParam('-5') returns null (negative)
     *  - validateIntegerParam('abc') returns null (non-numeric)
     *  - validateIntegerParam(null) returns null
     *  - validateIntegerParam('') returns null
     */
    public function validateIntegerParam(mixed $value): ?int;

    /**
     * Validate string parameter for name and college searches
     * 
     * @param mixed $value Raw value from form (can be string, int, null, etc.)
     * @return string|null Trimmed, sanitized string or null if empty
     * 
     * IMPORTANT BEHAVIORS:
     *  - Input is trimmed of whitespace
     *  - Strings longer than 64 characters are truncated
     *  - Empty strings and null return null
     *  - All string values are safe for LIKE queries (prepared statements used)
     *  - NEVER throws exceptions
     * 
     * Examples:
     *  - validateStringParam('  John Smith  ') returns 'John Smith'
     *  - validateStringParam('John' . str_repeat('x', 100)) returns 'John' + 60 chars
     *  - validateStringParam(null) returns null
     *  - validateStringParam('') returns null
     *  - validateStringParam('   ') returns null (whitespace-only)
     */
    public function validateStringParam(mixed $value): ?string;

    /**
     * Validate boolean/flag parameter
     * 
     * @param mixed $value Raw value from form
     * @return int|null 0 (false), 1 (true), or null (not specified)
     * 
     * IMPORTANT BEHAVIORS:
     *  - Accepts numeric 0 or 1
     *  - Accepts string '0' or '1'
     *  - All other values return null
     *  - Return value is always an integer 0 or 1, never boolean
     *  - NEVER throws exceptions
     * 
     * Examples:
     *  - validateBooleanParam(1) returns 1
     *  - validateBooleanParam('1') returns 1
     *  - validateBooleanParam(0) returns 0
     *  - validateBooleanParam('0') returns 0
     *  - validateBooleanParam(true) returns null (boolean type rejected)
     *  - validateBooleanParam(null) returns null
     *  - validateBooleanParam('') returns null
     *  - validateBooleanParam('yes') returns null
     */
    public function validateBooleanParam(mixed $value): ?int;
}
