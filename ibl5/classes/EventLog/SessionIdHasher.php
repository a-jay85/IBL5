<?php

declare(strict_types=1);

namespace EventLog;

/**
 * Derives the stored ibl_events.session_id value from a raw PHP session id.
 *
 * Pure: no I/O, no globals, no session access. The caller reads the raw id
 * and passes it in. The raw token is never returned or stored; only its
 * SHA-256 hex digest is (design decision D5).
 *
 * The algorithm must stay sha256. Existing ibl_events.session_id rows were
 * written with it, and a different digest would split every returning
 * session from its history.
 */
final class SessionIdHasher
{
    /**
     * @return string|null 64 lowercase hex chars, or null when there is no id
     */
    public static function hash(?string $rawSessionId): ?string
    {
        if ($rawSessionId === null || $rawSessionId === '') {
            return null;
        }

        return hash('sha256', $rawSessionId);
    }
}
