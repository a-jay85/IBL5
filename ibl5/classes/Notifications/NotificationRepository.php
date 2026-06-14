<?php

declare(strict_types=1);

namespace Notifications;

use Notifications\Contracts\NotificationRepositoryInterface;

/**
 * @see NotificationRepositoryInterface
 *
 * @phpstan-import-type NotificationRow from NotificationRepositoryInterface
 */
class NotificationRepository extends \BaseMysqliRepository implements NotificationRepositoryInterface
{
    /**
     * @see NotificationRepositoryInterface::insert()
     */
    public function insert(int $teamId, string $type, string $message, ?string $link): int
    {
        // bind_param has no NULL type — branch on $link so a null link is
        // stored as a SQL NULL literal rather than the string "".
        if ($link === null) {
            $this->execute(
                "INSERT INTO `gm_notifications` (team_id, type, message, link)
                 VALUES (?, ?, ?, NULL)",
                "iss",
                $teamId,
                $type,
                $message
            );
        } else {
            $this->execute(
                "INSERT INTO `gm_notifications` (team_id, type, message, link)
                 VALUES (?, ?, ?, ?)",
                "isss",
                $teamId,
                $type,
                $message,
                $link
            );
        }

        return $this->getLastInsertId();
    }

    /**
     * @see NotificationRepositoryInterface::getForTeam()
     *
     * @return list<NotificationRow>
     */
    public function getForTeam(int $teamId, int $limit = 50): array
    {
        /** @var list<NotificationRow> $rows */
        $rows = $this->fetchAll(
            "SELECT id, team_id, type, message, link, read_at, created_at
             FROM `gm_notifications`
             WHERE team_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ?",
            "ii",
            $teamId,
            $limit
        );

        return $rows;
    }

    /**
     * @see NotificationRepositoryInterface::countUnread()
     */
    public function countUnread(int $teamId): int
    {
        // COUNT(*) comes back from mysqli as a string — annotate as string and
        // cast (a mixed→int cast would trip strict-rules cast.int).
        /** @var array{cnt: string}|null $row */
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM `gm_notifications`
             WHERE team_id = ? AND read_at IS NULL",
            "i",
            $teamId
        );

        return $row === null ? 0 : (int) $row['cnt'];
    }

    /**
     * @see NotificationRepositoryInterface::markRead()
     */
    public function markRead(int $notificationId, int $teamId): int
    {
        // Authorization invariant: team_id is the caller's session-resolved id,
        // so a forged notification id belonging to another team affects 0 rows.
        return $this->execute(
            "UPDATE `gm_notifications` SET read_at = NOW()
             WHERE id = ? AND team_id = ? AND read_at IS NULL",
            "ii",
            $notificationId,
            $teamId
        );
    }

    /**
     * @see NotificationRepositoryInterface::markAllRead()
     */
    public function markAllRead(int $teamId): int
    {
        return $this->execute(
            "UPDATE `gm_notifications` SET read_at = NOW()
             WHERE team_id = ? AND read_at IS NULL",
            "i",
            $teamId
        );
    }
}
