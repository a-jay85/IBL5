import type {
    Client,
    Message,
    PartialMessage,
    MessageReaction,
    PartialMessageReaction,
    User,
    PartialUser,
} from 'discord.js';
import { config } from './config.js';
import * as phpClient from './php-client.js';
import { validateAndSanitize, downloadAttachment, type SanitizedAttachment } from './attachments.js';

// ── Pure routing function — the single most-tested unit ─────────────────────
// Takes a minimal plain shape (NOT a live Message) so tests need no discord.js
// mock. `clientUserId` is injected (rather than read off a live client) to keep
// classifyMessage pure.
export interface RoutableMessage {
    authorId: string;
    isBot: boolean;
    channelId: string;
    isThread: boolean;
    parentId: string | null;
}
export type Route = 'enqueue' | 'thread-reply' | 'ignore';

export function classifyMessage(msg: RoutableMessage, clientUserId: string): Route {
    if (msg.isBot || msg.authorId === clientUserId) return 'ignore';
    if (msg.channelId === config.bugChannelId && !msg.isThread) return 'enqueue';
    if (msg.isThread && msg.parentId === config.bugChannelId) return 'thread-reply';
    return 'ignore';
}

// Adapter mapping a live Message → the plain RoutableMessage shape. Co-located
// here so it is covered by handlers.test.ts.
export function toRoutable(message: Message): RoutableMessage {
    const inThread = message.channel.isThread();
    return {
        authorId: message.author.id,
        isBot: message.author.bot,
        channelId: message.channelId,
        isThread: inThread,
        parentId: inThread ? message.channel.parentId : null,
    };
}

// Capture up to 10 attachments off the message: sanitize (drop rejects), download the
// image survivors in parallel, and shape them for the enqueue wire. A single Promise.all
// keeps a 10-image report near one download's latency, not ten sequential ones — critical
// because backfill.ts awaits handleEnqueue in a loop with no per-message catch. The whole
// block degrades to [] on any failure: a broken capture must cost the screenshots, never
// the report (the same posture as the swallowed react ack below).
async function captureAttachments(msg: Message): Promise<phpClient.AttachmentInput[]> {
    try {
        const survivors = [...msg.attachments.values()]
            .slice(0, 10)
            .map((a) => validateAndSanitize(msg.id, a))
            .filter((s): s is SanitizedAttachment => s !== null);
        const localPaths = await Promise.all(survivors.map((s) => downloadAttachment(s)));
        return survivors.map((s, i) => ({
            attachment_id: s.attachmentId,
            original_url: s.originalUrl,
            local_path: localPaths[i], // null when the download failed → degrade to URL-only
            filename: s.filename,
            content_type: s.contentType,
            file_size: s.fileSize,
        }));
    } catch (err) {
        console.error('handleEnqueue: attachment capture failed (degrading to text-only):', err);
        return [];
    }
}

// ── Shared enqueue path — Phase 6 backfill calls this EXACT function ─────────
// All *_id fields are the string values discord.js already provides — NEVER
// Number()/parseInt.
export async function handleEnqueue(msg: Message): Promise<void> {
    const attachments = await captureAttachments(msg);
    await phpClient.enqueue({
        author_id: msg.author.id,
        channel_id: msg.channelId,
        message_id: msg.id,
        text: msg.content,
        attachments,
    });
    // Instant ack (bot-side of "ack + thread"): react ✴️ so a GM sees pickup with no
    // cron round-trip. Also fires for Phase-6 backfilled messages (same shared path) —
    // desirable: it signals the bot picked the report up on catch-up, not just live.
    // Must never throw past this handler — Phase-6 backfill (backfill.ts) awaits
    // handleEnqueue in a plain loop with no per-message try/catch, so an uncaught
    // react rejection here would abort replay of every message after it.
    try {
        await msg.react('✴️');
    } catch (err) {
        console.error('handleEnqueue: react ack failed (swallowed):', err);
    }
}

// A thread's own channel id IS the thread id — that's what §3b thread-reply
// stamps last_gm_reply_at against.
export async function handleThreadReply(msg: Message): Promise<void> {
    await phpClient.threadReply({ thread_id: msg.channelId, message_id: msg.id });
}

// ── Reaction handler — resolves partials, ignores the bot's own reactions ────
export async function handleReaction(
    reaction: MessageReaction | PartialMessageReaction,
    user: User | PartialUser,
    clientUserId: string,
): Promise<void> {
    if (user.bot || user.id === clientUserId) return;            // ignore bot's own
    if (reaction.partial) await reaction.fetch();                // uncached post-restart
    if (reaction.message.partial) await reaction.message.fetch();

    // SCOPE GUARD (security + efficiency): only forward reactions on messages in the
    // bug channel or a thread under it — the ✅ approval lives on a message in a
    // bug-channel thread. GuildMessageReactions otherwise fires for EVERY reaction
    // anywhere in the guild, POSTing each to PHP and leaking every reactor_id off a
    // channel we don't own. Resolve partials FIRST so channel context is populated.
    const msgChannel = reaction.message.channel;
    const inBugScope =
        reaction.message.channelId === config.bugChannelId ||
        (msgChannel.isThread() && msgChannel.parentId === config.bugChannelId);
    if (!inBugScope) return;

    await phpClient.reaction({
        message_id: reaction.message.id,
        emoji: reaction.emoji.name ?? '',   // custom emoji may have null name
        reactor_id: user.id,
    });
}

// ── Source-message reconciliation: edits and deletes ────────────────────────
// `original_text` is snapshotted at enqueue and was never reconciled again, so a GM
// who edited in the missing repro steps left an awaiting_info row parked forever and
// a deleted report left a zombie row the tick retried every 180s. These two handlers
// push gateway state at PHP, which owns EVERY state decision — see the controllers.

// Fixed literals, following the /prMerged 'Fixed! ✅' precedent. The GM's report text
// is NEVER echoed back into Discord: interpolating attacker-controlled content into a
// bot message is exactly the injection surface this path has to stay clear of.
const SOURCE_EDITED_NOTE = 'ℹ️ The original report was edited after this thread was opened.';
const SOURCE_DELETED_NOTE = 'ℹ️ The original report was deleted.';

// Best-effort thread note. Swallows its own failures: the DB write already landed and
// is what the pipeline runs on — a send failure must never surface as a handler throw.
async function postSourceNote(client: Client, threadId: string, note: string): Promise<void> {
    try {
        const thread = await client.channels.fetch(threadId);
        if (thread?.isSendable()) await thread.send(note);
    } catch (err) {
        console.error('source note post failed (swallowed):', err);
    }
}

export async function handleSourceUpdate(
    oldMessage: Message | PartialMessage,
    newMessage: Message | PartialMessage,
): Promise<void> {
    const msg = newMessage.partial ? await newMessage.fetch() : newMessage;

    if (msg.author.bot) return;

    // SCOPE GUARD: only a TOP-LEVEL bug-channel message is ever a row's source. A
    // thread reply is handled by handleThreadReply and must never rewrite original_text.
    if (msg.channelId !== config.bugChannelId || msg.channel.isThread()) return;

    // Cheap short-circuit, valid ONLY when both sides are real messages. This is an
    // optimisation, never the correctness boundary: Discord fires MessageUpdate on
    // embed hydration ~1s after any report containing a URL, frequently with a partial
    // `old` whose content is null. A listener-side comparison therefore cannot be
    // trusted — the authoritative one is PHP's `original_text === $text`.
    if (!oldMessage.partial && !newMessage.partial && oldMessage.content === msg.content) return;

    // Guard empty content BEFORE the call. A failed hydration must never overwrite the
    // stored snapshot with '' and revive the row to be reclassified against empty text.
    if (!msg.content) return;

    const r = await phpClient.sourceUpdated({ message_id: msg.id, text: msg.content });

    // A row that changed but was NOT revived is terminal or mid-flight (hunting,
    // pr_open, …): the tick will never re-classify it, so the humans already in its
    // thread are the only ones who can act on the new text. revived:true posts nothing
    // — the next tick re-classifies and speaks for itself.
    if (r.matched && r.changed && !r.revived && r.thread_id) {
        await postSourceNote(msg.client, r.thread_id, SOURCE_EDITED_NOTE);
    }
}

export async function handleSourceDelete(message: Message | PartialMessage): Promise<void> {
    // A deleted message is ALWAYS partial and can never be fetched — only `id` and
    // `channelId` are reliably present, so neither content nor author is available.
    // No bot/author filter is possible OR needed: an unmatched snowflake comes back
    // matched:false and no-ops.
    if (message.channelId !== config.bugChannelId) return;

    const r = await phpClient.sourceDeleted({ message_id: message.id });

    // dropped:false on a matched row means a human or hunter is mid-flight on it
    // (hunting / pr_open / …). markSourceDeleted deliberately leaves that status
    // alone; the thread just needs to know its source is gone.
    if (r.matched && !r.dropped && r.thread_id) {
        await postSourceNote(message.client, r.thread_id, SOURCE_DELETED_NOTE);
    }
}

// ── Optional convenience dispatcher (thin) ──────────────────────────────────
// index.ts stays a wiring shell; the tested logic lives in classifyMessage + the
// handlers above.
export async function routeAndHandle(message: Message, clientUserId: string): Promise<void> {
    const route = classifyMessage(toRoutable(message), clientUserId);
    if (route === 'enqueue') await handleEnqueue(message);
    else if (route === 'thread-reply') await handleThreadReply(message);
    // 'ignore' → no-op
}
