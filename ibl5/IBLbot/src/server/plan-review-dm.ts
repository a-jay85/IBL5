import { ActionRowBuilder, ButtonBuilder, ButtonStyle, EmbedBuilder } from 'discord.js';
import type { Client } from 'discord.js';
import type { Request, Response } from 'express';
import { config } from '../config.js';
import { readPending, ackDecisions } from './decision-store.js';

/**
 * The one definition of a plan slug's shape. Unit 3 points at this constant
 * rather than duplicating the literal.
 *
 * The security property is the character class, not the length: no `/` and no
 * `.`, so a slug can never traverse or escape a directory when it is later used
 * to resolve `~/claude-plans/<slug>.md`. The length bound only keeps the
 * `custom_id` under Discord's 100-char cap — `plan_discard_` is 13 chars, so
 * 13 + 80 = 93 with 7 to spare.
 */
export const PLAN_SLUG_RE = /^[a-z0-9][a-z0-9-]{0,79}$/;

/**
 * `EmbedBuilder.setDescription` validates at 4096 and *throws* — it does not
 * truncate. Callers on the loopback port are not trusted to have honoured unit
 * 1's 1500-char cap, so clamp here and leave room for the overflow tail.
 */
const DESCRIPTION_CLAMP = 3900;

export function clampDigest(digest: string, slug: string): string {
    if (digest.length <= DESCRIPTION_CLAMP) {
        return digest;
    }

    const head = digest.slice(0, DESCRIPTION_CLAMP);
    const moreLines = digest.slice(DESCRIPTION_CLAMP).split('\n').length;

    return `${head}\n…${moreLines} more lines — full plan at ~/claude-plans/${slug}.md`;
}

/**
 * The Queue/Discard action row. Built here and rebuilt disabled by the button
 * handler, so the emitted `custom_id` has exactly one definition.
 */
export function buildPlanReviewRow(slug: string, disabled = false): ActionRowBuilder<ButtonBuilder> {
    return new ActionRowBuilder<ButtonBuilder>().addComponents(
        new ButtonBuilder()
            .setCustomId(`plan_queue_${slug}`)
            .setLabel('Queue')
            .setStyle(ButtonStyle.Success)
            .setDisabled(disabled),
        new ButtonBuilder()
            .setCustomId(`plan_discard_${slug}`)
            .setLabel('Discard')
            .setStyle(ButtonStyle.Danger)
            .setDisabled(disabled),
    );
}

export function handlePlanReviewDM(client: Client) {
    return (req: Request, res: Response): void => {
        const payload = req.body as { slug?: unknown; digest?: unknown } | undefined;
        const slug = payload?.slug;
        const digest = payload?.digest;

        // Reject, never clamp: a clamped slug names a *different* plan, and this
        // slug is the key unit 3's drain uses to locate the plan file.
        if (typeof slug !== 'string' || !PLAN_SLUG_RE.test(slug)) {
            res.status(400).json({ error: 'invalid or missing slug' });
            return;
        }

        if (typeof digest !== 'string' || digest === '') {
            res.status(400).json({ error: 'missing digest' });
            return;
        }

        // The recipient is resolved server-side and is not a payload field, so
        // there is no way for a caller to redirect this DM at another user.
        const owner = config.planReview.ownerDiscordId;
        if (!owner) {
            res.status(503).json({ error: 'plan review disabled' });
            return;
        }

        const embed = new EmbedBuilder()
            .setTitle('Plan review')
            .setDescription(clampDigest(digest, slug))
            .setColor(0x5865f2)
            .setFooter({ text: slug })
            .setTimestamp();

        client.users.send(owner, { embeds: [embed], components: [buildPlanReviewRow(slug)] })
            .then(() => {
                console.log(`Plan review DM sent for ${slug}`);
                res.json({ ok: true, slug });
            })
            .catch((error: unknown) => {
                console.error('Failed to send plan review DM:', error);
                res.status(500).json({ error: 'failed to send plan review DM' });
            });
    };
}

/**
 * `GET /planDecisions` — unacked records only, in file order. Reading is pure:
 * it never acks, never compacts and never creates the sink directory.
 */
export function handleListDecisions(dir?: string) {
    return (_req: Request, res: Response): void => {
        const decisions = readPending(dir).map(({ id, slug, action, actor, ts }) => ({
            id,
            slug,
            action,
            actor,
            ts,
        }));

        res.json({ decisions });
    };
}

/**
 * `POST /planDecisions/ack` — `{ ids: string[] }`. Unknown ids are ignored so an
 * at-least-once drain replaying ids it already sent stays boring. Idempotency
 * itself lives in the store, not here.
 */
export function handleAckDecisions(dir?: string) {
    return (req: Request, res: Response): void => {
        const ids = (req.body as { ids?: unknown } | undefined)?.ids;

        if (!Array.isArray(ids) || ids.some((id) => typeof id !== 'string')) {
            res.status(400).json({ error: 'ids must be an array of strings' });
            return;
        }

        res.json({ acked: ackDecisions(ids as string[], dir) });
    };
}
