<?php

declare(strict_types=1);

namespace RecordHolders;

use Discord\Discord;
use RecordHolders\Contracts\AnnouncementDispatcherInterface;

/**
 * Posts record announcements to the configured Discord channels.
 *
 * This is the production default injected into RecordBreakingDetector when no
 * dispatcher is provided. It wraps the static Discord::postToChannel() call
 * that previously lived in the detector's private sendDiscordNotification().
 */
final class DiscordAnnouncementDispatcher implements AnnouncementDispatcherInterface
{
    public function dispatch(string $message): void
    {
        Discord::postToChannel('#trades', $message);
        Discord::postToChannel('#general-chat', $message);
    }
}
