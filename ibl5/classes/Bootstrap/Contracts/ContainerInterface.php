<?php

declare(strict_types=1);

namespace Bootstrap\Contracts;

/**
 * Minimal service container interface (PSR-11 subset).
 */
interface ContainerInterface
{
    /**
     * Find an entry by its identifier and return it.
     *
     * @throws \RuntimeException If no entry is found for the given identifier.
     */
    public function get(string $id): mixed;

    /**
     * Returns true if the container can return an entry for the given identifier.
     */
    public function has(string $id): bool;

    /**
     * Register an entry in the container.
     *
     * @param mixed $value A raw value or a factory closure (called lazily on first get()).
     */
    public function set(string $id, mixed $value): void;
}
