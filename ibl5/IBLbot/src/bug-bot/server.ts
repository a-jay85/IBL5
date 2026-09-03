import express from 'express';
import type { Client } from 'discord.js';
import { config } from './config.js';
import * as phpClient from './php-client.js';

/**
 * The 6 §3c loopback Express endpoints the PR #5 cron calls to make the bot
 * perform Discord actions. This is the NEW security surface (gate 14b): the ONLY
 * access control is the loopback bind (127.0.0.1) + per-field input validation.
 *
 * WIRE FORMAT: handlers read top-level `req.body.*` (NOT `content`-wrapped) — the
 * §3c callers (PR #5 cron / PR #6 GH-action) POST top-level. Do NOT re-introduce a
 * `content` wrapper.
 *
 * All ids are strings — NEVER Number()/parseInt; discord.js accepts string
 * snowflakes directly. `pr_number` is the one numeric field.
 */

/**
 * discord.js raises DiscordAPIError code 10008 ("Unknown Message") when the source
 * message no longer exists — the GM deleted their own report. Duck-typed on `.code`
 * rather than `instanceof DiscordAPIError` so the check survives a re-thrown or
 * wrapped error and stays assertable from the unit tests without constructing a real
 * REST error object.
 */
function isUnknownMessage(error: unknown): boolean {
    return typeof error === 'object' && error !== null
        && (error as { code?: unknown }).code === 10008;
}

// Splits text at ≤CHUNK_MAX chars, preferring line breaks over mid-word cuts.
// Each chunk after the first is prefixed with "(N/M): " so a reader knows more follows.
const CHUNK_MAX = 1900;
export function chunkMessage(text: string): string[] {
  if (text.length <= CHUNK_MAX) return [text];
  const chunks: string[] = [];
  let remaining = text;
  while (remaining.length > CHUNK_MAX) {
    let cut = remaining.lastIndexOf('\n', CHUNK_MAX);
    if (cut <= 0) cut = remaining.lastIndexOf(' ', CHUNK_MAX);
    if (cut <= 0) cut = CHUNK_MAX;
    chunks.push(remaining.slice(0, cut));
    remaining = remaining.slice(cut).trimStart();
  }
  if (remaining.length > 0) chunks.push(remaining);
  const total = chunks.length;
  return chunks.map((c, i) => (i === 0 ? c : `(${i + 1}/${total}): ${c}`));
}

export function startBugBotServer(client: Client): void {
    const app = express();
    app.use(express.json());

    // POST /react — { message_id, emoji }
    app.post('/react', async (req, res) => {
        const { message_id, emoji } = req.body ?? {};
        if (!message_id || !emoji) {
            res.status(400).send('Missing message_id or emoji');
            return;
        }
        try {
            const channel = await client.channels.fetch(config.bugChannelId);
            if (!channel || !channel.isTextBased()) {
                res.status(500).send('Bug channel is not text-based');
                return;
            }
            const message = await channel.messages.fetch(message_id);
            await message.react(emoji);
            res.send('reacted');
        } catch (error) {
            console.error('/react failed:', error);
            res.status(500).send('Failed to react');
        }
    });

    // POST /create-thread — { message_id, name } → { thread_id }
    app.post('/create-thread', async (req, res) => {
        const { message_id, name } = req.body ?? {};
        if (!message_id || !name) {
            res.status(400).send('Missing message_id or name');
            return;
        }
        try {
            const channel = await client.channels.fetch(config.bugChannelId);
            if (!channel || !channel.isTextBased()) {
                res.status(500).send('Bug channel is not text-based');
                return;
            }
            const message = await channel.messages.fetch(message_id);
            const thread = await message.startThread({ name });
            res.json({ thread_id: thread.id });   // thread_id is a string snowflake
        } catch (error) {
            // The GM deleted their report before the tick got here. 410 tells the
            // caller "gone, stop retrying" — bin/bug-pipeline-tick treats it exactly
            // like its existing create-thread failure path: log, no thread, row
            // unchanged. /get-message is the single deletion-detection point; a
            // message deleted between the two calls self-heals on the next tick.
            if (isUnknownMessage(error)) {
                res.status(410).send('Source message deleted');
                return;
            }
            console.error('/create-thread failed:', error);
            res.status(500).send('Failed to create thread');
        }
    });

    // POST /post-to-thread — { thread_id, message }
    app.post('/post-to-thread', async (req, res) => {
        const { thread_id, message } = req.body ?? {};
        if (!thread_id || !message) {
            res.status(400).send('Missing thread_id or message');
            return;
        }
        try {
            const channel = await client.channels.fetch(thread_id);
            if (!channel || !channel.isSendable()) {
                res.status(500).send('Thread is not sendable');
                return;
            }
            const chunks = chunkMessage(message);
            for (const chunk of chunks) { await channel.send(chunk); }
            res.json({ ok: true, chunks: chunks.length });
        } catch (error) {
            console.error('/post-to-thread failed:', error);
            res.status(500).send('Failed to post to thread');
        }
    });

    // POST /mention — { thread_id, discord_id, message } → { message_id }
    // The returned message_id is recorded by PR #5 as the row's
    // approval_message_id — the message A-Jay reacts ✅ to. Returning it is a HARD
    // contract requirement, not cosmetic.
    app.post('/mention', async (req, res) => {
        const { thread_id, discord_id, message } = req.body ?? {};
        if (!thread_id || !discord_id || !message) {
            res.status(400).send('Missing thread_id, discord_id, or message');
            return;
        }
        try {
            const channel = await client.channels.fetch(thread_id);
            if (!channel || !channel.isSendable()) {
                res.status(500).send('Thread is not sendable');
                return;
            }
            const sent = await channel.send(`<@${discord_id}> ${message}`);
            res.json({ message_id: sent.id });   // the posted mention's id (string)
        } catch (error) {
            console.error('/mention failed:', error);
            res.status(500).send('Failed to mention');
        }
    });

    // POST /get-thread-messages — { thread_id } → { messages: [...] }
    // The cron→Haiku live transcript (decision 7). Explicit limit 100 (default is
    // only 50). discord.js returns newest-first — sort ascending before mapping.
    app.post('/get-thread-messages', async (req, res) => {
        const { thread_id } = req.body ?? {};
        if (!thread_id) {
            res.status(400).send('Missing thread_id');
            return;
        }
        try {
            const channel = await client.channels.fetch(thread_id);
            if (!channel || !channel.isTextBased()) {
                res.status(500).send('Thread is not text-based');
                return;
            }
            const msgs = await channel.messages.fetch({ limit: 100 });
            res.json({
                messages: [...msgs.values()]
                    .sort((a, b) => a.createdTimestamp - b.createdTimestamp)
                    .map((m) => ({
                        author_id: m.author.id,     // string
                        content: m.content,
                        ts: m.createdTimestamp,     // numeric
                        // Metadata only — thread-reply bytes are ephemeral (decision 3); Phase 7
                        // renders these as text. Always an array ([] for text-only) so the
                        // driver's jq never sees null.
                        attachments: [...m.attachments.values()].slice(0, 10).map((a) => ({
                            id: a.id,
                            url: a.url,
                            proxy_url: a.proxyURL,
                            name: a.name,
                            content_type: a.contentType,
                        })),
                    })),
            });
        } catch (error) {
            console.error('/get-thread-messages failed:', error);
            res.status(500).send('Failed to fetch thread messages');
        }
    });

    // POST /prMerged — { pr_number }. Resolve pr_number → thread_id (gap #2). A null
    // thread is a boundary no-op (the PR may predate the pipeline), NOT an error.
    app.post('/prMerged', async (req, res) => {
        const { pr_number } = req.body ?? {};
        if (pr_number === undefined || pr_number === null) {
            res.status(400).send('Missing pr_number');
            return;
        }
        try {
            const { thread_id } = await phpClient.threadByPr({ pr_number });
            if (thread_id === null) {
                console.log(`/prMerged: no thread for PR #${pr_number}`);
                res.status(200).send('no thread for PR');
                return;
            }
            const channel = await client.channels.fetch(thread_id);
            if (!channel || !channel.isSendable()) {
                res.status(500).send('Thread is not sendable');
                return;
            }
            await channel.send('Fixed! ✅');   // fixed literal — no caller input interpolated
            res.status(200).send('fixed');
        } catch (error) {
            console.error('/prMerged failed:', error);
            res.status(500).send('Failed to post fixed confirmation');
        }
    });

    // POST /get-message — { message_id } → ALWAYS HTTP 200 { content, deleted }.
    // The 200-always shape is deliberate. bin/bug-pipeline-tick reads this inside a
    // $( ) subshell immediately before building the classify prompt; signalling
    // deletion through an HTTP status would force a `curl -w '%{http_code}'` channel
    // that has to cross that subshell via a tempfile, where a stale 410 from one row
    // could leak into the next row's decision and drop a healthy report. Carrying
    // `deleted` in the body instead makes failing OPEN the default: a bot restart, an
    // unparseable body, or any generic error all land on `deleted:false` and the tick
    // falls through on its stored text.
    app.post('/get-message', async (req, res) => {
        const { message_id } = req.body ?? {};
        if (!message_id) {
            res.status(400).send('Missing message_id');
            return;
        }
        try {
            const channel = await client.channels.fetch(config.bugChannelId);
            if (!channel || !channel.isTextBased()) {
                res.status(500).send('Bug channel is not text-based');
                return;
            }
            const message = await channel.messages.fetch(message_id);
            res.json({ content: message.content, deleted: false });
        } catch (error) {
            if (isUnknownMessage(error)) {
                res.json({ content: null, deleted: true });
                return;
            }
            console.error('/get-message failed:', error);
            res.json({ content: null, deleted: false });   // fail open
        }
    });

    // POST /reply-to-message — { message_id, message } → { message_id: sent.id }
    // Replies IN-CHANNEL, unlike /post-to-thread (needs a thread_id) and /mention
    // (posts into a thread). The not_a_thing drop has no thread to post into, so this
    // is the only way the GM ever hears back. 10008 → 410; other failures → 500.
    app.post('/reply-to-message', async (req, res) => {
        const { message_id, message } = req.body ?? {};
        if (!message_id || !message) {
            res.status(400).send('Missing message_id or message');
            return;
        }
        try {
            const channel = await client.channels.fetch(config.bugChannelId);
            if (!channel || !channel.isTextBased()) {
                res.status(500).send('Bug channel is not text-based');
                return;
            }
            const source = await channel.messages.fetch(message_id);
            const sent = await source.reply(message);
            res.json({ message_id: sent.id });   // the posted reply's id (string)
        } catch (error) {
            if (isUnknownMessage(error)) {
                res.status(410).send('Source message deleted');
                return;
            }
            console.error('/reply-to-message failed:', error);
            res.status(500).send('Failed to reply');
        }
    });

    // GET / — health check
    app.get('/', (_req, res) => {
        res.send('ok');
    });

    // LOOPBACK BIND '127.0.0.1' IS MANDATORY — THE security control (gate 14b).
    // Never bind 0.0.0.0 / omit the host arg.
    app.listen(config.express.port, '127.0.0.1', () => {
        console.log(`Bug-bot Express server listening on 127.0.0.1:${config.express.port}`);
    });
}
