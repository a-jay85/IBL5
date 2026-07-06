import { Client, Events, GatewayIntentBits, Partials } from 'discord.js';
import { config } from './config.js';
import {
    classifyMessage,
    toRoutable,
    handleEnqueue,
    handleThreadReply,
    handleReaction,
} from './handlers.js';
import { startBugBotServer } from './server.js';
import { runBackfill } from './backfill.js';

const client = new Client({
    intents: [
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,        // PRIVILEGED — must be enabled in the Developer Portal
        GatewayIntentBits.GuildMessageReactions,
    ],
    // Partials so MessageReactionAdd fires on uncached (pre-restart) messages;
    // handleReaction .fetch()es them before reading.
    partials: [Partials.Message, Partials.Reaction, Partials.Channel],
});

// Backfill gate: live MessageCreate handling WAITS for the ClientReady backfill to
// finish, so the oldest-first replay and live traffic never race the enqueue/cursor
// path. A live message that also falls in the backfill window is deduped downstream
// by PR #3's idempotent enqueue (findByOriginalMessageId), so buffer-then-process is
// safe. The gate is released in a `finally`, so even a thrown backfill lets live
// traffic through (never a permanent hang).
let resolveBackfill!: () => void;
const backfillReady = new Promise<void>((resolve) => {
    resolveBackfill = resolve;
});

client.once(Events.ClientReady, async (c) => {
    console.log(`Bug-bot ready as ${c.user.tag}`);
    try {
        await runBackfill(client);
    } catch (err) {
        console.error('Backfill failed (continuing live):', err);   // MUST NOT crash the bot
    } finally {
        resolveBackfill();   // OPEN THE GATE even if backfill threw — else live msgs hang forever
    }
});

client.on(Events.MessageCreate, async (message) => {
    try {
        await backfillReady;   // hold live enqueues until backfill completes
        const clientUserId = client.user?.id ?? '';
        const route = classifyMessage(toRoutable(message), clientUserId);
        if (route === 'enqueue') await handleEnqueue(message);
        else if (route === 'thread-reply') await handleThreadReply(message);
        // 'ignore' → no-op
    } catch (err) {
        console.error('MessageCreate handler error:', err);
    }
});

// MessageReactionAdd is deliberately NOT gated: reactions advance state via PR #3's
// idempotent advanceOnApproval UPDATE and have no backfill to race.
client.on(Events.MessageReactionAdd, async (reaction, user) => {
    try {
        const clientUserId = client.user?.id ?? '';
        await handleReaction(reaction, user, clientUserId);
    } catch (err) {
        console.error('MessageReactionAdd handler error:', err);
    }
});

// Bootstrap (order matters): start the loopback server unconditionally so the cron
// endpoints are reachable even while the gateway is mid-connect (discord.js queues
// actions).
void client.login(config.discord.token);
startBugBotServer(client);
