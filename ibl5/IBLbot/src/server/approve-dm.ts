import { ActionRowBuilder, ButtonBuilder, ButtonStyle, EmbedBuilder } from 'discord.js';
import type { Client } from 'discord.js';
import type { Request, Response } from 'express';
import { config } from '../config.js';
import { DECISIONS_DIR } from './decision-store.js';
import { claimClick, listPending, mintToken, revokeToken } from './approve-click-store.js';
import { CELL_KEYS, CELL_NAMES, EMPTY_DISPLAY, parseDisplay } from './approve-dm-display.js';
import type { Display } from './approve-dm-display.js';

const SHA_RE = /^[0-9a-f]{40}$/;
const NONCE_RE = /^[0-9a-f]{32}$/;

/**
 * The recipient is resolved server-side from the one owner id, never from the
 * payload. An empty id disables all three routes (503). This helper holds the
 * only empty-owner check in the file.
 */
function requireOwner(res: Response): string | null {
    const owner = config.planReview.ownerDiscordId;
    if (!owner) {
        res.status(503).json({ error: 'approve buttons disabled' });
        return null;
    }
    return owner;
}

/**
 * Build the Approve / Fix-first row. The handler builds every custom_id itself
 * from the freshly minted nonce and never copies a caller string. The nonce is
 * 32 hex, so `approve_fix_` + nonce is 44 characters, under Discord's 100 cap.
 * PR number, SHA and expiry live only in the store.
 */
export function buildApproveRow(nonce: string, disabled = false): ActionRowBuilder<ButtonBuilder> {
    return new ActionRowBuilder<ButtonBuilder>().addComponents(
        new ButtonBuilder()
            .setCustomId(`approve_ok_${nonce}`)
            .setLabel('Approve')
            .setStyle(ButtonStyle.Success)
            .setDisabled(disabled),
        new ButtonBuilder()
            .setCustomId(`approve_fix_${nonce}`)
            .setLabel('Fix first')
            .setStyle(ButtonStyle.Secondary)
            .setDisabled(disabled),
    );
}

/** Display text only. Any parse fault degrades to the fallback render, never a 4xx/5xx. */
function safeDisplay(raw: unknown): Display {
    try {
        return parseDisplay(raw);
    } catch {
        return EMPTY_DISPLAY;
    }
}

/**
 * `POST /discordApproveDM` — `{ pr, head_sha, display? }`. Mints a token, then DMs the
 * owner the embed and button row. The optional `display` object is display text only.
 * On a send failure the token is revoked and the answer is 502.
 */
export function handleApproveDM(client: Client, dir: string = DECISIONS_DIR) {
    return (req: Request, res: Response): void => {
        const body = req.body as { pr?: unknown; head_sha?: unknown; display?: unknown } | undefined;
        const pr = body?.pr;
        const headSha = body?.head_sha;

        if (typeof pr !== 'number' || !Number.isInteger(pr) || pr < 1 || pr > 999999) {
            res.status(400).json({ error: 'invalid or missing pr' });
            return;
        }
        if (typeof headSha !== 'string' || !SHA_RE.test(headSha)) {
            res.status(400).json({ error: 'invalid or missing head_sha' });
            return;
        }

        const owner = requireOwner(res);
        if (owner === null) return;

        const { nonce } = mintToken(pr, headSha, Date.now(), dir);

        const display = safeDisplay(body?.display);
        const lines: string[] = [];
        if (display.title) lines.push(display.title);
        lines.push(`Head \`${headSha.slice(0, 7)}\`` + (display.size ? ` · ${display.size}` : ''));
        const embed = new EmbedBuilder()
            .setTitle(`PR #${pr} is held for you`)
            .setDescription(lines.join('\n'))
            .addFields(
                ...CELL_KEYS.map((key) => ({ name: CELL_NAMES[key], value: display.cells[key], inline: true })),
                { name: 'Verdict', value: display.verdict, inline: false },
                ...(display.decision ? [{ name: 'Decision', value: display.decision, inline: false }] : []),
            )
            .setFooter({
                text: 'Approve runs bin/owner-approve on the Mac. Fix first records a condition. ' +
                    `Run bin/owner-approve ${pr} --finish once the fix is pushed. This button expires in 24h.`,
            })
            .setColor(0x5865f2)
            .setTimestamp();

        client.users.send(owner, {
            embeds: [embed],
            components: [buildApproveRow(nonce)],
            allowedMentions: { parse: [] },
        })
            .then(() => {
                console.log(`Approve buttons sent for PR #${pr}`);
                res.json({ nonce });
            })
            .catch((error: unknown) => {
                console.error(`Failed to send approve DM for PR #${pr}:`, error);
                revokeToken(nonce, Date.now(), dir);
                res.status(502).json({ error: 'failed to send approve DM' });
            });
    };
}

/**
 * `GET /approveClicks` — pressed, unclaimed clicks inside the claim window.
 */
export function handleListClicks(dir: string = DECISIONS_DIR) {
    return (_req: Request, res: Response): void => {
        if (requireOwner(res) === null) return;
        res.json({ clicks: listPending(Date.now(), dir) });
    };
}

/**
 * `POST /approveClicks/claim` — `{ nonce }`. ok 200 with the record, unknown 404,
 * already-claimed 409, lapsed 410.
 */
export function handleClaimClick(dir: string = DECISIONS_DIR) {
    return (req: Request, res: Response): void => {
        const nonce = (req.body as { nonce?: unknown } | undefined)?.nonce;
        if (typeof nonce !== 'string' || !NONCE_RE.test(nonce)) {
            res.status(400).json({ error: 'invalid or missing nonce' });
            return;
        }
        if (requireOwner(res) === null) return;

        const result = claimClick(nonce, Date.now(), dir);
        switch (result.status) {
            case 'ok':
                res.status(200).json(result.record);
                return;
            case 'unknown':
                res.status(404).json({ error: 'unknown' });
                return;
            case 'already-claimed':
                res.status(409).json({ error: 'already-claimed' });
                return;
            case 'lapsed':
                res.status(410).json({ error: 'lapsed' });
                return;
        }
    };
}
