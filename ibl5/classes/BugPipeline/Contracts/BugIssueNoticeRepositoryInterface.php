<?php

declare(strict_types=1);

namespace BugPipeline\Contracts;

/**
 * Map of tracked GitHub bug issues to their Discord origin, plus the close-notice
 * state machine (`ibl_bug_issue_notices`): pending -> claimed -> notified | skipped,
 * release back to pending (or failed at the fifth attempt), seeded for pre-existing closes.
 *
 * Discord ids are carried as decimal STRINGS end to end (a snowflake can exceed 2^53).
 */
interface BugIssueNoticeRepositoryInterface
{
    /**
     * @see \BugPipeline\BugIssueNoticeRepository::upsert()
     */
    public function upsert(int $issue, ?int $legacy, ?string $reporterId, ?string $channelId, ?string $threadId, ?string $messageId): void;

    /**
     * @see \BugPipeline\BugIssueNoticeRepository::seedNotified()
     */
    public function seedNotified(int $issue): bool;

    /**
     * @see \BugPipeline\BugIssueNoticeRepository::listPending()
     * @return list<array{issue_number: int, reporter_discord_id: ?string, thread_id: ?string, original_message_id: ?string}>
     */
    public function listPending(int $limit, int $after = 0): array;

    /**
     * @see \BugPipeline\BugIssueNoticeRepository::claim()
     */
    public function claim(int $issue): bool;

    /**
     * @see \BugPipeline\BugIssueNoticeRepository::markNotified()
     */
    public function markNotified(int $issue, string $reason): bool;

    /**
     * @see \BugPipeline\BugIssueNoticeRepository::release()
     * @return 'pending'|'failed'
     */
    public function release(int $issue): string;

    /**
     * @see \BugPipeline\BugIssueNoticeRepository::markSkipped()
     */
    public function markSkipped(int $issue): bool;

    /**
     * @see \BugPipeline\BugIssueNoticeRepository::listStaleClaims()
     * @return list<int>
     */
    public function listStaleClaims(int $olderThanMinutes): array;
}
