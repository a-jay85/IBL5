import type { Client, Message } from 'discord.js';
import { config } from './config.js';
import * as phpClient from './php-client.js';
import { handleEnqueue as realHandleEnqueue } from './handlers.js';

interface BackfillDeps {
    getState: typeof phpClient.getState;
    handleEnqueue: (msg: Message) => Promise<void>;
}

/**
 * On boot, recover TOP-LEVEL bug reports posted while the Mac was asleep. Reads the
 * cursor from PR #3 (gap #1), fetches only new top-level messages after it, and
 * replays each through the SAME handleEnqueue the live listener uses (no divergent
 * replay code — PR #3's /enqueue owns idempotency/dedupe).
 *
 * NEVER throws — every rejection is caught, logged, and swallowed. Collaborators are
 * injected via `deps` so vitest can mock them without a network or a live channel.
 */
export async function runBackfill(client: Client, deps?: Partial<BackfillDeps>): Promise<void> {
    const getState = deps?.getState ?? phpClient.getState;
    const handleEnqueue = deps?.handleEnqueue ?? realHandleEnqueue;

    try {
        // 1. Read cursor (gap #1).
        const { last_processed_message_id } = await getState({ channel_id: config.bugChannelId });

        // 2. Resolve the bug channel; guard it exposes .messages (a misconfigured
        //    BUG_CHANNEL_ID must not throw).
        const channel = await client.channels.fetch(config.bugChannelId);
        if (!channel || !channel.isTextBased()) {
            console.error(`Backfill: bug channel ${config.bugChannelId} is not a text channel — skipping`);
            return;
        }

        // 3. Null-cursor boundary (first-ever boot): start fresh, no replay. Replaying
        //    full channel history could flood PR #3's queue with pre-pipeline messages,
        //    and messages.fetch({ after }) requires a snowflake. Optionally seed the
        //    cursor from the newest message so the next boot has a watermark.
        if (last_processed_message_id === null) {
            const newest = await channel.messages.fetch({ limit: 1 });
            const newestMsg = newest.first();
            if (newestMsg) {
                try {
                    await phpClient.lastSeen({
                        channel_id: config.bugChannelId,
                        message_id: newestMsg.id,
                    });
                } catch (seedErr) {
                    console.error('Backfill: failed to seed cursor on first boot:', seedErr);
                }
            }
            return;
        }

        // 4. Page forward (TOP-LEVEL only — channel.messages.fetch does NOT include
        //    thread replies; missed thread replies are recovered by the cron's
        //    per-tick live get-thread-messages, decision 7). Replay oldest-first so
        //    the cursor advances monotonically.
        let after = last_processed_message_id;
        for (;;) {
            const page = await channel.messages.fetch({ after, limit: 100 });
            if (page.size === 0) break;

            const oldestFirst = [...page.values()].sort(
                (a, b) => a.createdTimestamp - b.createdTimestamp,
            );

            // 5. Replay via the shared path.
            for (const message of oldestFirst) {
                await handleEnqueue(message);
            }

            // Advance the cursor to the newest replayed id (last after oldest-first sort).
            const lastReplayed = oldestFirst[oldestFirst.length - 1];
            if (!lastReplayed) break;
            after = lastReplayed.id;   // string cursor — NEVER Number() it

            if (page.size < 100) break;   // short page → done, no infinite loop
        }
    } catch (error) {
        // 6/7. Error containment — runBackfill NEVER throws. A 429 (rate-limit) is
        // logged distinctly so a mis-provisioned API_KEY tier fails loudly rather than
        // silently dropping reports (PR #3 §rate-limiting: the bug-bot's API_KEY must
        // be a high/unlimited ibl_api_keys tier — an ops provisioning item).
        if (isRateLimited(error)) {
            console.error('Backfill: RATE-LIMITED (429) — API_KEY tier likely too low; reports may be dropped:', error);
        } else {
            console.error('Backfill failed (swallowed — bot continues live):', error);
        }
    }
}

function isRateLimited(error: unknown): boolean {
    if (error && typeof error === 'object' && 'statusCode' in error) {
        return (error as { statusCode: unknown }).statusCode === 429;
    }
    return false;
}
