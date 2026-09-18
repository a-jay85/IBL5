import type { Client } from 'discord.js';
import { config } from '../config.js';
import { readPending } from './decision-store.js';
import { buildPlanReviewRow } from './plan-review-dm.js';

export const STALE_THRESHOLD_MS = 15 * 60 * 1000;
export const SWEEP_INTERVAL_MS = 60_000;

export function staleWarning(slug: string, action: 'queue' | 'discard'): string {
    const base = `⚠️ not picked up after 15 min — the drain may be down (check com.ibl5.bug-pipeline-cron / bin/plan-review-drain log).\nFinish by hand: `;
    if (action === 'discard') {
        return base + `\`mv ~/claude-plans/${slug}.md ~/claude-plans/discarded/\``;
    }
    return base + `\`bin/automouse/queue ${slug}\``;
}

let sweeping = false;

export async function sweepOnce(client: Client, dir?: string): Promise<void> {
    if (sweeping) return;
    sweeping = true;
    try {
        const owner = config.planReview?.ownerDiscordId;
        if (!owner) return;
        const now = Date.now();
        for (const decision of readPending(dir)) {
            try {
                if (!decision.messageId) continue;
                const age = now - Date.parse(decision.ts);
                if (!(age > STALE_THRESHOLD_MS)) continue;
                const want = staleWarning(decision.slug, decision.action);
                const user = await client.users.fetch(owner);
                const dm = await user.createDM();
                const message = await dm.messages.fetch(decision.messageId);
                if (message.content === want) continue;
                await message.edit({ content: want, components: [buildPlanReviewRow(decision.slug, true)] });
                await client.users.send(owner, want);
            } catch (error: unknown) {
                console.error(`sweepOnce error for decision ${decision.id}:`, error);
            }
        }
    } finally {
        sweeping = false;
    }
}

export function startStaleSweeper(client: Client, dir?: string): NodeJS.Timeout {
    return setInterval(() => { void sweepOnce(client, dir); }, SWEEP_INTERVAL_MS);
}
