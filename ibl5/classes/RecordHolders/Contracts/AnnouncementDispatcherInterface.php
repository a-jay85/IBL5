<?php

declare(strict_types=1);

namespace RecordHolders\Contracts;

/**
 * Contract for dispatching record-breaking announcement messages.
 *
 * Implementations decide where messages go (Discord, nowhere for dry-run, a
 * test spy, etc.). Called once per message; the caller catches any throwable so
 * one failed dispatch does not abort the remaining announcements.
 *
 * @see RecordBreakingDetectorInterface
 */
interface AnnouncementDispatcherInterface
{
    /**
     * Dispatch a single record announcement to its destination(s).
     *
     * Called once per announcement message. Implementations decide where the
     * message goes (Discord channels, nowhere for dry-run, a test spy, etc.).
     * The caller catches any throwable so one failed dispatch does not abort the
     * remaining announcements.
     *
     * @param string $message The formatted announcement message
     */
    public function dispatch(string $message): void;
}
