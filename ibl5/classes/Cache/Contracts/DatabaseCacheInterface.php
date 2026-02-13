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
     * @return list<array<string, mixed>>|null Decoded array on hit, null on miss
     */
    public function get(string $key): ?array;

    /**
     * Store a value in the cache with a TTL.
     *
     * Uses REPLACE INTO for atomic upsert (old value goes directly to new value with no gap).
     * Silently fails on database errors (prepare failure, encode failure).
     *
     * @param string $key Cache key
     * @param list<array<string, mixed>> $data Data to cache (must be JSON-encodable)
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
}
