<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use Cache\Contracts\DatabaseCacheInterface;

/**
 * Cache double whose delete() always throws; every other call is an inert no-op.
 */
final class ThrowingDeleteCache implements DatabaseCacheInterface
{
    public int $deleteAttempts = 0;

    /**
     * @return array<mixed>|null
     */
    public function get(string $key): ?array
    {
        return null;
    }

    /**
     * @param array<mixed> $data
     */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
    }

    public function delete(string $key): void
    {
        $this->deleteAttempts++;

        throw new \RuntimeException('cache delete failed');
    }

    /**
     * @return array<mixed>|null
     */
    public function getStale(string $key): ?array
    {
        return null;
    }
}
