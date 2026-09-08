<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Sends a single free agency message to Discord.
 *
 * Behind an interface so tests can record dispatched messages.
 */
interface FreeAgencyDiscordDispatcherInterface
{
    /** @param string $message A single message, already within the 2000-character limit. */
    public function dispatch(string $message): void;
}
