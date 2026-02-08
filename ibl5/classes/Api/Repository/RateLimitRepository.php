<?php

declare(strict_types=1);

namespace Api\Repository;

class RateLimitRepository extends \BaseMysqliRepository
{
    /**
     * Increment the request count for the current minute window.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert.
     */
    public function increment(string $keyHash): void
    {
        $this->execute(
            "INSERT INTO ibl_api_rate_limits (api_key_hash, window_start, request_count)
             VALUES (?, DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:00'), 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1",
            's',
            $keyHash
        );
    }

    /**
     * Get the total request count for the current minute window.
     */
    public function getRequestCount(string $keyHash): int
    {
        $row = $this->fetchOne(
            "SELECT request_count FROM ibl_api_rate_limits
             WHERE api_key_hash = ? AND window_start = DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:00')",
            's',
            $keyHash
        );

        if ($row === null) {
            return 0;
        }

        /** @var int $count */
        $count = $row['request_count'];
        return $count;
    }

    /**
     * Probabilistic cleanup of old rate limit rows (older than 5 minutes).
     * Called with ~1% probability on each request.
     */
    public function pruneOldEntries(): void
    {
        $this->execute(
            'DELETE FROM ibl_api_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
        );
    }
}
