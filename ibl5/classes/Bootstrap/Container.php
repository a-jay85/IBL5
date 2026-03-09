<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\ContainerInterface;

/**
 * Lightweight service container with lazy factory support.
 *
 * Stores raw values directly and invokes Closure factories on first access,
 * caching the result for subsequent calls.
 */
class Container implements ContainerInterface
{
    /** @var array<string, mixed> Raw values and unresolved factory closures. */
    private array $entries = [];

    /** @var array<string, mixed> Resolved factory results (cached). */
    private array $resolved = [];

    /** @var array<string, true> Tracks which entries are factory closures. */
    private array $factories = [];

    /**
     * @see ContainerInterface::get()
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new \RuntimeException("Container entry not found: {$id}");
        }

        // Return cached factory result if already resolved (array_key_exists
        // handles null/false returns correctly, unlike isset)
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        $entry = $this->entries[$id];

        // Invoke factory closures lazily and cache the result
        if (isset($this->factories[$id])) {
            /** @var \Closure(self): mixed $factory */
            $factory = $entry;
            $this->resolved[$id] = $factory($this);
            return $this->resolved[$id];
        }

        return $entry;
    }

    /**
     * @see ContainerInterface::has()
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }

    /**
     * @see ContainerInterface::set()
     */
    public function set(string $id, mixed $value): void
    {
        // Clear any previously cached resolution
        unset($this->resolved[$id], $this->factories[$id]);

        $this->entries[$id] = $value;

        if ($value instanceof \Closure) {
            $this->factories[$id] = true;
        }
    }
}
