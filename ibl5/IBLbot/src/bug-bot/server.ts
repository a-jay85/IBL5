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
            await channel.send(message);
            res.send('posted');
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
