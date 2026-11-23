<?php

declare(strict_types=1);

namespace BugPipeline;

use BugPipeline\Contracts\BugIssueNoticeRepositoryInterface;

/**
 * Store for `ibl_bug_issue_notices`: the map of tracked GitHub bug issues to their
 * Discord origin and the close-notice state machine. Every UPDATE guards on the
 * expected source state, so two concurrent ticks cannot both win a transition.
 *
 * Discord ids bind as STRINGS ("s") and are validated against /^[0-9]{17,19}$/
 * before any SQL runs. Discord ids are at most 19 digits; a 20-digit value can
 * overflow the BIGINT UNSIGNED columns. Never (int)-cast a snowflake.
 */
class BugIssueNoticeRepository extends \Database\BaseMysqliRepository implements BugIssueNoticeRepositoryInterface
{
    private const SNOWFLAKE_PATTERN = '/^[0-9]{17,19}$/';
    private const VALID_REASONS = ['completed', 'not_planned', 'duplicate', 'other'];
    private const MIN_LIMIT = 1;
    private const MAX_LIMIT = 50;

    public function upsert(int $issue, ?int $legacy, ?string $reporterId, ?string $channelId, ?string $threadId, ?string $messageId): void
    {
        self::assertSnowflake($reporterId, 'reporter');
        self::assertSnowflake($channelId, 'channel');
        self::assertSnowflake($threadId, 'thread');
        self::assertSnowflake($messageId, 'message');

        // Updates the id columns only; notice_state / attempts / timestamps are never touched,
        // so re-running the mapping import cannot reset a notice already sent.
        $this->execute(
            'INSERT INTO `ibl_bug_issue_notices`
                (issue_number, legacy_issue_number, reporter_discord_id, channel_id, thread_id, original_message_id)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                legacy_issue_number = VALUES(legacy_issue_number),
                reporter_discord_id = VALUES(reporter_discord_id),
                channel_id = VALUES(channel_id),
                thread_id = VALUES(thread_id),
                original_message_id = VALUES(original_message_id)',
            'iissss',
            $issue,
            $legacy,
            $reporterId,
            $channelId,
            $threadId,
            $messageId
        );
    }

    public function seedNotified(int $issue): bool
    {
        return $this->execute(
            "UPDATE `ibl_bug_issue_notices` SET notice_state = 'seeded'
             WHERE issue_number = ? AND notice_state = 'pending'",
            'i',
            $issue
        ) === 1;
    }

    /**
     * @return list<array{issue_number: int, reporter_discord_id: ?string, thread_id: ?string, original_message_id: ?string}>
     */
    public function listPending(int $limit, int $after = 0): array
    {
        $limit = max(self::MIN_LIMIT, min(self::MAX_LIMIT, $limit));
        $rows = $this->fetchAll(
            "SELECT issue_number, reporter_discord_id, thread_id, original_message_id
             FROM `ibl_bug_issue_notices`
             WHERE notice_state = 'pending' AND issue_number > ?
             ORDER BY issue_number
             LIMIT ?",
            'ii',
            max(0, $after),
            $limit
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'issue_number' => self::scalarToInt($row['issue_number']),
                'reporter_discord_id' => self::nullableId($row['reporter_discord_id']),
                'thread_id' => self::nullableId($row['thread_id']),
                'original_message_id' => self::nullableId($row['original_message_id']),
            ];
        }
        return $out;
    }

    public function claim(int $issue): bool
    {
        return $this->execute(
            "UPDATE `ibl_bug_issue_notices` SET notice_state = 'claimed', claimed_at = NOW()
             WHERE issue_number = ? AND notice_state = 'pending'",
            'i',
            $issue
        ) === 1;
    }

    public function markNotified(int $issue, string $reason): bool
    {
        if (!in_array($reason, self::VALID_REASONS, true)) {
            throw new \InvalidArgumentException('reason must be one of: ' . implode(', ', self::VALID_REASONS));
        }

        return $this->execute(
            "UPDATE `ibl_bug_issue_notices`
             SET notice_state = 'notified', notified_reason = ?, notified_at = NOW()
             WHERE issue_number = ? AND notice_state = 'claimed'",
            'si',
            $reason,
            $issue
        ) === 1;
    }

    /**
     * Return a claimed row to the queue, or park it as 'failed' once it has been released
     * the fifth time. Throws when the row is not in the claimed state.
     *
     * @return 'pending'|'failed'
     */
    public function release(int $issue): string
    {
        // MariaDB evaluates SET assignments left to right and later ones see the new value of
        // earlier columns, so notice_state must be assigned BEFORE notice_attempts is incremented.
        $affected = $this->execute(
            "UPDATE `ibl_bug_issue_notices`
             SET notice_state = IF(notice_attempts + 1 >= 5, 'failed', 'pending'),
                 notice_attempts = notice_attempts + 1,
                 claimed_at = NULL
             WHERE issue_number = ? AND notice_state = 'claimed'",
            'i',
            $issue
        );
        if ($affected !== 1) {
            throw new NoticeNotClaimedException("issue {$issue} is not in the claimed state");
        }

        $row = $this->fetchOne(
            'SELECT notice_state FROM `ibl_bug_issue_notices` WHERE issue_number = ? LIMIT 1',
            'i',
            $issue
        );
        return $row !== null && $row['notice_state'] === 'failed' ? 'failed' : 'pending';
    }

    public function markSkipped(int $issue): bool
    {
        return $this->execute(
            "UPDATE `ibl_bug_issue_notices` SET notice_state = 'skipped'
             WHERE issue_number = ? AND notice_state = 'claimed'",
            'i',
            $issue
        ) === 1;
    }

    /**
     * @return list<int>
     */
    public function listStaleClaims(int $olderThanMinutes): array
    {
        $rows = $this->fetchAll(
            "SELECT issue_number FROM `ibl_bug_issue_notices`
             WHERE notice_state = 'claimed' AND claimed_at < NOW() - INTERVAL ? MINUTE
             ORDER BY issue_number",
            'i',
            $olderThanMinutes
        );

        return array_values(array_map(
            static fn (array $row): int => self::scalarToInt($row['issue_number']),
            $rows
        ));
    }

    private static function assertSnowflake(?string $id, string $label): void
    {
        if ($id !== null && preg_match(self::SNOWFLAKE_PATTERN, $id) !== 1) {
            throw new \InvalidArgumentException("{$label} id must be 17-19 digits");
        }
    }

    private static function nullableId(mixed $value): ?string
    {
        return $value === null ? null : self::scalarToString($value);
    }
}
