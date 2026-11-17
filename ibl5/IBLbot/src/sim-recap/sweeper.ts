import crypto from 'crypto';
import type { SimRecapSettings } from './settings.js';
import type { SimRecapApi } from './api.js';
import { runPublish, type PublisherDeps, type PublishTarget } from './publisher.js';

export interface SweeperOptions {
    settings: SimRecapSettings;
    api: Pick<SimRecapApi, 'claimNext' | 'enabled'>;
    /** Builds the Discord/API-backed deps for one claim token. */
    depsFactory: (token: string) => PublisherDeps;
    intervalMs?: number;
    log?: (line: string) => void;
}

export interface Sweeper {
    kick(): Promise<void>;
    start(): NodeJS.Timeout | undefined;
}

export const SWEEP_INTERVAL_MS = 300000;

export function createSweeper(options: SweeperOptions): Sweeper {
    const { settings, api, depsFactory } = options;
    const intervalMs = options.intervalMs ?? SWEEP_INTERVAL_MS;
    const log = options.log ?? ((line: string) => console.log(line));
    // One token per process: a restarted bot gets a new token, so the old claim's heartbeat
    // goes stale and the row is reclaimed at last_step + 1.
    const token = crypto.randomUUID();
    let running = false;

    async function kick(): Promise<void> {
        if (!settings.enabled || running) return;
        running = true;
        try {
            const target: PublishTarget = {
                liveSimsChannelId: settings.liveSimsChannelId,
                injuriesChannelId: settings.injuriesChannelId,
                stripMentions: settings.stripMentions,
            };
            const deps = depsFactory(token);
            for (;;) {
                if (!(await api.enabled())) {
                    log('sim-recap: posting is switched off in the League Control Panel; not claiming');
                    return;
                }
                const claim = await api.claimNext(token);
                if (claim === null) return;
                const outcome = await runPublish(claim, target, deps);
                if (outcome === 'lost-claim') {
                    log(`sim-recap: sim ${claim.sim} claim lost; stopping this sweep`);
                    return;
                }
                if (outcome === 'stopped') {
                    log(`sim-recap: sim ${claim.sim} stopped by the posting switch; ending this sweep`);
                    return;
                }
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : 'unknown error';
            log(`sim-recap: sweep stopped: ${message}`);
        } finally {
            running = false;
        }
    }

    function start(): NodeJS.Timeout | undefined {
        if (!settings.enabled) {
            log('sim-recap publisher disabled');
            return undefined;
        }
        void kick();
        return setInterval(() => { void kick(); }, intervalMs);
    }

    return { kick, start };
}
