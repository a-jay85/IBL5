<?php

declare(strict_types=1);

namespace Tests\GoogleSheets\Fakes;

use GoogleSheets\Contracts\GoogleSheetConnectionRepositoryInterface;

/**
 * In-memory stand-in for GoogleSheetConnectionRepository, keyed by user id.
 *
 * @phpstan-import-type ConnectionRow from GoogleSheetConnectionRepositoryInterface
 */
final class InMemoryConnectionRepository implements GoogleSheetConnectionRepositoryInterface
{
    /** @var array<int, ConnectionRow> */
    public array $rows = [];

    private int $nextId = 1;
    private int $tick = 0;

    public function findByUserId(int $userId): ?array
    {
        return $this->rows[$userId] ?? null;
    }

    public function upsert(int $userId, string $refreshTokenEnc, string $spreadsheetId, string $spreadsheetUrl): void
    {
        $existing = $this->rows[$userId] ?? null;
        $this->rows[$userId] = [
            'id' => $existing['id'] ?? $this->nextId++,
            'user_id' => $userId,
            'refresh_token_enc' => $refreshTokenEnc,
            'spreadsheet_id' => $spreadsheetId,
            'spreadsheet_url' => $spreadsheetUrl,
            'status' => 'active',
            'broken_reason' => null,
            'refresh_pending' => 0,
            'last_refresh_at' => $existing['last_refresh_at'] ?? null,
            'last_refresh_status' => $existing['last_refresh_status'] ?? null,
            'last_error' => null,
            'created_at' => $existing['created_at'] ?? '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
    }

    public function updateRefreshToken(int $userId, string $refreshTokenEnc): void
    {
        if (isset($this->rows[$userId])) {
            $this->rows[$userId]['refresh_token_enc'] = $refreshTokenEnc;
        }
    }

    public function markAllActivePending(): int
    {
        $count = 0;
        foreach ($this->rows as $userId => $row) {
            if ($row['status'] === 'active') {
                $this->rows[$userId]['refresh_pending'] = 1;
                $count++;
            }
        }

        return $count;
    }

    public function findPending(int $limit): array
    {
        $pending = array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => $row['status'] === 'active' && $row['refresh_pending'] === 1
        ));
        usort($pending, static function (array $a, array $b): int {
            if ($a['last_refresh_at'] === null || $b['last_refresh_at'] === null) {
                return ($b['last_refresh_at'] === null ? 1 : 0) - ($a['last_refresh_at'] === null ? 1 : 0);
            }

            return strcmp($a['last_refresh_at'], $b['last_refresh_at']);
        });

        return array_slice($pending, 0, $limit);
    }

    public function markRefreshed(int $userId, string $status, ?string $error): void
    {
        if (!isset($this->rows[$userId])) {
            return;
        }
        $this->rows[$userId]['refresh_pending'] = 0;
        $this->rows[$userId]['last_refresh_at'] = $this->now();
        $this->rows[$userId]['last_refresh_status'] = $status;
        $this->rows[$userId]['last_error'] = $error === null ? null : mb_substr($error, 0, 255);
    }

    public function markBroken(int $userId, string $reason): void
    {
        if (!isset($this->rows[$userId])) {
            return;
        }
        $this->rows[$userId]['status'] = 'broken';
        $this->rows[$userId]['broken_reason'] = $reason;
        $this->rows[$userId]['refresh_pending'] = 0;
        $this->rows[$userId]['last_refresh_at'] = $this->now();
        $this->rows[$userId]['last_refresh_status'] = 'error';
    }

    public function deleteByUserId(int $userId): void
    {
        unset($this->rows[$userId]);
    }

    private function now(): string
    {
        $this->tick++;

        return sprintf('2026-01-01 00:00:%02d', $this->tick % 60);
    }
}
