<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use Cache\Contracts\DatabaseCacheInterface;

/**
 * Cache double that JSON-encodes on set and decodes on get, matching what
 * Cache\DatabaseCache does against the `cache` table.
 */
final class JsonRoundTripCache implements DatabaseCacheInterface
{
    /** @var array<string, string> */
    private array $store = [];

    /** @var array<string, int> */
    private array $ttls = [];

    /** @var list<array{0: string, 1: string}> Method name and key for every get, set and delete call */
    public array $log = [];

    public string $lastKey = '';
    public int $lastTtl = 0;
    public int $setCount = 0;

    /**
     * @return array<mixed>|null
     */
    public function get(string $key): ?array
    {
        $this->log[] = ['get', $key];
        if (!isset($this->store[$key])) {
            return null;
        }

        $decoded = json_decode($this->store[$key], true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $data
     */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $this->store[$key] = json_encode($data, JSON_THROW_ON_ERROR);
        $this->ttls[$key] = $ttlSeconds;
        $this->log[] = ['set', $key];
        $this->lastKey = $key;
        $this->lastTtl = $ttlSeconds;
        $this->setCount++;
    }

    public function delete(string $key): void
    {
        $this->log[] = ['delete', $key];
        unset($this->store[$key], $this->ttls[$key]);
    }

    /**
     * @return list<string> Keys of the logged calls with the given method name, in call order
     */
    public function keysOf(string $method): array
    {
        $keys = [];
        foreach ($this->log as [$name, $key]) {
            if ($name === $method) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function ttlOf(string $key): ?int
    {
        return $this->ttls[$key] ?? null;
    }

    public function clearLog(): void
    {
        $this->log = [];
    }

    /**
     * @return array<mixed>|null
     */
    public function getStale(string $key): ?array
    {
        return $this->get($key);
    }

    /**
     * @param array<mixed> $data
     */
    public function seed(string $key, array $data): void
    {
        $this->store[$key] = json_encode($data, JSON_THROW_ON_ERROR);
    }
}
