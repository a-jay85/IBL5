import express from 'express';
import type { Client } from 'discord.js';
import { config } from './config.js';
import * as phpClient from './php-client.js';
import { checkBugChannelPermissions } from './preflight.js';

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

// Discord snowflake ids are 17-20 digit strings. Every id field the new routes accept
// is validated against this; a mismatch is a 400.
const SNOWFLAKE = /^[0-9]{17,20}$/;

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

    // Returns the channel when `id` is the bug channel or a thread whose parent is the
    // bug channel; otherwise null (the route answers 403). Stops the endpoints from
    // reaching any other channel the bot can see.
    async function resolveBugScopedChannel(id: string) {
        const channel = await client.channels.fetch(id);
        if (!channel) return null;
        if (id === config.bugChannelId) return channel;
        if (channel.isThread() && channel.parentId === config.bugChannelId) return channel;
        return null;
    }

    // Loud preflight for the scan and post routes. Both `missing` and `unknown` are 503:
    // the caller must not act on a bot whose permissions cannot be confirmed.
    async function requirePermissions(res: express.Response): Promise<boolean> {
        const result = await checkBugChannelPermissions(client);
        if (result.status === 'ok') return true;
        const detail = result.status === 'missing'
            ? `missing: ${result.missing.join(', ')}`
            : `unknown: ${result.reason}`;
        console.error(`Bug-bot permission preflight failed — bug channel ${config.bugChannelId} ${detail}`);
        res.status(503).json({ error: 'missing_permissions', detail });
        return false;
    }

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
            for (const chunk of chunks) { await channel.send({ content: chunk, allowedMentions: { parse: [] } }); }
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
        if (typeof discord_id !== 'string' || !SNOWFLAKE.test(discord_id)) {
            res.status(400).send('Invalid discord_id');
            return;
        }
        try {
            const channel = await client.channels.fetch(thread_id);
            if (!channel || !channel.isSendable()) {
                res.status(500).send('Thread is not sendable');
                return;
            }
            // Only the target user is pinged; a role or @everyone inside `message` is not.
            const sent = await channel.send({
                content: `<@${discord_id}> ${message}`,
                allowedMentions: { parse: [], users: [discord_id] },
            });
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
                        id: m.id,                   // string; the tick omits operator mentions by it
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
    // (posts into a thread). The roster_error redirect has no thread to post into, so
    // this is the only way that GM ever hears back. (The not_a_thing drop is silent by
    // design and uses no reply at all.) 10008 → 410; other failures → 500.
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

    // POST /edit-message — { channel_id, message_id, content }
    // Edits only the bot's own message, and only inside the bug channel or its threads.
    app.post('/edit-message', async (req, res) => {
        const { channel_id, message_id, content } = req.body ?? {};
        if (typeof channel_id !== 'string' || !SNOWFLAKE.test(channel_id)
            || typeof message_id !== 'string' || !SNOWFLAKE.test(message_id)) {
            res.status(400).send('Invalid channel_id or message_id');
            return;
        }
        if (typeof content !== 'string' || content.length < 1 || content.length > 2000) {
            res.status(400).send('content must be 1..2000 characters');
            return;
        }
        try {
            const channel = await resolveBugScopedChannel(channel_id);
            if (!channel) {
                res.status(403).json({ error: 'channel_out_of_scope' });
                return;
            }
            if (!channel.isTextBased()) {
                res.status(500).send('Channel is not text-based');
                return;
            }
            const message = await channel.messages.fetch(message_id);
            if (message.author.id !== client.user?.id) {
                res.status(403).json({ error: 'not_bot_author' });
                return;
            }
            await message.edit({ content, allowedMentions: { parse: [] } });
            res.json({ ok: true, message_id: message.id });
        } catch (error) {
            if (isUnknownMessage(error)) {
                res.status(410).send('Message deleted');
                return;
            }
            console.error('/edit-message failed:', error);
            res.status(500).send('Failed to edit message');
        }
    });

    // POST /list-threads — { archived, before? } → { threads, has_more, next_before }
    app.post('/list-threads', async (req, res) => {
        const { archived, before } = req.body ?? {};
        if (typeof archived !== 'boolean') {
            res.status(400).send('archived must be a boolean');
            return;
        }
        if (before !== undefined && (typeof before !== 'string' || !SNOWFLAKE.test(before))) {
            res.status(400).send('Invalid before');
            return;
        }
        try {
            if (!(await requirePermissions(res))) return;
            const channel = await client.channels.fetch(config.bugChannelId);
            if (!channel || !('threads' in channel)) {
                res.status(500).send('Bug channel has no threads');
                return;
            }
            const toWire = (t: { id: string; archived: boolean | null; locked: boolean | null }) => ({
                id: t.id,
                archived: t.archived ?? false,
                locked: t.locked ?? false,
            });
            if (!archived) {
                const active = await channel.threads.fetchActive();
                res.json({ threads: [...active.threads.values()].map(toWire), has_more: false, next_before: null });
                return;
            }
            const page = await channel.threads.fetchArchived({ type: 'public', before, limit: 100 });
            const threads = [...page.threads.values()].map(toWire);
            const last = threads[threads.length - 1];
            res.json({
                threads,
                has_more: page.hasMore,
                next_before: page.hasMore && last ? last.id : null,
            });
        } catch (error) {
            console.error('/list-threads failed:', error);
            res.status(500).send('Failed to list threads');
        }
    });

    // POST /scan-messages — { channel_id, before? } → { messages, next_before }
    // One page of 100; only the bot's own messages are returned. next_before is the
    // oldest id of the page (null at the end) so the caller can keep paging.
    app.post('/scan-messages', async (req, res) => {
        const { channel_id, before } = req.body ?? {};
        if (typeof channel_id !== 'string' || !SNOWFLAKE.test(channel_id)) {
            res.status(400).send('Invalid channel_id');
            return;
        }
        if (before !== undefined && (typeof before !== 'string' || !SNOWFLAKE.test(before))) {
            res.status(400).send('Invalid before');
            return;
        }
        try {
            if (!(await requirePermissions(res))) return;
            const channel = await resolveBugScopedChannel(channel_id);
            if (!channel) {
                res.status(403).json({ error: 'channel_out_of_scope' });
                return;
            }
            if (!channel.isTextBased()) {
                res.status(500).send('Channel is not text-based');
                return;
            }
            const page = [...(await channel.messages.fetch({ limit: 100, before })).values()];
            const botId = client.user?.id;
            const messages = page
                .filter((m) => m.author.id === botId)
                .map((m) => ({
                    id: m.id,
                    channel_id,
                    content: m.content,
                    reference_message_id: m.reference?.messageId ?? null,
                    created_timestamp: m.createdTimestamp,
                }));
            const oldest = page[page.length - 1];
            res.json({ messages, next_before: page.length >= 100 && oldest ? oldest.id : null });
        } catch (error) {
            console.error('/scan-messages failed:', error);
            res.status(500).send('Failed to scan messages');
        }
    });

    // POST /post-with-mention — { message, discord_id?, thread_id?, reply_to_message_id? }
    // Posts a close notice at most once: thread, else reply to the original, else the
    // bug channel. A lookup that cannot locate the target falls through; a send that
    // throws is a 502 and never tries the next target.
    app.post('/post-with-mention', async (req, res) => {
        const { message, discord_id, thread_id, reply_to_message_id } = req.body ?? {};
        if (typeof message !== 'string' || message.length === 0) {
            res.status(400).send('Missing message');
            return;
        }
        if (/@everyone|@here|<@/.test(message)) {
            res.status(400).send('message must not contain mention tokens');
            return;
        }
        for (const [name, value] of [['discord_id', discord_id], ['thread_id', thread_id], ['reply_to_message_id', reply_to_message_id]] as const) {
            if (value !== undefined && value !== null && (typeof value !== 'string' || !SNOWFLAKE.test(value))) {
                res.status(400).send(`Invalid ${name}`);
                return;
            }
        }
        const reporterId: string | null = discord_id ?? null;
        const content = reporterId ? `<@${reporterId}> ${message}` : message;
        if (content.length > 2000) {
            res.status(400).send('content exceeds 2000 characters');
            return;
        }
        const allowedMentions = { parse: [] as never[], users: reporterId ? [reporterId] : [], repliedUser: false };
        try {
            if (!(await requirePermissions(res))) return;

            // (1) the thread
            if (thread_id) {
                try {
                    const thread = await client.channels.fetch(thread_id);
                    if (thread && thread.isThread() && thread.parentId === config.bugChannelId && !thread.locked) {
                        let usable = true;
                        if (thread.archived) {
                            try {
                                await thread.setArchived(false, 'bug close notice');
                            } catch (unarchiveError) {
                                console.warn('/post-with-mention: unarchive failed, falling back:', unarchiveError);
                                usable = false;
                            }
                        }
                        if (usable) {
                            let sent;
                            try {
                                sent = await thread.send({ content, allowedMentions });
                            } catch (sendError) {
                                console.error('/post-with-mention thread send failed:', sendError);
                                res.status(502).send('Failed to send');
                                return;
                            }
                            res.json({ ok: true, target: 'thread', message_id: sent.id });
                            return;
                        }
                    }
                } catch (lookupError) {
                    console.warn('/post-with-mention: thread lookup failed, falling back:', lookupError);
                }
            }

            const bugChannel = await client.channels.fetch(config.bugChannelId);
            if (!bugChannel || !bugChannel.isTextBased()) {
                res.status(500).send('Bug channel is not text-based');
                return;
            }

            // (2) reply to the original report
            if (reply_to_message_id) {
                let original = null;
                try {
                    original = await bugChannel.messages.fetch(reply_to_message_id);
                } catch (lookupError) {
                    console.warn('/post-with-mention: original lookup failed, falling back:', lookupError);
                }
                if (original) {
                    let sent;
                    try {
                        sent = await original.reply({ content, allowedMentions });
                    } catch (sendError) {
                        console.error('/post-with-mention reply failed:', sendError);
                        res.status(502).send('Failed to send');
                        return;
                    }
                    res.json({ ok: true, target: 'reply', message_id: sent.id });
                    return;
                }
            }

            // (3) the bug channel
            if (!bugChannel.isSendable()) {
                res.status(500).send('Bug channel is not sendable');
                return;
            }
            let sent;
            try {
                sent = await bugChannel.send({ content, allowedMentions });
            } catch (sendError) {
                console.error('/post-with-mention channel send failed:', sendError);
                res.status(502).send('Failed to send');
                return;
            }
            res.json({ ok: true, target: 'channel', message_id: sent.id });
        } catch (error) {
            console.error('/post-with-mention failed:', error);
            res.status(500).send('Failed to post');
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
