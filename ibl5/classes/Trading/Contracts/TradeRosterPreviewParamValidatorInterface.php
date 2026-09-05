<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeRosterPreviewParamValidatorInterface - Contract for validating the
 * trade roster preview endpoint's `$_GET` query parameters.
 *
 * Each method reads its parameter directly from the `$_GET` superglobal and
 * returns a narrowed, validated value. Extracted verbatim from
 * TradeRosterPreviewApiHandler to isolate the pure (no-DB) param-validation
 * surface from rendering. No method touches the database.
 */
interface TradeRosterPreviewParamValidatorInterface
{
    /**
     * Validate teamid query parameter
     */
    public function validateTeamID(): int;

    /**
     * Validate a PID list query parameter (addPids or removePids)
     *
     * Returns empty array for empty/missing values (valid — no players to add/remove).
     * Returns null for invalid values (non-numeric, exceeds max).
     *
     * @return list<int>|null Validated PID list or null if invalid
     */
    public function validatePidList(string $paramName): ?array;

    /**
     * Validate display query parameter against whitelist
     */
    public function validateDisplay(): string;

    /**
     * Validate a string query parameter
     */
    public function validateStringParam(string $paramName): string;

    /**
     * Validate an integer query parameter (positive)
     */
    public function validateIntParam(string $paramName): int;

    /**
     * Validate a cash amount query parameter (0-2000)
     */
    public function validateCashAmount(string $paramName): int;

    /**
     * Validate cash year range parameters and enforce horizon bound.
     *
     * Returns [cashStartYear, cashEndYear] if valid; [0, 0] on any violation:
     *   - either year missing or non-digit
     *   - cashEndYear > $maxYear (over-horizon)
     *   - cashStartYear > cashEndYear (inverted ordering)
     *
     * @return array{0: int, 1: int}
     */
    public function validateCashYearRange(int $maxYear): array;
}
