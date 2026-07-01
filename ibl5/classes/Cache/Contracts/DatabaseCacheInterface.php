<?php

declare(strict_types=1);

namespace Cache\Contracts;

/**
 * DatabaseCacheInterface - Generic key-value cache backed by a database table.
 *
 * Provides get/set/delete operations against the `cache` table.
 * Values are stored as JSON-encoded strings with an expiration timestamp.
 */
interface DatabaseCacheInterface
{
    /**
     * Retrieve a cached value by key.
     *
     * Returns null on cache miss, expired entry, corrupt JSON, or database error.
     *
     * @param string $key Cache key
     * @return array<mixed>|null Decoded array on hit, null on miss
     */
    public function get(string $key): ?array;

    /**
     * Store a value in the cache with a TTL.
     *
     * Uses REPLACE INTO for atomic upsert (old value goes directly to new value with no gap).
     * Silently fails on database errors (prepare failure, encode failure).
     *
     * @param string $key Cache key
     * @param array<mixed> $data Data to cache (must be JSON-encodable)
     * @param int $ttlSeconds Time-to-live in seconds
     */
    public function set(string $key, array $data, int $ttlSeconds): void;

    /**
     * Delete a cached value by key.
     *
     * Silently fails on database errors.
     *
     * @param string $key Cache key
     */
    public function delete(string $key): void;

    /**
     * Retrieve a cached value by key, IGNORING expiration.
     *
     * Returns the stored value even when past its expiration timestamp, so callers can
     * serve a stale-but-valid payload while an out-of-band refresh (e.g. cron) rebuilds.
     * Returns null only on a genuine miss, corrupt JSON, or database error.
     *
     * @param string $key Cache key
     * @return array<mixed>|null Decoded array if a row exists (fresh or stale), null otherwise
     */
    public function getStale(string $key): ?array;

    /**
     * Acquire a named single-flight lock, blocking up to $timeoutSeconds for it.
     *
     * Used to ensure only one request rebuilds an expensive cache entry at a time
     * (stampede / thundering-herd guard). Returns false on timeout or database error,
     * so a failed acquire must degrade safely (serve stale/empty, do not rebuild).
     *
     * @param string $key Lock name
     * @param int $timeoutSeconds Seconds to wait for the lock
     * @return bool True if the lock was acquired, false on timeout or error
     */
    public function acquireLock(string $key, int $timeoutSeconds): bool;

    /**
     * Release a named lock previously taken with acquireLock().
     *
     * Silently fails on database errors.
     *
     * @param string $key Lock name
     */
    public function releaseLock(string $key): void;
}
