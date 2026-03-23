<?php

declare(strict_types=1);

namespace Logging\Contracts;

use Psr\Log\LoggerInterface;

/**
 * Factory for creating named PSR-3 logger instances.
 *
 * Each channel name produces a logger that tags log records with that channel,
 * making it easy to filter logs by subsystem (e.g. 'db', 'discord', 'trade').
 */
interface LoggerFactoryInterface
{
    /**
     * Get a logger for the given channel.
     *
     * Repeated calls with the same channel name should return the same instance.
     */
    public function channel(string $channel): LoggerInterface;
}
