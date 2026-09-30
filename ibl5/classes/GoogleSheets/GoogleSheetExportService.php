<?php

declare(strict_types=1);

namespace GoogleSheets;

use Api\Repository\ApiPlayerRepository;
use Api\Transformer\PlayerExportTransformer;
use GoogleSheets\Contracts\GoogleSheetConnectionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Security\SecretBox;
use Security\SecretBoxDecryptException;

/**
 * GoogleSheetExportService - connect, refresh, and disconnect a GM's export sheet.
 *
 * refreshConnection() is the per-GM isolation boundary: every outcome is recorded
 * on that GM's row as ok, error, or broken, and nothing is thrown out of it.
 * Tokens never reach a log line, an exception message, or last_error.
 *
 * @phpstan-import-type ConnectionRow from GoogleSheetConnectionRepositoryInterface
 */
class GoogleSheetExportService
{
    public const RESULT_OK = 'ok';
    public const RESULT_ERROR = 'error';
    public const RESULT_BROKEN = 'broken';
    public const RESULT_MISSING = 'missing';

    private const MIN_ROWS = 2000;
    private const MIN_COLUMNS = 64;

    public function __construct(
        private readonly GoogleSheetConnectionRepositoryInterface $connections,
        private readonly GoogleOAuthClient $oauth,
        private readonly GoogleSheetsClient $sheets,
        private readonly SecretBox $box,
        private readonly ApiPlayerRepository $players,
        private readonly PlayerExportTransformer $transformer,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Header row followed by one row per exported player (same source as the CSV export).
     *
     * @return list<list<string>>
     */
    public function buildRows(): array
    {
        $rows = [$this->transformer->getHeaders()];
        foreach ($this->players->getAllPlayersForExport() as $player) {
            $rows[] = $this->transformer->transform($player);
        }

        return $rows;
    }

    /**
     * Exchange the OAuth code, pick or create the sheet, store the connection, fill it once.
     *
     * Google and SecretBox exceptions propagate; nothing is stored when the exchange fails.
     *
     * @return ConnectionRow
     */
    public function connect(int $userId, string $code): array
    {
        $tokens = $this->oauth->exchangeCode($code);
        $refreshTokenEnc = $this->box->encrypt($tokens->refreshToken);

        $existing = $this->connections->findByUserId($userId);
        $spreadsheet = null;
        if ($existing !== null && $existing['spreadsheet_id'] !== '') {
            try {
                $this->sheets->ensureTab(
                    $tokens->accessToken,
                    $existing['spreadsheet_id'],
                    GoogleSheetsClient::PLAYERS_TAB,
                    self::MIN_ROWS,
                    self::MIN_COLUMNS
                );
                $spreadsheet = ['id' => $existing['spreadsheet_id'], 'url' => $existing['spreadsheet_url']];
            } catch (GoogleSheetMissingException) {
                $spreadsheet = null;
            }
        }
        if ($spreadsheet === null) {
            $spreadsheet = $this->sheets->createSpreadsheet($tokens->accessToken, GoogleSheetsClient::SHEET_TITLE);
        }

        $this->connections->upsert($userId, $refreshTokenEnc, $spreadsheet['id'], $spreadsheet['url']);
        $this->log('info', 'google sheet connected', ['user_id' => $userId]);

        $row = $this->connections->findByUserId($userId);
        if ($row === null) {
            throw new \RuntimeException('Google Sheet connection was not stored');
        }
        $this->refreshConnection($row);

        return $this->connections->findByUserId($userId) ?? $row;
    }

    /**
     * Manual refresh for the session user. Broken rows make no Google call.
     *
     * @return string One of the RESULT_* constants
     */
    public function refreshForUser(int $userId): string
    {
        $row = $this->connections->findByUserId($userId);
        if ($row === null) {
            return self::RESULT_MISSING;
        }
        if ($row['status'] === 'broken') {
            return self::RESULT_BROKEN;
        }

        return $this->refreshConnection($row);
    }

    /**
     * Write the current export to one GM's sheet. Never throws.
     *
     * @param ConnectionRow $row
     * @return string One of RESULT_OK, RESULT_ERROR, RESULT_BROKEN
     */
    public function refreshConnection(array $row): string
    {
        $userId = $row['user_id'];
        $started = hrtime(true);

        try {
            try {
                $refreshToken = $this->box->decrypt($row['refresh_token_enc']);
            } catch (SecretBoxDecryptException) {
                return $this->broken($userId, GoogleSheetConnectionRepositoryInterface::REASON_KEY_UNAVAILABLE, $started);
            }

            try {
                $accessToken = $this->oauth->refreshAccessToken($refreshToken);
            } catch (GoogleGrantRevokedException) {
                return $this->broken($userId, GoogleSheetConnectionRepositoryInterface::REASON_INVALID_GRANT, $started);
            }

            if ($this->box->needsReencrypt($row['refresh_token_enc'])) {
                $this->connections->updateRefreshToken($userId, $this->box->encrypt($refreshToken));
            }

            $rows = $this->buildRows();
            try {
                $this->sheets->ensureTab(
                    $accessToken,
                    $row['spreadsheet_id'],
                    GoogleSheetsClient::PLAYERS_TAB,
                    max(self::MIN_ROWS, count($rows) + 1),
                    self::MIN_COLUMNS
                );
                $this->sheets->overwriteTab($accessToken, $row['spreadsheet_id'], GoogleSheetsClient::PLAYERS_TAB, $rows);
            } catch (GoogleSheetMissingException) {
                return $this->broken($userId, GoogleSheetConnectionRepositoryInterface::REASON_SHEET_MISSING, $started);
            }

            $this->connections->markRefreshed($userId, self::RESULT_OK, null);
            $this->log('info', 'google sheet refreshed', [
                'user_id' => $userId,
                'status' => self::RESULT_OK,
                'elapsed_ms' => $this->elapsedMs($started),
            ]);

            return self::RESULT_OK;
        } catch (GoogleApiException $e) {
            return $this->error($userId, $e->status . ' ' . $e->reason, $e->reason, $started);
        } catch (GoogleHttpException) {
            return $this->error($userId, '0 transport_error', 'transport_error', $started);
        } catch (\Throwable $e) {
            $reason = 'internal:' . get_class($e);
            try {
                $this->connections->markRefreshed($userId, self::RESULT_ERROR, $reason);
            } catch (\Throwable) {
                // The row write itself failed; the log line below is the only record.
            }
            $this->log('error', 'google sheet refresh failed', [
                'user_id' => $userId,
                'status' => self::RESULT_ERROR,
                'reason' => $reason,
                'elapsed_ms' => $this->elapsedMs($started),
            ]);

            return self::RESULT_ERROR;
        }
    }

    /**
     * Revoke at Google (best effort) and delete the row. The sheet stays in Drive.
     */
    public function disconnect(int $userId): void
    {
        $row = $this->connections->findByUserId($userId);
        if ($row === null) {
            return;
        }

        $alreadyRevoked = $row['status'] === 'broken'
            && $row['broken_reason'] === GoogleSheetConnectionRepositoryInterface::REASON_INVALID_GRANT;
        $revoked = false;
        if (!$alreadyRevoked) {
            try {
                $revoked = $this->oauth->revoke($this->box->decrypt($row['refresh_token_enc']));
            } catch (SecretBoxDecryptException) {
                $revoked = false;
            }
        }

        $this->connections->deleteByUserId($userId);
        $this->log('info', 'google sheet disconnected', ['user_id' => $userId, 'revoked' => $revoked]);
    }

    /**
     * The only connection shape handed to the view: no token, no spreadsheet id.
     *
     * @return array{status: string, broken_reason: ?string, spreadsheet_url: string, last_refresh_at: ?string, last_refresh_status: ?string, last_error: ?string}|null
     */
    public function connectionSummaryFor(int $userId): ?array
    {
        $row = $this->connections->findByUserId($userId);
        if ($row === null) {
            return null;
        }

        return [
            'status' => $row['status'],
            'broken_reason' => $row['broken_reason'],
            'spreadsheet_url' => $row['spreadsheet_url'],
            'last_refresh_at' => $row['last_refresh_at'],
            'last_refresh_status' => $row['last_refresh_status'],
            'last_error' => $row['last_error'],
        ];
    }

    private function broken(int $userId, string $reason, int $started): string
    {
        $this->connections->markBroken($userId, $reason);
        $this->log('warning', 'google sheet connection broken', [
            'user_id' => $userId,
            'status' => self::RESULT_BROKEN,
            'reason' => $reason,
            'elapsed_ms' => $this->elapsedMs($started),
        ]);

        return self::RESULT_BROKEN;
    }

    private function error(int $userId, string $sanitized, string $reason, int $started): string
    {
        $this->connections->markRefreshed($userId, self::RESULT_ERROR, $sanitized);
        $this->log('warning', 'google sheet refresh failed', [
            'user_id' => $userId,
            'status' => self::RESULT_ERROR,
            'reason' => $reason,
            'elapsed_ms' => $this->elapsedMs($started),
        ]);

        return self::RESULT_ERROR;
    }

    private function elapsedMs(int $started): int
    {
        return intdiv(hrtime(true) - $started, 1_000_000);
    }

    /**
     * @param array<string, int|string|bool> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        $this->logger?->log($level, $message, $context);
    }
}
