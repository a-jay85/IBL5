import type {
    Message,
    MessageReaction,
    PartialMessageReaction,
    User,
    PartialUser,
} from 'discord.js';
import { config } from './config.js';
import * as phpClient from './php-client.js';

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

// ── Shared enqueue path — Phase 6 backfill calls this EXACT function ─────────
// All *_id fields are the string values discord.js already provides — NEVER
// Number()/parseInt.
export async function handleEnqueue(msg: Message): Promise<void> {
    await phpClient.enqueue({
        author_id: msg.author.id,
        channel_id: msg.channelId,
        message_id: msg.id,
        text: msg.content,
    });
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

// ── Optional convenience dispatcher (thin) ──────────────────────────────────
// index.ts stays a wiring shell; the tested logic lives in classifyMessage + the
// handlers above.
export async function routeAndHandle(message: Message, clientUserId: string): Promise<void> {
    const route = classifyMessage(toRoutable(message), clientUserId);
    if (route === 'enqueue') await handleEnqueue(message);
    else if (route === 'thread-reply') await handleThreadReply(message);
    // 'ignore' → no-op
}
