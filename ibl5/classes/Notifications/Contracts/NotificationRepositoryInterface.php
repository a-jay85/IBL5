<?php

declare(strict_types=1);

namespace Notifications\Contracts;

/**
 * NotificationRepositoryInterface — contract for gm_notifications persistence.
 *
 * All methods use prepared statements internally; SQL injection is prevented.
 * Authorization is a WHERE-clause invariant: mark methods take the caller's
 * session-resolved team id and scope every mutation to `WHERE team_id = ?`, so
 * a GM cannot mutate another team's row.
 *
 * @phpstan-type NotificationRow = array{
 *     id: int,
 *     team_id: int,
 *     type: string,
 *     message: string,
 *     link: string|null,
 *     read_at: string|null,
 *     created_at: string,
 * }
 */
interface NotificationRepositoryInterface
{
    /**
     * Insert a notification row. Returns the new row's auto-increment id.
     */
    public function insert(int $teamId, string $type, string $message, ?string $link): int;

    /**
     * Notifications for a team, newest-first (created_at DESC, id DESC).
     *
     * @return list<NotificationRow>
     */
    public function getForTeam(int $teamId, int $limit = 50): array;

    /**
     * Count unread (read_at IS NULL) notifications for a team.
     */
    public function countUnread(int $teamId): int;

    /**
     * Mark a single notification read, scoped to the owning team.
     * Returns affected rows (0 if the id does not belong to $teamId or is
     * already read).
     */
    public function markRead(int $notificationId, int $teamId): int;

    /**
     * Mark all unread notifications for a team read. Returns affected rows.
     */
    public function markAllRead(int $teamId): int;
}
