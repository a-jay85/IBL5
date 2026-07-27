<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradeRosterPreviewParamValidatorInterface;

/**
 * Validates the trade roster preview endpoint's `$_GET` query parameters.
 *
 * Extracted verbatim from TradeRosterPreviewApiHandler (ADR-0001). Pure — reads
 * only `$_GET`, never the database, so it takes no `\mysqli`. Each method
 * reproduces the handler's original validation exactly; behaviour must not change.
 */
class TradeRosterPreviewParamValidator implements TradeRosterPreviewParamValidatorInterface
{
    private const VALID_DISPLAY_MODES = [
        'ratings',
        'total_s',
        'avg_s',
        'per36mins',
        'contracts',
        'split',
        'chunk',
        'playoffs',
    ];

    private const MAX_PIDS = 20;

    /**
     * Validate teamid query parameter
     */
    public function validateTeamID(): int
    {
        if (!isset($_GET['teamid']) || !is_string($_GET['teamid'])) {
            return 0;
        }

        $raw = $_GET['teamid'];
        if (!ctype_digit($raw) || $raw === '0') {
            return 0;
        }

        return (int) $raw;
    }

    /**
     * Validate a PID list query parameter (addPids or removePids)
     *
     * Returns empty array for empty/missing values (valid — no players to add/remove).
     * Returns null for invalid values (non-numeric, exceeds max).
     *
     * @return list<int>|null Validated PID list or null if invalid
     */
    public function validatePidList(string $paramName): ?array
    {
        if (!isset($_GET[$paramName]) || !is_string($_GET[$paramName])) {
            return [];
        }

        $raw = $_GET[$paramName];
        if ($raw === '') {
            return [];
        }

        $parts = explode(',', $raw);
        if (count($parts) > self::MAX_PIDS) {
            return null;
        }

        $pids = [];
        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '' || !ctype_digit($trimmed)) {
                return null;
            }
            $pids[] = (int) $trimmed;
        }

        return $pids;
    }

    /**
     * Validate display query parameter against whitelist
     */
    public function validateDisplay(): string
    {
        if (!isset($_GET['display']) || !is_string($_GET['display'])) {
            return 'ratings';
        }

        $raw = $_GET['display'];
        if (in_array($raw, self::VALID_DISPLAY_MODES, true)) {
            return $raw;
        }

        return 'ratings';
    }

    /**
     * Validate a string query parameter
     */
    public function validateStringParam(string $paramName): string
    {
        if (!isset($_GET[$paramName]) || !is_string($_GET[$paramName])) {
            return '';
        }
        $raw = trim($_GET[$paramName]);
        return $raw !== '' ? $raw : '';
    }

    /**
     * Validate an integer query parameter (positive)
     */
    public function validateIntParam(string $paramName): int
    {
        if (!isset($_GET[$paramName]) || !is_string($_GET[$paramName])) {
            return 0;
        }
        $raw = $_GET[$paramName];
        if (!ctype_digit($raw)) {
            return 0;
        }
        return (int) $raw;
    }

    /**
     * Validate a cash amount query parameter (0-2000)
     */
    public function validateCashAmount(string $paramName): int
    {
        if (!isset($_GET[$paramName]) || !is_string($_GET[$paramName])) {
            return 0;
        }
        $raw = $_GET[$paramName];
        if (!ctype_digit($raw)) {
            return 0;
        }
        $amount = (int) $raw;
        if ($amount > 2000) {
            return 0;
        }
        return $amount;
    }
}
