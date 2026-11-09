import { describe, it, expect } from 'vitest';
import { GatewayIntentBits, Partials } from 'discord.js';
import { BUG_BOT_CLIENT_OPTIONS } from './client-options.js';

describe('BUG_BOT_CLIENT_OPTIONS', () => {
    const intents = BUG_BOT_CLIENT_OPTIONS.intents as GatewayIntentBits[];

    it('requests Guilds so messages in operator-created threads are delivered', () => {
        expect(intents).toContain(GatewayIntentBits.Guilds);
    });

    it('keeps the message, content, and reaction intents', () => {
        expect(intents).toEqual(expect.arrayContaining([
            GatewayIntentBits.GuildMessages,
            GatewayIntentBits.MessageContent,
            GatewayIntentBits.GuildMessageReactions,
        ]));
    });

    it('keeps the partials that let reactions fire on uncached messages', () => {
        expect(BUG_BOT_CLIENT_OPTIONS.partials).toEqual([
            Partials.Message, Partials.Reaction, Partials.Channel,
        ]);
    });
});
