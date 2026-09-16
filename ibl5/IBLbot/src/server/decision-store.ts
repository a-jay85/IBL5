import * as fs from 'fs';
import * as path from 'path';
import * as crypto from 'crypto';

export type PlanDecision = {
    kind: 'decision';
    id: string;
    slug: string;
    action: 'queue' | 'discard';
    actor: string;
    ts: string;
};

type AckRecord = {
    kind: 'ack';
    id: string;
    ts: string;
};

export const DECISIONS_DIR: string = path.resolve(process.cwd(), 'data');
export const DECISIONS_FILE = 'plan-decisions.jsonl';

export class DecisionExistsError extends Error {
    existing: PlanDecision;
    constructor(existing: PlanDecision) {
        super(`Decision already exists for slug: ${existing.slug}`);
        this.name = 'DecisionExistsError';
        this.existing = existing;
    }
}

/**
 * Read all lines from the file, skipping empties and malformed JSON.
 * Returns { decisions, ackedIds } without filtering — used by readPending and hasDecision.
 */
function readRaw(dir: string): { lines: string[]; decisions: PlanDecision[]; ackedIds: Set<string> } {
    const file = path.join(dir, DECISIONS_FILE);
    let content: string;
    try {
        content = fs.readFileSync(file, 'utf8');
    } catch {
        return { lines: [], decisions: [], ackedIds: new Set() };
    }

    const rawLines = content.split('\n');
    const lines: string[] = [];
    const ackedIds = new Set<string>();
    const decisions: PlanDecision[] = [];

    for (const line of rawLines) {
        if (!line.trim()) continue;
        lines.push(line);
        try {
            const record = JSON.parse(line) as PlanDecision | AckRecord;
            if (record.kind === 'ack') {
                ackedIds.add((record as AckRecord).id);
            } else if (record.kind === 'decision') {
                decisions.push(record as PlanDecision);
            }
        } catch {
            // skip malformed lines
        }
    }

    return { lines, decisions, ackedIds };
}

/**
 * Returns all decision records that have not been acknowledged, in file order.
 */
export function readPending(dir: string = DECISIONS_DIR): PlanDecision[] {
    const { decisions, ackedIds } = readRaw(dir);
    return decisions.filter(d => !ackedIds.has(d.id));
}

/**
 * Returns the first decision record (acked or not) for the given slug that is still
 * present in the file, or null if none exists (or the file has been compacted past it).
 */
export function hasDecision(slug: string, dir: string = DECISIONS_DIR): PlanDecision | null {
    const file = path.join(dir, DECISIONS_FILE);
    let content: string;
    try {
        content = fs.readFileSync(file, 'utf8');
    } catch {
        return null;
    }

    for (const line of content.split('\n')) {
        if (!line.trim()) continue;
        try {
            const record = JSON.parse(line);
            if (record.kind === 'decision' && record.slug === slug) {
                return record as PlanDecision;
            }
        } catch {
            // skip malformed lines
        }
    }

    return null;
}

/**
 * Appends a new decision for the given slug. Throws DecisionExistsError if a decision
 * for this slug is already on disk. Safe to call concurrently on different slugs because
 * iblbot runs single-process (fork mode, no cluster).
 */
export function appendDecision(
    input: { slug: string; action: 'queue' | 'discard'; actor: string },
    dir: string = DECISIONS_DIR,
): PlanDecision {
    const existing = hasDecision(input.slug, dir);
    if (existing) {
        throw new DecisionExistsError(existing);
    }

    fs.mkdirSync(dir, { recursive: true });

    const decision: PlanDecision = {
        kind: 'decision',
        id: crypto.randomUUID(),
        slug: input.slug,
        action: input.action,
        actor: input.actor,
        ts: new Date().toISOString(),
    };

    const line = JSON.stringify(decision) + '\n';

    if (Buffer.byteLength(line, 'utf8') > 1024) {
        throw new Error('plan decision record too large');
    }

    fs.appendFileSync(path.join(dir, DECISIONS_FILE), line, 'utf8');

    compactIfNeeded(dir);

    return decision;
}

/**
 * Appends ack tombstones for the given ids. A tombstone is written only for an id that is
 * present in the file as an unacked decision. Every other id — already acked, compacted
 * away, or never a decision at all — is ignored: no line is written and it does not count
 * toward the return value. Without that guard an arbitrary id posted to
 * `POST /planDecisions/ack` appends a tombstone matching no decision, which nothing but a
 * later compaction can clear.
 *
 * Returns the number of new tombstones written. A return of 0 does NOT mean failure — it is
 * the normal result of an at-least-once drain replaying ids it already acked. Callers must
 * not read `acked === ids.length` as proof; see `handleAckDecisions` in plan-review-dm.ts
 * for the authoritative check.
 *
 * Safe because iblbot is single-process (pm2 fork mode): the readRaw + appendFileSync
 * pair has no await between them, so the event loop cannot interleave a second ack call.
 */
export function ackDecisions(ids: string[], dir: string = DECISIONS_DIR): number {
    if (ids.length === 0) return 0;

    // Read the file once up front; new writes are appended, not interleaved
    // (single-process invariant — see ecosystem.config.cjs).
    const { decisions, ackedIds: alreadyAcked } = readRaw(dir);
    const knownIds = new Set(decisions.map(d => d.id));

    const file = path.join(dir, DECISIONS_FILE);
    let count = 0;

    for (const id of ids) {
        if (!knownIds.has(id)) continue;      // unknown id: ignored, never tombstoned
        if (alreadyAcked.has(id)) continue;   // idempotent: no second tombstone
        const ack: AckRecord = {
            kind: 'ack',
            id,
            ts: new Date().toISOString(),
        };
        const line = JSON.stringify(ack) + '\n';
        try {
            fs.appendFileSync(file, line, 'utf8');
            count++;
        } catch {
            // If the file or directory doesn't exist, there is nothing to ack
        }
    }

    if (count > 0) {
        compactIfNeeded(dir);
    }

    return count;
}

/**
 * Compacts the decisions file when it has grown past 500 lines. Keeps every unacked
 * decision record; drops acked decision+tombstone pairs. Writes to a .tmp file then
 * renames atomically — the live file is always either the old or new complete version.
 *
 * Called by appendDecision and ackDecisions after their write. Never called from readPending
 * — a drain must not mutate the file.
 *
 * WARNING: This check is safe only because iblbot runs single-process (pm2 fork mode).
 * Switching to pm2 cluster mode would require a real file lock here.
 */
function compactIfNeeded(dir: string): void {
    const file = path.join(dir, DECISIONS_FILE);
    let content: string;
    try {
        content = fs.readFileSync(file, 'utf8');
    } catch {
        return;
    }

    const lines = content.split('\n').filter(l => l.trim() !== '');
    if (lines.length <= 500) return;

    // Collect all acked ids
    const ackedIds = new Set<string>();
    for (const line of lines) {
        try {
            const record = JSON.parse(line);
            if (record.kind === 'ack') {
                ackedIds.add(record.id);
            }
        } catch {
            // skip malformed
        }
    }

    // Keep only unacked decision records; drop acked decisions and all ack tombstones.
    // Malformed lines are also dropped — they cannot be recovered and keeping them
    // indefinitely would prevent the file from ever shrinking.
    const kept: string[] = [];
    for (const line of lines) {
        try {
            const record = JSON.parse(line);
            if (record.kind === 'decision' && !ackedIds.has(record.id)) {
                kept.push(line);
            }
            // Drop: acked decisions and all ack tombstones
        } catch {
            // Drop malformed lines during compaction
        }
    }

    const tmp = path.join(dir, DECISIONS_FILE + '.tmp');
    const output = kept.length > 0 ? kept.map(l => l + '\n').join('') : '';
    fs.writeFileSync(tmp, output, 'utf8');
    fs.renameSync(tmp, file);
}
