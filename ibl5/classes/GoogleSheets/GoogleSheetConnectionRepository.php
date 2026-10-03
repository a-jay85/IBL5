<?php

declare(strict_types=1);

namespace GoogleSheets;

use BaseMysqliRepository;
use GoogleSheets\Contracts\GoogleSheetConnectionRepositoryInterface;

/**
 * GoogleSheetConnectionRepository - ibl_google_sheet_connections access.
 *
 * Every query is a prepared statement with bound parameters.
 *
 * @phpstan-import-type ConnectionRow from GoogleSheetConnectionRepositoryInterface
 *
 * @see GoogleSheetConnectionRepositoryInterface For method contracts
 */
class GoogleSheetConnectionRepository extends BaseMysqliRepository implements GoogleSheetConnectionRepositoryInterface
{
    private const MAX_ERROR_LENGTH = 255;

    /**
     * @see GoogleSheetConnectionRepositoryInterface::findByUserId()
     *
     * @return ConnectionRow|null
     */
    public function findByUserId(int $userId): ?array
    {
        /** @var ConnectionRow|null $row */
        $row = $this->fetchOne(
            'SELECT * FROM `ibl_google_sheet_connections` WHERE user_id = ?',
            'i',
            $userId
        );

        return $row;
    }

    /**
     * @see GoogleSheetConnectionRepositoryInterface::upsert()
     */
    public function upsert(int $userId, string $refreshTokenEnc, string $spreadsheetId, string $spreadsheetUrl): void
    {
        $this->execute(
            "INSERT INTO `ibl_google_sheet_connections` (user_id, refresh_token_enc, spreadsheet_id, spreadsheet_url)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                refresh_token_enc = VALUES(refresh_token_enc),
                spreadsheet_id = VALUES(spreadsheet_id),
                spreadsheet_url = VALUES(spreadsheet_url),
                status = 'active',
                broken_reason = NULL,
                refresh_pending = 0,
                last_error = NULL",
            'isss',
            $userId,
            $refreshTokenEnc,
            $spreadsheetId,
            $spreadsheetUrl
        );
    }

    /**
     * @see GoogleSheetConnectionRepositoryInterface::updateRefreshToken()
     */
    public function updateRefreshToken(int $userId, string $refreshTokenEnc): void
    {
        $this->execute(
            'UPDATE `ibl_google_sheet_connections` SET refresh_token_enc = ? WHERE user_id = ?',
            'si',
            $refreshTokenEnc,
            $userId
        );
    }

    /**
     * @see GoogleSheetConnectionRepositoryInterface::markAllActivePending()
     */
    public function markAllActivePending(): int
    {
        return $this->execute(
            "UPDATE `ibl_google_sheet_connections` SET refresh_pending = 1 WHERE status = 'active'",
            ''
        );
    }

    /**
     * @see GoogleSheetConnectionRepositoryInterface::findPending()
     *
     * @return list<ConnectionRow>
     */
    public function findPending(int $limit): array
    {
        /** @var list<ConnectionRow> $rows */
        $rows = $this->fetchAll(
            "SELECT * FROM `ibl_google_sheet_connections`
             WHERE status = 'active' AND refresh_pending = 1
             ORDER BY last_refresh_at IS NULL DESC, last_refresh_at ASC, id ASC
             LIMIT ?",
            'i',
            $limit
        );

        return $rows;
    }

    /**
     * @see GoogleSheetConnectionRepositoryInterface::markRefreshed()
     */
    public function markRefreshed(int $userId, string $status, ?string $error): void
    {
        $error = $error === null ? null : mb_substr($error, 0, self::MAX_ERROR_LENGTH);

        $this->execute(
            'UPDATE `ibl_google_sheet_connections`
             SET refresh_pending = 0, last_refresh_at = NOW(), last_refresh_status = ?, last_error = ?
             WHERE user_id = ?',
            'ssi',
            $status,
            $error,
            $userId
        );
    }

    /**
     * @see GoogleSheetConnectionRepositoryInterface::markBroken()
     */
    public function markBroken(int $userId, string $reason): void
    {
        $this->execute(
            "UPDATE `ibl_google_sheet_connections`
             SET status = 'broken', broken_reason = ?, refresh_pending = 0,
                 last_refresh_at = NOW(), last_refresh_status = 'error'
             WHERE user_id = ?",
            'si',
            $reason,
            $userId
        );
    }

    /**
     * @see GoogleSheetConnectionRepositoryInterface::deleteByUserId()
     */
    public function deleteByUserId(int $userId): void
    {
        $this->execute(
            'DELETE FROM `ibl_google_sheet_connections` WHERE user_id = ?',
            'i',
            $userId
        );
    }
}
