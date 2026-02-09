import dotenv from 'dotenv';

dotenv.config();

function requireEnv(key: string): string {
    const value = process.env[key];
    if (!value) {
        throw new Error(`Missing required environment variable: ${key}`);
    }
    return value;
}

export const config = {
    discord: {
        token: requireEnv('DISCORD_TOKEN'),
        clientId: requireEnv('DISCORD_CLIENT_ID'),
        guildId: process.env['DISCORD_GUILD_ID'] ?? '',
    },
    api: {
        baseUrl: requireEnv('API_BASE_URL'),
        key: requireEnv('API_KEY'),
    },
    express: {
        port: parseInt(process.env['EXPRESS_PORT'] ?? '50000', 10),
    },
} as const;
