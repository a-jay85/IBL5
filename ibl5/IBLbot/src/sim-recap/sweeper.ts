import crypto from 'crypto';
import type { SimRecapSettings } from './settings.js';
import type { SimRecapApi } from './api.js';
import { runPublish, StepGaveUpError, type PublishClaim, type PublisherDeps, type PublishTarget } from './publisher.js';
import type { ErrorReporter, FailureReport } from './error-reporter.js';

export interface SweeperOptions {
    settings: SimRecapSettings;
    api: Pick<SimRecapApi, 'claimNext' | 'enabled'>;
    /** Builds the Discord/API-backed deps for one claim token. */
    depsFactory: (token: string) => PublisherDeps;
    intervalMs?: number;
    log?: (line: string) => void;
    /** Optional #errors alerts. Absent: failures are only logged, as before. */
    reporter?: ErrorReporter;
}

export interface Sweeper {
    kick(): Promise<void>;
    start(): NodeJS.Timeout | undefined;
}

export const SWEEP_INTERVAL_MS = 300000;
export const STUCK_CLAIM_COUNT = 3;

export function createSweeper(options: SweeperOptions): Sweeper {
    const { settings, api, depsFactory } = options;
    const intervalMs = options.intervalMs ?? SWEEP_INTERVAL_MS;
    const log = options.log ?? ((line: string) => console.log(line));
    // One token per process: a restarted bot gets a new token, so the old claim's heartbeat
    // goes stale and the row is reclaimed at last_step + 1.
    const token = crypto.randomUUID();
    let running = false;
    let kickCount = 0;
    const claimsSeen = new Map<number, { lastStep: number; seen: number; kick: number }>();

    async function safeReport(failure: FailureReport): Promise<void> {
        if (options.reporter === undefined) return;
        try {
            await options.reporter.report(failure);
        } catch {
            log('sim-recap: alert reporter failed');
        }
    }

    function sweepEndedCleanly(): void {
        options.reporter?.resolve('sweep-crash', null);
    }

    // A sim claimed at the start of STUCK_CLAIM_COUNT separate kicks with no lastStep
    // progress is stuck. Counting once per kick keeps a long drip in one kick from counting.
    async function noteClaim(claim: PublishClaim): Promise<void> {
        const entry = claimsSeen.get(claim.sim);
        if (entry === undefined || entry.lastStep !== claim.lastStep) {
            claimsSeen.set(claim.sim, { lastStep: claim.lastStep, seen: 1, kick: kickCount });
            return;
        }
        if (entry.kick === kickCount) return;
        entry.seen += 1;
        entry.kick = kickCount;
        if (entry.seen >= STUCK_CLAIM_COUNT) {
            await safeReport({ kind: 'stuck', sim: claim.sim, step: null, lastStep: claim.lastStep });
        }
    }

    async function kick(): Promise<void> {
        if (!settings.enabled || running) return;
        running = true;
        kickCount += 1;
        let currentSim: number | null = null;
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
                if (claim === null) {
                    sweepEndedCleanly();
                    return;
                }
                currentSim = claim.sim;
                await noteClaim(claim);
                const outcome = await runPublish(claim, target, deps);
                if (outcome === 'lost-claim') {
                    log(`sim-recap: sim ${claim.sim} claim lost; stopping this sweep`);
                    sweepEndedCleanly();
                    return;
                }
                if (outcome === 'stopped') {
                    log(`sim-recap: sim ${claim.sim} stopped by the posting switch; ending this sweep`);
                    sweepEndedCleanly();
                    return;
                }
                claimsSeen.delete(claim.sim);
                currentSim = null;
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : 'unknown error';
            log(`sim-recap: sweep stopped: ${message}`);
            if (error instanceof StepGaveUpError) {
                await safeReport({ kind: 'step-gave-up', sim: error.sim, step: { index: error.stepIndex, kind: error.stepKind }, error: error.original });
            } else {
                await safeReport({ kind: 'sweep-crash', sim: currentSim, step: null, error });
            }
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
