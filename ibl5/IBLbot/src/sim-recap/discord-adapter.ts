import type { Client } from 'discord.js';
import type { EnabledSimRecapSettings, SimRecapSettings } from './settings.js';
import type { SimRecapApi } from './api.js';
import { createSimRecapApi } from './api.js';
import { searchGif } from './giphy.js';
import { createSweeper, type Sweeper } from './sweeper.js';
import type { PublisherDeps } from './publisher.js';
import { createErrorReporter, type ErrorReporter } from './error-reporter.js';

export class GuildMismatchError extends Error {
    constructor() {
        super('sim-recap: channel belongs to a different guild than the publish target');
        this.name = 'GuildMismatchError';
    }
}

export interface AdapterOptions {
    client: Client;
    settings: EnabledSimRecapSettings;
    api: Pick<SimRecapApi, 'progress' | 'finish' | 'enabled'>;
    token: string;
    log?: (line: string) => void;
    sleep?: (ms: number) => Promise<void>;
    searchGifFn?: typeof searchGif;
}

export function createPublisherDeps(options: AdapterOptions): PublisherDeps {
    const { client, settings, api, token } = options;
    const log = options.log ?? ((line: string) => console.log(line));
    const sleep = options.sleep ?? ((ms: number) => new Promise<void>(resolve => setTimeout(resolve, ms)));
    const gifSearch = options.searchGifFn ?? searchGif;

    async function sendableChannel(channelId: string) {
        const channel = await client.channels.fetch(channelId);
        if (!channel || !channel.isSendable()) {
            throw new Error('sim-recap: target channel is not sendable');
        }
        if (!('guildId' in channel) || channel.guildId !== settings.guildId) {
            throw new GuildMismatchError();
        }
        return channel;
    }

    return {
        async send(channelId, content, allowed) {
            const channel = await sendableChannel(channelId);
            const message = await channel.send({ content, allowedMentions: allowed });
            return { id: message.id };
        },

        async startThread(channelId, messageId, name) {
            const channel = await sendableChannel(channelId);
            if (!('messages' in channel)) {
                throw new Error('sim-recap: channel cannot hold threads');
            }
            const message = await channel.messages.fetch(messageId);
            const thread = await message.startThread({ name, autoArchiveDuration: 1440 });
            return { id: thread.id };
        },

        async lastOwnMessage(channelId) {
            const channel = await sendableChannel(channelId);
            if (!('messages' in channel)) {
                return null;
            }
            const botId = client.user?.id;
            const recent = await channel.messages.fetch({ limit: 10 });
            const own = [...recent.values()]
                .filter(message => message.author.id === botId)
                .sort((a, b) => b.createdTimestamp - a.createdTimestamp)[0];
            if (!own) return null;
            return { id: own.id, content: own.content, threadId: own.thread?.id ?? null };
        },

        searchGif(query) {
            return gifSearch(query, settings.giphyKey, fetch, Math.random, log);
        },

        progress(sim, step, threadId) {
            return api.progress(sim, token, step, threadId);
        },

        finish(sim) {
            return api.finish(sim, token);
        },

        postingEnabled() {
            return api.enabled();
        },

        sleep,
        log,
    };
}

/** Failure alerts exist only for an enabled publisher; a disabled one never alerts. */
export function reporterFor(settings: SimRecapSettings): ErrorReporter | undefined {
    if (!settings.enabled) return undefined;
    return createErrorReporter({ webhookUrl: settings.errorsWebhookUrl });
}

/** Production wiring: one sweeper whose per-process token backs every claim and progress call. */
export function createDiscordSweeper(client: Client, settings: SimRecapSettings): Sweeper {
    if (!settings.enabled) {
        return createSweeper({
            settings,
            api: { claimNext: async () => null, enabled: async () => false },
            depsFactory: () => { throw new Error('sim-recap publisher disabled'); },
        });
    }
    const api = createSimRecapApi({ baseUrl: settings.apiBaseUrl, apiKey: settings.apiKey });
    return createSweeper({
        settings,
        api,
        depsFactory: token => createPublisherDeps({ client, settings, api, token }),
        reporter: reporterFor(settings),
    });
}
