<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Posts "Sim N recap started on <host>." to #admin-chat on the first claim
 * of a sim, at most once per sim.
 *
 * "First claim" is notified_state IS NULL: every park that posts leaves the
 * column non-null, so a re-claim after a park finds a value and stays quiet.
 * The compare-and-set NULL -> 'started' makes the post once-only across both
 * hosts. A failed post never touches the claim; it only reverts the CAS so the
 * column stays honest about what reached the channel.
 */
final class ClaimAnnouncer
{
    /**
     * @param \Closure(string): void $poster Sends one line to #admin-chat; may throw.
     */
    public function __construct(
        private readonly SimSummaryRepository $repo,
        private readonly \Closure $poster,
    ) {
    }

    /**
     * Call only after this host's claim succeeded.
     *
     * @return array{notified: 'started'|'unchanged'|'failed', error: ?string}
     *         error is the exception class on 'failed' (never its message:
     *         the message can carry the webhook host), else null.
     */
    public function announceStart(int $sim, string $host): array
    {
        if (!$this->repo->transitionNotifiedState($sim, null, 'started')) {
            return ['notified' => 'unchanged', 'error' => null];
        }
        try {
            ($this->poster)(AdminChatStatus::message('started', $sim, [], $host));
        } catch (\Throwable $e) {
            $this->repo->revertNotifiedState($sim, 'started', null);

            return ['notified' => 'failed', 'error' => get_class($e)];
        }

        return ['notified' => 'started', 'error' => null];
    }
}
