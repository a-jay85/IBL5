import * as fs from 'fs';
import * as path from 'path';
import * as crypto from 'crypto';
import { DECISIONS_DIR } from './decision-store.js';

/**
 * Append-only JSONL event store for the Discord Approve / Fix-first buttons.
 * A separate file from plan-decisions.jsonl so plan review is untouched.
 *
 * State is re-folded from the file on every call and never cached in memory,
 * so a pm2 restart or a deploy loses nothing. iblbot is single-process (pm2
 * fork mode), so there is no lock, same as the decision store.
 */
export const APPROVE_CLICKS_FILE = 'approve-clicks.jsonl';
export const TOKEN_TTL_MS = 24 * 60 * 60 * 1000;
export const CLAIM_WINDOW_MS = 60 * 60 * 1000;

export type ClickButton = 'ok' | 'fix';

type MintEvent = {
    type: 'mint';
    nonce: string;
    pr: number;
    head_sha: string;
    minted_at_ms: number;
    expires_at_ms: number;
};
type SupersedeEvent = { type: 'supersede'; nonce: string; at_ms: number };
type ClickEvent = {
    type: 'click';
    nonce: string;
    button: ClickButton;
    condition: string | null;
    actor: string;
    clicked_at_ms: number;
};
type ClaimEvent = { type: 'claim'; nonce: string; at_ms: number };

export type TokenState = {
    mint: MintEvent;
    superseded: boolean;
    clicked: ClickEvent | null;
    claimed: boolean;
};

export type ClickRecord = {
    nonce: string;
    pr: number;
    head_sha: string;
    button: ClickButton;
    condition: string | null;
    actor: string;
    clicked_at: string;
    clicked_at_epoch: number;
};

export type ClickRefusal = 'unknown' | 'superseded' | 'used' | 'expired';

export class ClickRefusedError extends Error {
    reason: ClickRefusal;
    constructor(reason: ClickRefusal) {
        super(`approve click refused: ${reason}`);
        this.name = 'ClickRefusedError';
        this.reason = reason;
    }
}

export type ClaimResult =
    | { status: 'ok'; record: ClickRecord }
    | { status: 'unknown' | 'already-claimed' | 'lapsed' };

function storeFile(dir: string): string {
    return path.join(dir, APPROVE_CLICKS_FILE);
}

function append(dir: string, event: MintEvent | SupersedeEvent | ClickEvent | ClaimEvent): void {
    fs.mkdirSync(dir, { recursive: true });
    fs.appendFileSync(storeFile(dir), JSON.stringify(event) + '\n', 'utf8');
}

/**
 * Replay the file into a nonce -> state map. A malformed line is skipped, same
 * tolerant parser as decision-store.ts.
 */
export function foldState(dir: string = DECISIONS_DIR): Map<string, TokenState> {
    const states = new Map<string, TokenState>();
    let content: string;
    try {
        content = fs.readFileSync(storeFile(dir), 'utf8');
    } catch {
        return states;
    }

    for (const line of content.split('\n')) {
        if (!line.trim()) continue;
        let ev: { type?: string; nonce?: string };
        try {
            ev = JSON.parse(line) as { type?: string; nonce?: string };
        } catch {
            continue; // skip malformed lines
        }
        if (typeof ev.nonce !== 'string') continue;

        if (ev.type === 'mint') {
            states.set(ev.nonce, {
                mint: ev as MintEvent,
                superseded: false,
                clicked: null,
                claimed: false,
            });
            continue;
        }
        const st = states.get(ev.nonce);
        if (!st) continue;
        if (ev.type === 'supersede') st.superseded = true;
        else if (ev.type === 'click') st.clicked = ev as ClickEvent;
        else if (ev.type === 'claim') st.claimed = true;
    }
    return states;
}

/**
 * Mint a fresh token for a PR at a head SHA. Every live, unclicked token on the
 * same PR is superseded first, so only one button per PR can be pressed.
 */
export function mintToken(
    pr: number,
    headSha: string,
    nowMs: number,
    dir: string = DECISIONS_DIR,
): { nonce: string; expires_at_ms: number } {
    for (const [oldNonce, st] of foldState(dir)) {
        const live = !st.superseded && !st.clicked && nowMs < st.mint.expires_at_ms;
        if (st.mint.pr === pr && live) {
            append(dir, { type: 'supersede', nonce: oldNonce, at_ms: nowMs });
        }
    }

    const nonce = crypto.randomBytes(16).toString('hex');
    const expires_at_ms = nowMs + TOKEN_TTL_MS;
    append(dir, {
        type: 'mint',
        nonce,
        pr,
        head_sha: headSha,
        minted_at_ms: nowMs,
        expires_at_ms,
    });
    return { nonce, expires_at_ms };
}

export function revokeToken(nonce: string, nowMs: number, dir: string = DECISIONS_DIR): void {
    append(dir, { type: 'supersede', nonce, at_ms: nowMs });
}

/**
 * Returns the token's mint event, or throws ClickRefusedError with the reason.
 */
export function lookupLive(
    nonce: string,
    nowMs: number,
    dir: string = DECISIONS_DIR,
): MintEvent {
    const st = foldState(dir).get(nonce);
    if (!st) throw new ClickRefusedError('unknown');
    if (st.superseded) throw new ClickRefusedError('superseded');
    if (st.clicked) throw new ClickRefusedError('used');
    if (nowMs >= st.mint.expires_at_ms) throw new ClickRefusedError('expired');
    return st.mint;
}

export function recordClick(
    nonce: string,
    button: ClickButton,
    actor: string,
    condition: string | null,
    nowMs: number,
    dir: string = DECISIONS_DIR,
): void {
    lookupLive(nonce, nowMs, dir);
    append(dir, {
        type: 'click',
        nonce,
        button,
        condition,
        actor: String(actor),
        clicked_at_ms: nowMs,
    });
}

/**
 * Clicked, unclaimed clicks that are still inside the claim window.
 */
export function listPending(nowMs: number, dir: string = DECISIONS_DIR): ClickRecord[] {
    const out: ClickRecord[] = [];
    for (const st of foldState(dir).values()) {
        if (!st.clicked || st.claimed) continue;
        if (nowMs - st.clicked.clicked_at_ms > CLAIM_WINDOW_MS) continue;
        out.push(toRecord(st));
    }
    return out;
}

function toRecord(st: TokenState): ClickRecord {
    const click = st.clicked as ClickEvent;
    return {
        nonce: st.mint.nonce,
        pr: st.mint.pr,
        head_sha: st.mint.head_sha,
        button: click.button,
        condition: click.condition,
        actor: click.actor,
        clicked_at: new Date(click.clicked_at_ms).toISOString(),
        clicked_at_epoch: Math.floor(click.clicked_at_ms / 1000),
    };
}

/**
 * Takes a click at most once. Both `unknown` checks run before any append, so
 * an unknown nonce leaves the file byte-identical. A claim is appended only on `ok`.
 */
export function claimClick(
    nonce: string,
    nowMs: number,
    dir: string = DECISIONS_DIR,
): ClaimResult {
    const st = foldState(dir).get(nonce);
    if (!st || !st.clicked) return { status: 'unknown' };
    if (st.claimed) return { status: 'already-claimed' };
    if (nowMs - st.clicked.clicked_at_ms > CLAIM_WINDOW_MS) return { status: 'lapsed' };
    append(dir, { type: 'claim', nonce, at_ms: nowMs });
    return { status: 'ok', record: toRecord(st) };
}
