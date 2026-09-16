import { describe, it, expect } from 'vitest';
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import * as crypto from 'crypto';
import {
    appendDecision,
    readPending,
    hasDecision,
    ackDecisions,
    DECISIONS_DIR,
    DECISIONS_FILE,
    DecisionExistsError,
    type PlanDecision,
} from './decision-store.js';

// Each test gets its own isolated temp directory — no shared state.
function tmpDir(): string {
    return fs.mkdtempSync(path.join(os.tmpdir(), 'plandec-'));
}

// Write N decision records directly to the file, bypassing appendDecision.
// Returns the array of written ids (in order).
function seedRaw(dir: string, count: number, startAt = 0): string[] {
    fs.mkdirSync(dir, { recursive: true });
    const ids: string[] = [];
    const lines: string[] = [];
    for (let i = startAt; i < startAt + count; i++) {
        const id = crypto.randomUUID();
        const decision: PlanDecision = {
            kind: 'decision',
            id,
            slug: `seed-slug-${i}`,
            action: 'queue',
            actor: '111',
            ts: new Date().toISOString(),
        };
        ids.push(id);
        lines.push(JSON.stringify(decision));
    }
    const file = path.join(dir, DECISIONS_FILE);
    fs.appendFileSync(file, lines.join('\n') + '\n', 'utf8');
    return ids;
}

// ─── Phase 2: append-only decision store ────────────────────────────────────

describe('decision-store — Phase 2', () => {
    it('appendDecision then readPending returns one record with all fields', () => {
        const dir = tmpDir();
        const rec = appendDecision({ slug: 'test-slug', action: 'queue', actor: '123456' }, dir);

        expect(rec.kind).toBe('decision');
        expect(rec.slug).toBe('test-slug');
        expect(rec.action).toBe('queue');
        expect(rec.actor).toBe('123456');
        expect(rec.id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/);
        expect(() => new Date(rec.ts).toISOString()).not.toThrow();

        const pending = readPending(dir);
        expect(pending).toHaveLength(1);
        expect(pending[0]).toEqual(rec);
    });

    it('acking an id removes it from pending; acking the same id again is a no-op', () => {
        const dir = tmpDir();
        const rec = appendDecision({ slug: 'test-slug', action: 'queue', actor: '123456' }, dir);

        ackDecisions([rec.id], dir);
        expect(readPending(dir)).toHaveLength(0);

        // Second ack of the same id must not throw and must leave pending empty
        expect(() => ackDecisions([rec.id], dir)).not.toThrow();
        expect(readPending(dir)).toHaveLength(0);
    });

    it('ackDecisions with a no-such-id does not throw and leaves pending unchanged', () => {
        const dir = tmpDir();
        const rec = appendDecision({ slug: 'some-slug', action: 'queue', actor: '111' }, dir);

        expect(() => ackDecisions(['00000000-0000-0000-0000-000000000000'], dir)).not.toThrow();

        const pending = readPending(dir);
        expect(pending).toHaveLength(1);
        expect(pending[0].id).toBe(rec.id);
    });

    it('truncated last line does not discard earlier records', () => {
        const dir = tmpDir();
        const rec = appendDecision({ slug: 'some-slug', action: 'queue', actor: '111' }, dir);

        // Simulate a crash mid-write: append a truncated JSON fragment
        const file = path.join(dir, DECISIONS_FILE);
        fs.appendFileSync(file, '{"kind":"deci', 'utf8');

        const pending = readPending(dir);
        expect(pending).toHaveLength(1);
        expect(pending[0].id).toBe(rec.id);
    });

    it('a maximal record (80-char slug, 20-char actor) is under 1024 bytes', () => {
        // slug max: first char [a-z0-9], then up to 79 more [a-z0-9-] = 80 total
        const maxSlug = 'a' + 'b'.repeat(79);    // 80 chars
        const maxActor = '1'.repeat(20);           // 20 chars (Discord snowflake ceiling)
        const dir = tmpDir();
        const rec = appendDecision({ slug: maxSlug, action: 'queue', actor: maxActor }, dir);

        const line = JSON.stringify(rec) + '\n';
        expect(Buffer.byteLength(line, 'utf8')).toBeLessThan(1024);
    });

    it('two appendDecision calls for the same slug: second throws DecisionExistsError, file is byte-identical', () => {
        const dir = tmpDir();
        appendDecision({ slug: 'dup-slug', action: 'queue', actor: '111' }, dir);

        const file = path.join(dir, DECISIONS_FILE);
        const before = fs.readFileSync(file, 'utf8');

        let thrown: unknown;
        try {
            appendDecision({ slug: 'dup-slug', action: 'discard', actor: '222' }, dir);
        } catch (e) {
            thrown = e;
        }

        expect(thrown).toBeInstanceOf(DecisionExistsError);
        expect((thrown as DecisionExistsError).existing.slug).toBe('dup-slug');

        const after = fs.readFileSync(file, 'utf8');
        expect(after).toBe(before);

        // File holds exactly one decision record for the slug
        const lines = after.split('\n').filter(l => l.trim());
        const decisions = lines.filter(l => {
            try { return JSON.parse(l).kind === 'decision'; } catch { return false; }
        });
        expect(decisions).toHaveLength(1);
    });

    it('DECISIONS_DIR ends in /data and does not include /dist/', () => {
        // Normalise path separators for cross-platform safety
        const normalized = DECISIONS_DIR.replace(/\\/g, '/');
        expect(normalized.endsWith('/data')).toBe(true);
        expect(normalized.includes('/dist/')).toBe(false);
    });

    it('hasDecision returns null for an unknown slug', () => {
        const dir = tmpDir();
        expect(hasDecision('no-such-slug', dir)).toBeNull();
    });

    it('hasDecision returns a record even after it is acked (pre-compaction)', () => {
        const dir = tmpDir();
        const rec = appendDecision({ slug: 'my-slug', action: 'queue', actor: '1' }, dir);
        ackDecisions([rec.id], dir);

        // still on disk (file below compaction threshold), so hasDecision should find it
        const found = hasDecision('my-slug', dir);
        expect(found?.id).toBe(rec.id);
    });
});

// ─── Phase 3: growth bounding and atomic compaction ─────────────────────────

describe('decision-store — Phase 3 compaction', () => {
    it('seed 600, ack 550, append once: line count < 600 and the 50 unacked ids survive', () => {
        const dir = tmpDir();

        // Write 600 decisions directly (bypasses appendDecision compaction overhead)
        const allIds = seedRaw(dir, 600);
        const toAck = allIds.slice(0, 550);
        const unackedIds = new Set(allIds.slice(550));  // ids 550-599

        // Acking 550 appends 550 tombstones (total 1150 lines), compactIfNeeded fires,
        // keeps only the 50 unacked decisions.
        ackDecisions(toAck, dir);

        // One more append — verifies the compacted file is still writable
        const newRec = appendDecision({ slug: 'after-compact', action: 'queue', actor: '1' }, dir);

        const file = path.join(dir, DECISIONS_FILE);
        const lines = fs.readFileSync(file, 'utf8').split('\n').filter(l => l.trim());
        expect(lines.length).toBeLessThan(600);

        const pending = readPending(dir);
        // All 50 original unacked ids must survive compaction
        for (const id of unackedIds) {
            expect(pending.some(p => p.id === id)).toBe(true);
        }
        // The new record must also be present
        expect(pending.some(p => p.id === newRec.id)).toBe(true);
        // Total: 50 originals + 1 new
        expect(pending).toHaveLength(51);
    });

    it('seed 400, append once: no compaction, line count is 401, no .tmp file', () => {
        const dir = tmpDir();
        seedRaw(dir, 400);

        const newRec = appendDecision({ slug: 'extra-slug', action: 'queue', actor: '1' }, dir);

        const file = path.join(dir, DECISIONS_FILE);
        const content = fs.readFileSync(file, 'utf8');
        const lines = content.split('\n').filter(l => l.trim());
        expect(lines.length).toBe(401);

        // .tmp file must not exist
        expect(fs.existsSync(path.join(dir, DECISIONS_FILE + '.tmp'))).toBe(false);

        // Byte-identical tail: last non-empty line is the newly appended record
        const lastLine = lines[lines.length - 1];
        expect(JSON.parse(lastLine)).toEqual(newRec);
    });

    it('after compaction readdirSync(dir) contains only plan-decisions.jsonl', () => {
        const dir = tmpDir();

        // 501 decisions, ack all → compaction removes everything, leaving an empty file
        const ids = seedRaw(dir, 501);
        ackDecisions(ids, dir);

        const entries = fs.readdirSync(dir);
        expect(entries).toEqual([DECISIONS_FILE]);
    });

    it('acked-then-compacted id stays acked after re-ack, no throw', () => {
        const dir = tmpDir();

        // Seed enough records to breach threshold when all are acked
        const ids = seedRaw(dir, 501);
        ackDecisions(ids, dir);

        // All decisions + tombstones were compacted away
        expect(readPending(dir)).toHaveLength(0);

        // Re-acking a compacted id must not throw and must not resurrect the record
        const compactedId = ids[0];
        expect(() => ackDecisions([compactedId], dir)).not.toThrow();
        expect(readPending(dir)).toHaveLength(0);
    });
});
