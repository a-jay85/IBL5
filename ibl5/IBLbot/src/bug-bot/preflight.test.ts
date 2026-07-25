import { describe, it, expect, beforeEach, vi } from 'vitest';
import { PermissionsBitField } from 'discord.js';
import type { Client } from 'discord.js';

vi.mock('./config.js', () => ({
    config: { express: { port: 50001 }, bugChannelId: 'BUG_CHANNEL' },
}));

import {
    checkBugChannelPermissions,
    logBugChannelPermissionPreflight,
    REQUIRED_BUG_CHANNEL_PERMISSIONS,
} from './preflight.js';

const ALL = Object.values(REQUIRED_BUG_CHANNEL_PERMISSIONS);

// A channel whose permissionsFor reports exactly the supplied flags. Shaped like a
// real GuildTextChannel on the no-Guilds-intent path: the code must roles.fetch() and
// members.fetchMe() before permissionsFor can resolve anything (verified live).
function channelHolding(flags: bigint[]) {
    return {
        guild: {
            roles: { fetch: vi.fn().mockResolvedValue(undefined) },
            members: { fetchMe: vi.fn().mockResolvedValue({ id: 'BOT' }) },
        },
        permissionsFor: vi.fn(() => ({ has: (flag: bigint) => flags.includes(flag) })),
    };
}

function clientWith(channel: unknown): Client {
    return {
        user: { id: 'BOT', tag: 'bug-bot#0001' },
        channels: { fetch: vi.fn().mockResolvedValue(channel) },
    } as unknown as Client;
}

beforeEach(() => {
    vi.restoreAllMocks();
    vi.spyOn(console, 'log').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    vi.spyOn(console, 'error').mockImplementation(() => {});
});

describe('checkBugChannelPermissions', () => {
    it('ok when every required permission is held', async () => {
        const res = await checkBugChannelPermissions(clientWith(channelHolding(ALL)));
        expect(res).toEqual({ status: 'ok' });
    });

    it('names AddReactions when only that one is missing — the ✴️ ack case', async () => {
        const withoutReact = ALL.filter((f) => f !== PermissionsBitField.Flags.AddReactions);
        const res = await checkBugChannelPermissions(clientWith(channelHolding(withoutReact)));
        expect(res).toEqual({ status: 'missing', missing: ['AddReactions'] });
    });

    it('lists every missing permission, not just the first', async () => {
        const res = await checkBugChannelPermissions(clientWith(channelHolding([])));
        expect(res.status).toBe('missing');
        expect(res.status === 'missing' && res.missing).toEqual(Object.keys(REQUIRED_BUG_CHANNEL_PERMISSIONS));
    });

    it('unknown (not missing) when the channel does not resolve', async () => {
        const res = await checkBugChannelPermissions(clientWith(null));
        expect(res.status).toBe('unknown');
    });

    it('unknown when the channel has no permissionsFor (DM/group)', async () => {
        const res = await checkBugChannelPermissions(clientWith({ isTextBased: () => true }));
        expect(res.status).toBe('unknown');
    });

    it('unknown when permissionsFor returns null', async () => {
        const channel = { ...channelHolding([]), permissionsFor: vi.fn(() => null) };
        const res = await checkBugChannelPermissions(clientWith(channel));
        expect(res.status).toBe('unknown');
    });

    // Regression, found live 2026-07-24: the bot has no GatewayIntentBits.Guilds, so
    // neither roles nor its own member are cached. Skipping either fetch made the
    // preflight permanently INCONCLUSIVE (or throw) in production while every mocked
    // test still passed.
    it('fetches roles and its own member before resolving permissions', async () => {
        const channel = channelHolding(ALL);
        await checkBugChannelPermissions(clientWith(channel));
        expect(channel.guild.roles.fetch).toHaveBeenCalledTimes(1);
        expect(channel.guild.members.fetchMe).toHaveBeenCalledTimes(1);
        // and it asks about the fetched member, not client.user
        expect(channel.permissionsFor).toHaveBeenCalledWith({ id: 'BOT' });
    });

    it('unknown when roles.fetch rejects', async () => {
        const channel = channelHolding(ALL);
        channel.guild.roles.fetch = vi.fn().mockRejectedValue(new Error('rest 403'));
        const res = await checkBugChannelPermissions(clientWith(channel));
        expect(res.status).toBe('unknown');
    });

    it('never throws when channels.fetch rejects', async () => {
        const client = {
            user: { id: 'BOT' },
            channels: { fetch: vi.fn().mockRejectedValue(new Error('boom')) },
        } as unknown as Client;
        await expect(checkBugChannelPermissions(client)).resolves.toMatchObject({ status: 'unknown' });
    });

    it('unknown when client.user is not populated', async () => {
        const client = { user: null, channels: { fetch: vi.fn() } } as unknown as Client;
        const res = await checkBugChannelPermissions(client);
        expect(res.status).toBe('unknown');
    });
});

describe('logBugChannelPermissionPreflight', () => {
    it('console.errors on a missing permission — the whole point is visibility', async () => {
        const withoutReact = ALL.filter((f) => f !== PermissionsBitField.Flags.AddReactions);
        await logBugChannelPermissionPreflight(clientWith(channelHolding(withoutReact)));
        expect(console.error).toHaveBeenCalledTimes(1);
        expect(String(vi.mocked(console.error).mock.calls[0][0])).toContain('AddReactions');
    });

    it('logs (not errors) when everything is held', async () => {
        await logBugChannelPermissionPreflight(clientWith(channelHolding(ALL)));
        expect(console.error).not.toHaveBeenCalled();
        expect(console.log).toHaveBeenCalledTimes(1);
    });

    it('warns as inconclusive rather than claiming failure', async () => {
        await logBugChannelPermissionPreflight(clientWith(null));
        expect(console.warn).toHaveBeenCalledTimes(1);
        expect(console.error).not.toHaveBeenCalled();
    });
});
