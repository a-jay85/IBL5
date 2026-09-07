import { describe, it, expect } from 'vitest';

// Opt-in E2E smoke against the RUNNING pm2 bug-bot-test instance (port 50002).
// Everything in server.test.ts runs against a mocked discord.js — it proves
// handler logic only, never that the deployed test bot's Discord role actually
// holds Add Reactions. A missing Add Reactions permission fails SILENTLY in
// production (handlers.ts swallows react failures so backfill never aborts),
// so the only mechanical proof that the role bit is present is a live ✴️ round-
// trip through a real Discord API call.
//
// Default-SKIP because this drives real Discord writes. Prerequisites:
//   1. bin/bug-pipeline-test-env up (starts pm2 app ibl-bug-bot-test on :50002)
//   2. ibl5/IBLbot/.env.bugbot.test (example) filled in (test credentials, test guild)
//   3. BUG_BOT_E2E_MESSAGE_ID set to a snowflake of any existing message in the
//      test channel — it is used as the /reply-to-message anchor; the reply is
//      ephemeral (Discord has no /unreact and no /delete here, but a test channel
//      accumulates disposable messages).
//
//   BUG_BOT_E2E=1 BUG_BOT_E2E_MESSAGE_ID=<snowflake> npm test -- server.e2e
//
// config.js is deliberately NOT imported: it throws on missing env, which would
// break the default-skip path in CI.
const E2E  = process.env.BUG_BOT_E2E === '1';
const SEED = process.env.BUG_BOT_E2E_MESSAGE_ID ?? '';
const BASE = `http://127.0.0.1:${process.env.BUG_BOT_E2E_PORT ?? '50002'}`;
// Approver id sourced from env — same var bin/bug-pipeline-test-env exports.
// Required for /mention; never hardcoded.
const APPROVER = process.env.BUG_PIPELINE_APPROVER_ID ?? '';

async function post(path: string, body: unknown) {
    const res = await fetch(`${BASE}${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    return { status: res.status, text: await res.text() };
}

describe.runIf(E2E && SEED !== '')('E2E bug-bot smoke (opt-in, port 50002)', () => {
    // Unique per-run marker so /get-thread-messages can assert the text is present
    // without relying on channel history from previous runs.
    const MARKER = `e2e-smoke-${Date.now()}`;

    // Ids accumulated across the sequential steps.
    let replyId  = '';
    let threadId = '';

    // ── 1. Liveness ──────────────────────────────────────────────────────────
    it('GET / returns ok', async () => {
        const res = await fetch(`${BASE}/`);
        expect(res.status).toBe(200);
        expect(await res.text()).toBe('ok');
    });

    // ── 2. Bootstrap: reply to seed message to get a fresh in-channel anchor ─
    // /react (step 6) fetches from config.bugChannelId → only in-channel message
    // ids are valid targets. Threading directly off SEED would also work once,
    // but Discord allows only one thread per message — a fresh reply keeps SEED
    // reusable across test runs.
    it('POST /reply-to-message seeds a fresh in-channel anchor', async () => {
        const res = await post('/reply-to-message', { message_id: SEED, message: MARKER });
        expect(res.status).toBe(200);
        const body = JSON.parse(res.text) as { message_id: string };
        expect(typeof body.message_id).toBe('string');
        replyId = body.message_id;
    });

    // ── 3. Create thread off the reply ───────────────────────────────────────
    it('POST /create-thread returns a thread_id string', async () => {
        const res = await post('/create-thread', { message_id: replyId, name: `E2E ${MARKER}` });
        expect(res.status).toBe(200);
        const body = JSON.parse(res.text) as { thread_id: string };
        expect(typeof body.thread_id).toBe('string');
        threadId = body.thread_id;
    });

    // ── 4. Post a message into the thread ────────────────────────────────────
    it('POST /post-to-thread returns 200', async () => {
        const res = await post('/post-to-thread', { thread_id: threadId, message: `posted-${MARKER}` });
        expect(res.status).toBe(200);
    });

    // ── 5. Mention the approver in the thread ────────────────────────────────
    it('POST /mention returns a message_id string', async () => {
        const res = await post('/mention', {
            thread_id:  threadId,
            discord_id: APPROVER,
            message:    `mention-${MARKER}`,
        });
        expect(res.status).toBe(200);
        const body = JSON.parse(res.text) as { message_id: string };
        expect(typeof body.message_id).toBe('string');
    });

    // ── 6. React to the in-channel reply (proves the role holds Add Reactions) ─
    // /react fetches the message from config.bugChannelId, so the target must be
    // an in-channel message (not a thread post). replyId satisfies that constraint.
    it('POST /react on in-channel message returns "reacted" — proves Add Reactions bit', async () => {
        const res = await post('/react', { message_id: replyId, emoji: '✴️' });
        expect(res.status).toBe(200);
        expect(res.text).toBe('reacted');
    });

    // ── 7. Negative: bogus message_id → 500 (proves the positive isn't a stub) ─
    it('POST /react with message_id "1" returns 500', async () => {
        const res = await post('/react', { message_id: '1', emoji: '✴️' });
        expect(res.status).toBe(500);
    });

    // ── 8. Thread messages include the posted marker ──────────────────────────
    it('POST /get-thread-messages — posted text is present', async () => {
        const res = await post('/get-thread-messages', { thread_id: threadId });
        expect(res.status).toBe(200);
        const body = JSON.parse(res.text) as { messages: Array<{ content: string }> };
        const found = body.messages.some((m) => m.content.includes(`posted-${MARKER}`));
        expect(found).toBe(true);
    });
});

describe.skipIf(E2E && SEED !== '')('E2E bug-bot smoke (opt-in, port 50002)', () => {
    it.skip(
        'SKIP: set BUG_BOT_E2E=1 + BUG_BOT_E2E_MESSAGE_ID=<snowflake> and run bin/bug-pipeline-test-env first',
        () => {},
    );
});
