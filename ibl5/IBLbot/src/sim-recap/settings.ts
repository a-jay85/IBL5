// Sim-recap publisher settings. Every variable is optional on purpose: this module is separate
// from src/config.ts so its requireEnv set is untouched and a missing new variable can never
// crash-loop pm2. Disabled (the default) is a legal state.

// The guild ids mirror Discord::IBL_GUILD_ID / IBL_GUILD_ID_TESTING in ibl5/classes/Discord/Discord.php.
const PROD_GUILD_ID = '666986450889474053';
const TESTING_GUILD_ID = '399119059771195392';
const PROD_LIVE_SIMS_CHANNEL_ID = '682990441641279531';
const PROD_INJURIES_CHANNEL_ID = '684080867936960518';

export interface EnabledSimRecapSettings {
    enabled: true;
    target: 'testing' | 'prod';
    guildId: string;
    liveSimsChannelId: string;
    injuriesChannelId: string;
    stripMentions: boolean;
    apiBaseUrl: string;
    apiKey: string;
    giphyKey: string;
}

export type SimRecapSettings = { enabled: false } | EnabledSimRecapSettings;

function read(env: NodeJS.ProcessEnv, key: string): string {
    return (env[key] ?? '').trim();
}

export function loadSimRecapSettings(
    env: NodeJS.ProcessEnv = process.env,
    warn: (line: string) => void = console.warn,
): SimRecapSettings {
    const target = read(env, 'SIM_RECAP_PUBLISH_TARGET');
    if (target === '') {
        return { enabled: false };
    }

    let guildId: string;
    let liveSimsChannelId: string;
    let injuriesChannelId: string;
    let stripMentions: boolean;

    if (target === 'testing') {
        const testChannel = read(env, 'SIM_RECAP_TEST_CHANNEL_ID');
        if (testChannel === '') {
            warn('sim-recap: SIM_RECAP_PUBLISH_TARGET=testing needs SIM_RECAP_TEST_CHANNEL_ID; publisher disabled');
            return { enabled: false };
        }
        guildId = TESTING_GUILD_ID;
        liveSimsChannelId = testChannel;
        injuriesChannelId = testChannel;
        stripMentions = true;
    } else if (target === 'prod') {
        guildId = PROD_GUILD_ID;
        liveSimsChannelId = PROD_LIVE_SIMS_CHANNEL_ID;
        injuriesChannelId = PROD_INJURIES_CHANNEL_ID;
        stripMentions = false;
    } else {
        warn(`sim-recap: unknown SIM_RECAP_PUBLISH_TARGET "${target}"; publisher disabled`);
        return { enabled: false };
    }

    const apiKey = read(env, 'SIM_RECAP_API_KEY');
    if (apiKey === '') {
        warn('sim-recap: SIM_RECAP_API_KEY is empty; publisher disabled');
        return { enabled: false };
    }

    const apiBaseUrl = read(env, 'API_BASE_URL');
    if (apiBaseUrl === '') {
        warn('sim-recap: API_BASE_URL is empty; publisher disabled');
        return { enabled: false };
    }

    return {
        enabled: true,
        target,
        guildId,
        liveSimsChannelId,
        injuriesChannelId,
        stripMentions,
        apiBaseUrl,
        apiKey,
        giphyKey: read(env, 'GIPHY_API_KEY'),
    };
}
