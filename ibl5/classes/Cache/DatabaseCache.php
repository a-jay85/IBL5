<?php

declare(strict_types=1);

namespace Cache;

use Cache\Contracts\DatabaseCacheInterface;
use Clock\ClockInterface;
use Clock\SystemClock;

/**
 * DatabaseCache - Generic key-value cache using the `cache` database table.
 *
 * Extracted from the pattern in CachedRecordHoldersService for reuse across modules.
 */
class DatabaseCache extends \BaseMysqliRepository implements DatabaseCacheInterface
{
    /** PSR-3 logger for cache-layer failure logging; defaults to the 'db' channel. */
    private \Psr\Log\LoggerInterface $channelLogger;

    private ClockInterface $clock;

    public function __construct(\mysqli $db, ?\Psr\Log\LoggerInterface $logger = null, ?ClockInterface $clock = null)
    {
        parent::__construct($db);
        $this->channelLogger = $logger ?? \Logging\LoggerFactory::getChannel('db');
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * @see DatabaseCacheInterface::get()
     *
     * @return array<mixed>|null
     */
    public function get(string $key): ?array
    {
        $row = $this->fetchRow($key);
        if ($row === null) {
            return null; // cache miss or DB error — already logged in fetchRow on error
        }

        if ($row['expiration'] < $this->clock->now()) {
            return null; // expired — not an error (getStale() ignores this branch)
        }

        return $this->decodeValue($key, $row['value']);
    }

    /**
     * @see DatabaseCacheInterface::getStale()
     *
     * @return array<mixed>|null
     */
    public function getStale(string $key): ?array
    {
        $row = $this->fetchRow($key);
        if ($row === null) {
            return null; // absent or DB error — already logged in fetchRow on error
        }

        // Deliberately IGNORE $row['expiration']: return the stored value even if stale.
        return $this->decodeValue($key, $row['value']);
    }

    /**
     * Fetch the raw cache row for a key, or null on miss / database error.
     *
     * @return array{value: string, expiration: int}|null
     */
    private function fetchRow(string $key): ?array
    {
        try {
            $row = $this->fetchOne(
                "SELECT `value`, `expiration` FROM `cache` WHERE `cache_key` = ?",
                's',
                $key
            );
        } catch (\RuntimeException $e) {
            $this->channelLogger->error('cache_get_failed', ['cache_key' => $key, 'error' => $e->getMessage()]);
            return null;
        }

        if ($row === null) {
            return null; // cache miss — NOT an error, do not log
        }

        /** @var array{value: string, expiration: int} $row */
        return $row;
    }

    /**
     * Decode a stored JSON cache value into an array, or null on corrupt JSON.
     *
     * @return array<mixed>|null
     */
    private function decodeValue(string $key, string $value): ?array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->channelLogger->warning('cache_corrupt_json', ['cache_key' => $key, 'error' => $e->getMessage()]);
            return null;
        }
        if (!is_array($decoded)) {
            $this->channelLogger->warning('cache_corrupt_json', ['cache_key' => $key, 'error' => 'decoded value is not an array']);
            return null;
        }

        /** @var array<mixed> $decoded */
        return $decoded;
    }

    /**
     * @see DatabaseCacheInterface::set()
     *
     * @param array<mixed> $data
     */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            $this->channelLogger->warning('cache_set_encode_failed', ['cache_key' => $key, 'error' => json_last_error_msg()]);
            return;
        }

        $expiration = $this->clock->now() + $ttlSeconds;
        try {
            $this->execute(
                "REPLACE INTO `cache` (`cache_key`, `value`, `expiration`) VALUES (?, ?, ?)",
                'ssi',
                $key,
                $encoded,
                $expiration
            );
        } catch (\RuntimeException $e) {
            $this->channelLogger->error('cache_set_failed', ['cache_key' => $key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @see DatabaseCacheInterface::delete()
     */
    public function delete(string $key): void
    {
        try {
            $this->execute("DELETE FROM `cache` WHERE `cache_key` = ?", 's', $key);
        } catch (\RuntimeException $e) {
            $this->channelLogger->error('cache_delete_failed', ['cache_key' => $key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @see DatabaseCacheInterface::acquireLock()
     */
    public function acquireLock(string $key, int $timeoutSeconds): bool
    {
        try {
            $row = $this->fetchOne("SELECT GET_LOCK(?, ?) AS got", 'si', $key, $timeoutSeconds);
        } catch (\RuntimeException $e) {
            $this->channelLogger->error('cache_lock_acquire_failed', ['lock_key' => $key, 'error' => $e->getMessage()]);
            return false;
        }

        if ($row === null) {
            return false; // GET_LOCK returned NULL (error/killed) — do not rebuild
        }

        /** @var array{got: int|string|null} $row */
        return (int) ($row['got'] ?? 0) === 1;
    }

    /**
     * @see DatabaseCacheInterface::releaseLock()
     */
    public function releaseLock(string $key): void
    {
        try {
            // DO (not SELECT) avoids leaving an unconsumed result set → "commands out of sync".
            $this->execute("DO RELEASE_LOCK(?)", 's', $key);
        } catch (\RuntimeException $e) {
            $this->channelLogger->error('cache_lock_release_failed', ['lock_key' => $key, 'error' => $e->getMessage()]);
        }
    }
}
