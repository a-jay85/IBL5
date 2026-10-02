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
/**
 * The only verbs a plan-review row can carry, in render order. Every rendered
 * custom_id is `plan_<verb>_<slug>`: <verb> comes from this tuple and <slug> has
 * already matched PLAN_SLUG_RE. No caller-supplied string reaches Discord.
 */
export const PLAN_BUTTON_VERBS = ['queue', 'discard'] as const;
export type PlanButtonVerb = (typeof PLAN_BUTTON_VERBS)[number];

const BUTTON_SPEC: Record<PlanButtonVerb, { label: string; style: ButtonStyle }> = {
    queue: { label: 'Queue', style: ButtonStyle.Success },
    discard: { label: 'Discard', style: ButtonStyle.Danger },
};

export function buildPlanReviewRow(
    slug: string,
    disabled = false,
    verbs: readonly PlanButtonVerb[] = PLAN_BUTTON_VERBS,
): ActionRowBuilder<ButtonBuilder> {
    const wanted = new Set<PlanButtonVerb>(verbs);
    // Discord rejects an empty action row. Neither producer below can yield an
    // empty set, so an empty list here means "default", which is both buttons.
    const rendered = PLAN_BUTTON_VERBS.filter((v) => wanted.size === 0 || wanted.has(v));
    return new ActionRowBuilder<ButtonBuilder>().addComponents(
        rendered.map((verb) =>
            new ButtonBuilder()
                .setCustomId(`plan_${verb}_${slug}`)
                .setLabel(BUTTON_SPEC[verb].label)
                .setStyle(BUTTON_SPEC[verb].style)
                .setDisabled(disabled),
        ),
    );
}

/**
 * Validate the optional `buttons` payload field. `undefined` (field absent) means
 * both buttons, which keeps older callers working. Anything else must be a
 * non-empty array whose every element exactly equals `plan_queue_<slug>` or
 * `plan_discard_<slug>` for this payload's slug, with no repeats. Returns null
 * on any violation; the caller turns null into a 400. Call only after `slug`
 * has passed PLAN_SLUG_RE.
 */
export function parsePlanButtons(raw: unknown, slug: string): PlanButtonVerb[] | null {
    if (raw === undefined) {
        return [...PLAN_BUTTON_VERBS];
    }
    if (!Array.isArray(raw) || raw.length === 0) {
        return null;
    }
    const seen = new Set<PlanButtonVerb>();
    for (const entry of raw) {
        const verb = PLAN_BUTTON_VERBS.find((v) => entry === `plan_${v}_${slug}`);
        if (verb === undefined || seen.has(verb)) {
            return null;
        }
        seen.add(verb);
    }
    return PLAN_BUTTON_VERBS.filter((v) => seen.has(v));
}

/**
 * Recover the button set from a message the bot already sent, so a disabled
 * rebuild keeps the original set. Reads `customId` (discord.js ButtonComponent)
 * or `data.custom_id` (builder or raw component). Defaults to both when
 * components are absent, malformed, or carry no matching id.
 */
export function derivePlanButtons(components: unknown, slug: string): PlanButtonVerb[] {
    const ids = new Set<string>();
    if (Array.isArray(components)) {
        for (const row of components) {
            const inner = (row as { components?: unknown } | null)?.components;
            if (!Array.isArray(inner)) {
                continue;
            }
            for (const c of inner) {
                const btn = c as { customId?: unknown; data?: { custom_id?: unknown } } | null;
                const id = btn?.customId ?? btn?.data?.custom_id;
                if (typeof id === 'string') {
                    ids.add(id);
                }
            }
        }
    }
    const found = PLAN_BUTTON_VERBS.filter((v) => ids.has(`plan_${v}_${slug}`));
    return found.length > 0 ? found : [...PLAN_BUTTON_VERBS];
}

export const PLAN_OUTCOMES = ['queued', 'discarded', 'refused', 'rejected'] as const;
export type PlanOutcome = (typeof PLAN_OUTCOMES)[number];

export const OUTCOME_TEXT: Record<PlanOutcome, string> = {
    queued: '✅ queued',
    discarded: '🗑️ discarded',
    refused: '❌ refused — fails bin/check-plan (see separate DM)',
    rejected: '⚠️ drain rejected this decision — check the drain log',
};

/**
 * Re-fetch and edit a DM the bot sent earlier. Uses the owner-DM path
 * (users.fetch -> createDM -> messages.fetch) to rebuild the channel from scratch.
 */
export async function editPlanReviewDM(
    client: Client,
    userId: string,
    messageId: string,
    slug: string,
    content: string,
): Promise<void> {
    const user = await client.users.fetch(userId);
    const dm = await user.createDM();
    const message = await dm.messages.fetch(messageId);
    await message.edit({
        content,
        components: [buildPlanReviewRow(slug, true, derivePlanButtons(message.components, slug))],
    });
}

export function handlePlanReviewDM(client: Client) {
    return (req: Request, res: Response): void => {
        const payload = req.body as { slug?: unknown; digest?: unknown; buttons?: unknown } | undefined;
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

        // Never echo a caller string into a custom_id: map to the verb enum and
        // let buildPlanReviewRow render from it.
        const buttons = parsePlanButtons(payload?.buttons, slug);
        if (buttons === null) {
            res.status(400).json({ error: 'invalid buttons' });
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

        client.users.send(owner, { embeds: [embed], components: [buildPlanReviewRow(slug, false, buttons)] })
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

function parseOutcomes(raw: unknown): Record<string, PlanOutcome> | null {
    if (raw === undefined) return {};
    if (typeof raw !== 'object' || raw === null || Array.isArray(raw)) return null;
    for (const value of Object.values(raw as Record<string, unknown>)) {
        if (typeof value !== 'string' || !(PLAN_OUTCOMES as readonly string[]).includes(value)) {
            return null;
        }
    }
    return raw as Record<string, PlanOutcome>;
}

/**
 * `POST /planDecisions/ack` — `{ ids: string[], outcomes?: { [id: string]: "queued"|"discarded"|"refused"|"rejected" } }`.
 * Unknown ids are ignored — no tombstone is written for them — so an at-least-once drain
 * replaying ids it already sent stays boring. Idempotency itself lives in the store, not here.
 *
 * **`acked` is advisory, not proof.** It counts only tombstones newly written on this call.
 * Three different outcomes all contribute 0 and the response cannot distinguish them: the id
 * was already acked, the id was compacted away, the id never named a decision. A drain must
 * therefore never gate success on `acked === ids.length`. The authoritative check that an id
 * is drained is that a subsequent `GET /planDecisions` no longer lists it.
 */
export function handleAckDecisions(client: Client, dir?: string) {
    return (req: Request, res: Response): void => {
        const ids = (req.body as { ids?: unknown } | undefined)?.ids;

        if (!Array.isArray(ids) || ids.some((id) => typeof id !== 'string')) {
            res.status(400).json({ error: 'ids must be an array of strings' });
            return;
        }

        const outcomes = parseOutcomes((req.body as { outcomes?: unknown } | undefined)?.outcomes);
        if (outcomes === null) {
            res.status(400).json({ error: 'outcomes must be an object mapping id to queued|discarded|refused|rejected' });
            return;
        }

        // SNAPSHOT BEFORE ACK: capture the pending records so we can find messageId after tombstoning
        const byId = new Map(readPending(dir).map(d => [d.id, d]));

        const acked = ackDecisions(ids as string[], dir);
        res.json({ acked });

        // Fire DM edits detached — never delay the response
        for (const [id, outcome] of Object.entries(outcomes)) {
            const decision = byId.get(id);
            if (!decision?.messageId) continue;
            void editPlanReviewDM(client, decision.actor, decision.messageId, decision.slug, OUTCOME_TEXT[outcome])
                .catch((error: unknown) => {
                    console.error(`Failed to edit plan review DM for ${decision.slug} (${id}):`, error);
                });
        }
    };
}
