import { describe, it, expect, vi, afterEach } from 'vitest';
import { createSweeper } from './sweeper.js';
import type { EnabledSimRecapSettings } from './settings.js';
import type { PublishClaim, PublisherDeps } from './publisher.js';

const ENABLED: EnabledSimRecapSettings = {
    enabled: true,
    target: 'testing',
    guildId: 'G',
    liveSimsChannelId: 'C',
    injuriesChannelId: 'C',
    stripMentions: true,
    apiBaseUrl: 'https://example.test',
    apiKey: 'k',
    giphyKey: '',
};

function emptyClaim(sim: number): PublishClaim {
    return { sim, threadId: null, lastStep: -1, script: { sim, title: 'T', steps: [] } };
}

function makeDeps(overrides: Partial<PublisherDeps> = {}): PublisherDeps {
    return {
        send: vi.fn(async () => ({ id: 'M' })),
        startThread: vi.fn(async () => ({ id: 'T' })),
        lastOwnMessage: vi.fn(async () => null),
        searchGif: vi.fn(async () => null),
        progress: vi.fn(async () => true),
        finish: vi.fn(async () => true),
        sleep: vi.fn(async () => undefined),
        log: vi.fn(),
        ...overrides,
    };
}

afterEach(() => {
    vi.useRealTimers();
});

describe('createSweeper', () => {
    it('sweeper keeps claiming until claimNext returns null', async () => {
        const claims: Array<PublishClaim | null> = [emptyClaim(1), emptyClaim(2), null];
        const claimNext = vi.fn(async () => claims.shift() ?? null);
        const deps = makeDeps();
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext }, depsFactory: () => deps, log: vi.fn() });

        await sweeper.kick();

        expect(claimNext).toHaveBeenCalledTimes(3);
        expect(deps.finish).toHaveBeenCalledTimes(2);
        const tokens = claimNext.mock.calls.map(call => (call as unknown[])[0]);
        expect(new Set(tokens).size).toBe(1);
    });

    it('stops this kick when a claim is lost or an error is thrown', async () => {
        const log = vi.fn();
        const lostClaim: PublishClaim = {
            sim: 7,
            threadId: null,
            lastStep: -1,
            script: {
                sim: 7,
                title: 'T',
                steps: [{ index: 0, kind: 'thread_start', channel: 'live_sims', content: 'x', mentionUsers: [], mentionRoles: [], delayBeforeMs: 0 }],
            },
        };
        const claimNext = vi.fn(async () => lostClaim);
        const deps = makeDeps({ progress: vi.fn(async () => false) });
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext }, depsFactory: () => deps, log });

        await sweeper.kick();

        expect(claimNext).toHaveBeenCalledTimes(1);
        expect(deps.finish).not.toHaveBeenCalled();

        const throwingClaim = vi.fn(async () => { throw new Error('http 500'); });
        const sweeper2 = createSweeper({ settings: ENABLED, api: { claimNext: throwingClaim }, depsFactory: () => deps, log });
        await sweeper2.kick();
        expect(throwingClaim).toHaveBeenCalledTimes(1);
        expect(log).toHaveBeenCalledWith('sim-recap: sweep stopped: http 500');
    });

    it('kick is single-flight', async () => {
        let release: (claim: PublishClaim | null) => void = () => undefined;
        const claimNext = vi.fn(() => new Promise<PublishClaim | null>(resolve => { release = resolve; }));
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext }, depsFactory: () => makeDeps(), log: vi.fn() });

        const first = sweeper.kick();
        const second = sweeper.kick();
        await second;

        expect(claimNext).toHaveBeenCalledTimes(1);
        release(null);
        await first;

        const third = sweeper.kick();
        expect(claimNext).toHaveBeenCalledTimes(2);
        release(null);
        await third;
    });

    it('start schedules nothing when disabled', async () => {
        vi.useFakeTimers();
        const log = vi.fn();
        const claimNext = vi.fn(async () => null);
        const sweeper = createSweeper({ settings: { enabled: false }, api: { claimNext }, depsFactory: () => makeDeps(), log });

        const handle = sweeper.start();
        await vi.advanceTimersByTimeAsync(600000);

        expect(handle).toBeUndefined();
        expect(claimNext).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
        expect(log).toHaveBeenCalledWith('sim-recap publisher disabled');
    });

    it('start kicks once and then on the interval when enabled', async () => {
        vi.useFakeTimers();
        const claimNext = vi.fn(async () => null);
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext }, depsFactory: () => makeDeps(), intervalMs: 300000, log: vi.fn() });

        const handle = sweeper.start();
        await vi.advanceTimersByTimeAsync(0);
        expect(claimNext).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(300000);
        expect(claimNext).toHaveBeenCalledTimes(2);
        if (handle) clearInterval(handle);
    });
});
