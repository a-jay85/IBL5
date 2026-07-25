import { describe, it, expect } from 'vitest';

// Opt-in live smoke against the RUNNING pm2 bug-bot. Everything in
// server.test.ts runs against a mocked discord.js, so it proves handler LOGIC
// only — it can never prove the deployed bot's Discord role actually holds
// Add Reactions in the bug channel. That is an environment fact, and the ✴️
// ack depends on it: handlers.ts swallows a react failure so backfill can't
// abort, so a missing permission fails SILENTLY in production.
//
// Default-SKIP (same shape as bin/test-bug-pipeline-hunt's BUG_PIPELINE_HUNT_LIVE
// row) because this touches live Discord. There is no /unreact endpoint, so a
// run leaves a real reaction on a real message — point BUG_BOT_LIVE_MESSAGE_ID
// at a message the bot has ALREADY ✴️-acked and the run adds no visible state.
//
//   BUG_BOT_LIVE=1 BUG_BOT_LIVE_MESSAGE_ID=957965952618758154 npm test
//
// That id is a known-good target: it already carries the bot's ✴️ (verified
// green 2026-07-24), so re-running adds nothing visible to the channel.
//
// config.js is deliberately NOT imported: it throws on missing env, which would
// break the default-skip path in CI.
const LIVE = process.env.BUG_BOT_LIVE === '1';
const MESSAGE_ID = process.env.BUG_BOT_LIVE_MESSAGE_ID ?? '';
const BASE = `http://127.0.0.1:${process.env.BUG_BOT_LIVE_PORT ?? '50001'}`;

async function post(path: string, body: unknown) {
    const res = await fetch(`${BASE}${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    return { status: res.status, text: await res.text() };
}

describe.runIf(LIVE && MESSAGE_ID !== '')('live bug-bot /react (opt-in)', () => {
    it('reacts to a real bug-channel message — proves the role holds Add Reactions', async () => {
        const res = await post('/react', { message_id: MESSAGE_ID, emoji: '✴️' });
        expect(res.status).toBe(200);
        expect(res.text).toBe('reacted');
    });

    // Negative control: without this, a stub that always answers 'reacted'
    // would pass the row above and prove nothing.
    it('500s on a message id that does not exist in the bug channel', async () => {
        const res = await post('/react', { message_id: '1', emoji: '✴️' });
        expect(res.status).toBe(500);
    });
});

describe.skipIf(LIVE && MESSAGE_ID !== '')('live bug-bot /react (opt-in)', () => {
    it.skip('SKIP: set BUG_BOT_LIVE=1 + BUG_BOT_LIVE_MESSAGE_ID=<snowflake> (posts a real ✴️; no /unreact exists)', () => {});
});
