<?php

declare(strict_types=1);

namespace BugPipeline\Contracts;

/**
 * Lease/claim + state-machine writer contract for the Discord bug pipeline.
 *
 * Groups the atomic lease primitives (single-flight claims, stale-lease reclaim,
 * blocked-hunt resume), the general transition() writer, and the conditional
 * writers transition()'s value-bind cannot express (advanceOnApproval,
 * stampThreadReply, markReminderSent, stampLastProcessed, clearBlocked).
 *
 * @phpstan-import-type BugReportRow from \BugPipeline\BugReportRowCasting
 */
interface BugReportClaimRepositoryInterface
{
    /**
     * @see \BugPipeline\BugReportClaimRepository::claimQueued()
     */
    public function claimQueued(int $id, string $leaseOwner, string $leaseExpires): bool;

    /**
     * @see \BugPipeline\BugReportClaimRepository::claimNextQueued()
     * @phpstan-return BugReportRow|null
     */
    public function claimNextQueued(string $leaseOwner, string $leaseExpires): ?array;

    /**
     * @see \BugPipeline\BugReportClaimRepository::reclaimStaleLease()
     * @phpstan-return BugReportRow|null
     */
    public function reclaimStaleLease(string $newLeaseOwner, string $leaseExpires): ?array;

    /**
     * @see \BugPipeline\BugReportClaimRepository::claimNextHuntable()
     * @phpstan-return BugReportRow|null
     */
    public function claimNextHuntable(string $leaseOwner, string $leaseExpires): ?array;

    /**
     * @see \BugPipeline\BugReportClaimRepository::resumeBlockedHunt()
     */
    public function resumeBlockedHunt(string $leaseOwner, string $leaseExpires, int $id): bool;

    /**
     * @see \BugPipeline\BugReportClaimRepository::transition()
     * @param array<string, int|string> $opts
     */
    public function transition(int $id, string $status, array $opts = [], bool $releaseLease = false): bool;

    /**
     * @see \BugPipeline\BugReportClaimRepository::advanceOnApproval()
     */
    public function advanceOnApproval(string $messageId): bool;

    /**
     * @see \BugPipeline\BugReportClaimRepository::stampThreadReply()
     */
    public function stampThreadReply(string $threadId): bool;

    /**
     * @see \BugPipeline\BugReportClaimRepository::markReminderSent()
     */
    public function markReminderSent(int $id): bool;

    /**
     * @see \BugPipeline\BugReportClaimRepository::stampLastProcessed()
     */
    public function stampLastProcessed(int $id): bool;

    /**
     * @see \BugPipeline\BugReportClaimRepository::clearBlocked()
     */
    public function clearBlocked(int $id): bool;

    /** Statuses an edited source message may re-open for reclassification. */
    public const RECLASSIFIABLE_ON_EDIT = ['queued', 'gathering', 'awaiting_info', 'dropped'];

    public function updateSourceText(string $originalMessageId, string $text): bool;
    public function reviveForReclassify(string $originalMessageId): bool;
    public function markSourceDeleted(string $originalMessageId): bool;
}
