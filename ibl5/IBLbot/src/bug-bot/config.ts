import dotenv from 'dotenv';
import * as path from 'path';

// cwd = IBLbot for BOTH the prod bot and the bug-bot. Load the bug-bot's own
// dotenv file explicitly so it never inherits the prod bot's DISCORD_TOKEN — an
// unqualified dotenv.config() would read the prod .env from the shared cwd. No
// `override`, so a real process-env value still wins (matches prod behavior).
dotenv.config({ path: path.resolve(process.cwd(), '.env.bugbot') });

function requireEnv(key: string): string {
    const value = process.env[key];
    if (!value) {
        throw new Error(`Missing required environment variable: ${key}`);
    }
    return value;
}

export const config = {
    discord: {
        // The SECOND Discord app's token — distinct from prod DISCORD_TOKEN.
        token: requireEnv('BUG_BOT_DISCORD_TOKEN'),
        // Optional — only used if the bug-bot ever registers slash commands.
        clientId: process.env['DISCORD_CLIENT_ID'] ?? '',
        guildId: process.env['DISCORD_GUILD_ID'] ?? '',
    },
    phpApi: {
        // Always-up main stack incl. the /ibl5 app prefix (http://main.localhost/ibl5)
        // — serves master's endpoints; NOT a worktree slug (torn down on merge) nor
        // prod. php-client builds `${baseUrl}/api/v1/bug-pipeline/<endpoint>`.
        baseUrl: requireEnv('BUG_PIPELINE_API_BASE_URL'),
        // Reused as the X-API-Key header value on the §3b POSTs.
        key: requireEnv('API_KEY'),
    },
    express: {
        // Default 50001 — distinct from the prod bot's 50000 so both loopback
        // servers coexist on the Mac.
        port: parseInt(process.env['EXPRESS_PORT'] ?? '50001', 10),
    },
    // Dedicated bug-report channel snowflake — injected via env, NEVER hardcoded.
    // Keep it a string (snowflake wire-type LOCKED — never parseInt it).
    bugChannelId: requireEnv('BUG_CHANNEL_ID'),
} as const;
