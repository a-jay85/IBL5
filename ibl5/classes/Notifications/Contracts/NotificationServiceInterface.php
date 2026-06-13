<?php

declare(strict_types=1);

namespace Notifications\Contracts;

/**
 * NotificationServiceInterface — the single write seam for in-app GM
 * notifications. Every dispatch point (trade events this PR; waivers/FA in a
 * follow-up) calls notify() to record one in-app notification.
 */
interface NotificationServiceInterface
{
    /**
     * Record an in-app notification for a team.
     *
     * @param int         $teamId  Recipient team id (ibl_team_info.teamid)
     * @param string      $type    One of the NotificationType::* constants
     * @param string      $message Human-readable message (rendered HTML-escaped)
     * @param string|null $link    Optional relative link target, or null
     */
    public function notify(int $teamId, string $type, string $message, ?string $link = null): void;
}
