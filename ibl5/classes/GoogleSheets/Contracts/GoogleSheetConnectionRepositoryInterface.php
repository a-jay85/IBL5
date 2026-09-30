<?php

declare(strict_types=1);

namespace GoogleSheets\Contracts;

/**
 * One row per connected GM in ibl_google_sheet_connections.
 *
 * Every method is scoped by the session user id. The refresh_token_enc column is a
 * Security\SecretBox ciphertext; callers must never render or log it.
 *
 * @phpstan-type ConnectionRow array{
 *     id: int,
 *     user_id: int,
 *     refresh_token_enc: string,
 *     spreadsheet_id: string,
 *     spreadsheet_url: string,
 *     status: string,
 *     broken_reason: ?string,
 *     refresh_pending: int,
 *     last_refresh_at: ?string,
 *     last_refresh_status: ?string,
 *     last_error: ?string,
 *     created_at: string,
 *     updated_at: string
 * }
 */
interface GoogleSheetConnectionRepositoryInterface
{
    public const REASON_INVALID_GRANT = 'invalid_grant';
    public const REASON_SHEET_MISSING = 'sheet_missing';
    public const REASON_KEY_UNAVAILABLE = 'key_unavailable';

    /**
     * Full row, including refresh_token_enc, or null when the user has no connection.
     *
     * @return ConnectionRow|null
     */
    public function findByUserId(int $userId): ?array;

    /**
     * Insert or replace the user's connection; a reconnect clears broken state.
     */
    public function upsert(int $userId, string $refreshTokenEnc, string $spreadsheetId, string $spreadsheetUrl): void;

    /**
     * Replace the stored ciphertext (key-rotation re-encrypt).
     */
    public function updateRefreshToken(int $userId, string $refreshTokenEnc): void;

    /**
     * Flag every active connection for refresh. Returns the affected-row count.
     */
    public function markAllActivePending(): int;

    /**
     * Active pending rows, never-refreshed first, then oldest refresh first.
     *
     * @return list<ConnectionRow>
     */
    public function findPending(int $limit): array;

    /**
     * Record a refresh attempt outcome and clear the pending flag.
     *
     * @param string $status One of ok, error, timeout
     * @param string|null $error Sanitized message; truncated to 255 characters
     */
    public function markRefreshed(int $userId, string $status, ?string $error): void;

    /**
     * Mark the connection broken and clear the pending flag.
     *
     * @param string $reason One of the REASON_* constants
     */
    public function markBroken(int $userId, string $reason): void;

    /**
     * Delete the user's connection row (disconnect).
     */
    public function deleteByUserId(int $userId): void;
}
