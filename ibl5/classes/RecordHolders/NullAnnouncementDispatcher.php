<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\AnnouncementDispatcherInterface;

/**
 * No-op dispatcher for dry-run scenarios and test isolation.
 *
 * Detection still runs and detectAndAnnounce() still returns its full
 * announcement list; nothing is dispatched to any external channel.
 */
final class NullAnnouncementDispatcher implements AnnouncementDispatcherInterface
{
    public function dispatch(string $message): void
    {
        // Intentional no-op: dry-run / test null-object — nothing is sent.
    }
}
