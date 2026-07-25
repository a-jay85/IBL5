import { PermissionsBitField } from 'discord.js';
import type { Client } from 'discord.js';
import { config } from './config.js';

// Every Discord permission the bug-bot's own surfaces need in the bug channel.
// Deliberately the ACTUAL set, not a generous one — a false alarm here trains
// people to ignore the log line. The bot never posts top-level in the channel
// (all output goes into threads), so SendMessages is NOT required.
export const REQUIRED_BUG_CHANNEL_PERMISSIONS: Record<string, bigint> = {
    // backfill + handlers read the channel at all
    ViewChannel: PermissionsBitField.Flags.ViewChannel,
    // backfill's messages.fetch over the replay window
    ReadMessageHistory: PermissionsBitField.Flags.ReadMessageHistory,
    // the ✴️ ack on a GM report (handlers.ts) and POST /react
    AddReactions: PermissionsBitField.Flags.AddReactions,
    // POST /create-thread — message.startThread()
    CreatePublicThreads: PermissionsBitField.Flags.CreatePublicThreads,
    // POST /post-to-thread, /mention, /prMerged
    SendMessagesInThreads: PermissionsBitField.Flags.SendMessagesInThreads,
};

export type PreflightResult =
    | { status: 'ok' }
    | { status: 'missing'; missing: string[] }
    | { status: 'unknown'; reason: string };

/**
 * Resolve which of REQUIRED_BUG_CHANNEL_PERMISSIONS the bot actually holds in the
 * bug channel. Never throws — an undeterminable answer is 'unknown', not an error,
 * because this runs on the ClientReady path and MUST NOT be able to abort startup.
 */
export async function checkBugChannelPermissions(client: Client): Promise<PreflightResult> {
    try {
        if (!client.user) return { status: 'unknown', reason: 'client.user is not populated yet' };

        const channel = await client.channels.fetch(config.bugChannelId);
        if (!channel) return { status: 'unknown', reason: `channel ${config.bugChannelId} did not resolve` };
        // DM/group channels have no permission overwrites to read.
        if (!('permissionsFor' in channel) || typeof channel.permissionsFor !== 'function' || !('guild' in channel)) {
            return { status: 'unknown', reason: 'channel is not a guild channel' };
        }

        // Both fetches are REQUIRED, and both are REST calls that need no extra intent.
        // The bot runs without GatewayIntentBits.Guilds, so neither the guild's roles
        // nor its own member are cached — verified live 2026-07-24: without roles.fetch()
        // permissionsFor THROWS on an undefined role, and without fetchMe() it returns
        // null. Passing client.user directly hits the same empty member cache.
        await channel.guild.roles.fetch();
        const me = await channel.guild.members.fetchMe();

        const perms = channel.permissionsFor(me);
        if (!perms) return { status: 'unknown', reason: 'permissionsFor returned null' };

        const missing = Object.entries(REQUIRED_BUG_CHANNEL_PERMISSIONS)
            .filter(([, flag]) => !perms.has(flag))
            .map(([name]) => name);

        return missing.length === 0 ? { status: 'ok' } : { status: 'missing', missing };
    } catch (err) {
        return { status: 'unknown', reason: `permission check threw: ${String(err)}` };
    }
}

/**
 * The startup half: log the result loudly. This exists because a missing permission
 * is otherwise INVISIBLE — handlers.ts swallows react/thread failures on purpose so a
 * single bad message can't abort backfill, which means a mis-scoped role degrades the
 * pipeline silently. One line at boot turns that into something you can see in
 * `pm2 logs ibl-bug-bot`.
 */
export async function logBugChannelPermissionPreflight(client: Client): Promise<PreflightResult> {
    const result = await checkBugChannelPermissions(client);

    if (result.status === 'ok') {
        console.log(`Permission preflight OK — bug channel ${config.bugChannelId}: ${Object.keys(REQUIRED_BUG_CHANNEL_PERMISSIONS).join(', ')}`);
    } else if (result.status === 'missing') {
        console.error(`Permission preflight FAILED — bug channel ${config.bugChannelId} is MISSING: ${result.missing.join(', ')}. The pipeline will fail SILENTLY on the affected step (ack/thread/post). Fix the bot role in Discord, then restart.`);
    } else {
        console.warn(`Permission preflight INCONCLUSIVE — ${result.reason}. Could not confirm bug-channel permissions.`);
    }

    return result;
}
