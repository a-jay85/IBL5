import { GatewayIntentBits, Partials } from 'discord.js';
import type { ClientOptions } from 'discord.js';

// Kept apart from index.ts (which logs in on import) so a test can check it.
export const BUG_BOT_CLIENT_OPTIONS: ClientOptions = {
    intents: [
        // Fills the channel and thread cache. Without it, discord.js drops every
        // MessageCreate from a thread the bot did not create itself, so an operator
        // @mention in a thread the operator opened never reaches handleWake.
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,        // PRIVILEGED — must be enabled in the Developer Portal
        GatewayIntentBits.GuildMessageReactions,
    ],
    // Partials so MessageReactionAdd fires on uncached (pre-restart) messages;
    // handleReaction .fetch()es them before reading.
    partials: [Partials.Message, Partials.Reaction, Partials.Channel],
};
