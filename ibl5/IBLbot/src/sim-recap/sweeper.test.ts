import { describe, it, expect, vi, afterEach } from 'vitest';
import { createSweeper } from './sweeper.js';
import type { EnabledSimRecapSettings, SimRecapSettings } from './settings.js';
import { createErrorReporter, type ErrorReporter, type FailureReport } from './error-reporter.js';
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
        postingEnabled: vi.fn(async () => true),
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
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext, enabled: async () => true }, depsFactory: () => deps, log: vi.fn() });

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
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext, enabled: async () => true }, depsFactory: () => deps, log });

        await sweeper.kick();

        expect(claimNext).toHaveBeenCalledTimes(1);
        expect(deps.finish).not.toHaveBeenCalled();

        const throwingClaim = vi.fn(async () => { throw new Error('http 500'); });
        const sweeper2 = createSweeper({ settings: ENABLED, api: { claimNext: throwingClaim, enabled: async () => true }, depsFactory: () => deps, log });
        await sweeper2.kick();
        expect(throwingClaim).toHaveBeenCalledTimes(1);
        expect(log).toHaveBeenCalledWith('sim-recap: sweep stopped: http 500');
    });

    it('does not claim when posting is switched off', async () => {
        const log = vi.fn();
        const claimNext = vi.fn(async () => emptyClaim(1));
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext, enabled: async () => false }, depsFactory: () => makeDeps(), log });

        await sweeper.kick();

        expect(claimNext).not.toHaveBeenCalled();
        expect(log).toHaveBeenCalledWith('sim-recap: posting is switched off in the League Control Panel; not claiming');
    });

    it('does not claim when the enabled read throws', async () => {
        const log = vi.fn();
        const claimNext = vi.fn(async () => emptyClaim(1));
        const enabled = vi.fn(async (): Promise<boolean> => { throw new Error('sim-recap api enabled failed: http 500'); });
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext, enabled }, depsFactory: () => makeDeps(), log });

        await sweeper.kick();

        expect(claimNext).not.toHaveBeenCalled();
        expect(log).toHaveBeenCalledWith('sim-recap: sweep stopped: sim-recap api enabled failed: http 500');
    });

    it('ends the sweep after a stopped outcome without re-claiming', async () => {
        const claim: PublishClaim = {
            sim: 9,
            threadId: null,
            lastStep: -1,
            script: {
                sim: 9,
                title: 'T',
                steps: [{ index: 0, kind: 'thread_start', channel: 'live_sims', content: 'x', mentionUsers: [], mentionRoles: [], delayBeforeMs: 0 }],
            },
        };
        const claimNext = vi.fn(async () => claim);
        const deps = makeDeps({ postingEnabled: vi.fn(async () => false) });
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext, enabled: async () => true }, depsFactory: () => deps, log: vi.fn() });

        await sweeper.kick();

        expect(claimNext).toHaveBeenCalledTimes(1);
        expect(deps.send).not.toHaveBeenCalled();
        expect(deps.finish).not.toHaveBeenCalled();
    });

    it('kick is single-flight', async () => {
        let release: (claim: PublishClaim | null) => void = () => undefined;
        const claimNext = vi.fn(() => new Promise<PublishClaim | null>(resolve => { release = resolve; }));
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext, enabled: async () => true }, depsFactory: () => makeDeps(), log: vi.fn() });

        const first = sweeper.kick();
        const second = sweeper.kick();
        await second;

        expect(claimNext).toHaveBeenCalledTimes(1);
        release(null);
        await first;

        const third = sweeper.kick();
        // The enabled() read runs before claimNext, so claimNext is reached a tick later.
        await vi.waitFor(() => expect(claimNext).toHaveBeenCalledTimes(2));
        release(null);
        await third;
    });

    it('start schedules nothing when disabled', async () => {
        vi.useFakeTimers();
        const log = vi.fn();
        const claimNext = vi.fn(async () => null);
        const sweeper = createSweeper({ settings: { enabled: false }, api: { claimNext, enabled: async () => true }, depsFactory: () => makeDeps(), log });

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
        const sweeper = createSweeper({ settings: ENABLED, api: { claimNext, enabled: async () => true }, depsFactory: () => makeDeps(), intervalMs: 300000, log: vi.fn() });

        const handle = sweeper.start();
        await vi.advanceTimersByTimeAsync(0);
        expect(claimNext).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(300000);
        expect(claimNext).toHaveBeenCalledTimes(2);
        if (handle) clearInterval(handle);
    });
});

describe('createSweeper failure alerts', () => {
    function mockReporter() {
        return { report: vi.fn(async (_failure: FailureReport) => undefined), resolve: vi.fn() };
    }

    function realReporter() {
        const fetchFn = vi.fn(async (..._args: Parameters<typeof fetch>) => new Response(null, { status: 204 }));
        const reporter = createErrorReporter({ webhookUrl: 'https://discord.com/api/webhooks/1/abc', fetchFn, log: vi.fn() });
        return { reporter, fetchFn };
    }

    const throwing = async (): Promise<PublishClaim | null> => { throw new Error('http 500'); };

    function build(
        claimNext: () => Promise<PublishClaim | null>,
        reporter: ErrorReporter,
        deps: PublisherDeps = makeDeps(),
        settings: SimRecapSettings = ENABLED,
        log: (line: string) => void = vi.fn(),
    ) {
        return createSweeper({ settings, api: { claimNext, enabled: async () => true }, depsFactory: () => deps, log, reporter });
    }

    it('alerts once on a sweep crash', async () => {
        const reporter = mockReporter();

        await build(throwing, reporter).kick();

        expect(reporter.report).toHaveBeenCalledTimes(1);
        const failure = reporter.report.mock.calls[0]?.[0];
        expect(failure?.kind).toBe('sweep-crash');
        expect(failure?.sim).toBeNull();
    });

    it('sends no second alert when the same crash repeats next sweep', async () => {
        const { reporter, fetchFn } = realReporter();
        const sweeper = build(throwing, reporter);

        await sweeper.kick();
        await sweeper.kick();

        expect(fetchFn).toHaveBeenCalledTimes(1);
    });

    it('alerts a step that gave up with its sim and step', async () => {
        const reporter = mockReporter();
        const log = vi.fn();
        const claim: PublishClaim = {
            sim: 7,
            threadId: 'TH',
            lastStep: -1,
            script: {
                sim: 7,
                title: 'T',
                steps: [{ index: 0, kind: 'thread_message', channel: 'thread', content: 'x', mentionUsers: [], mentionRoles: [], delayBeforeMs: 0 }],
            },
        };
        const deps = makeDeps({ send: vi.fn(async () => { throw new Error('Missing Access'); }) });

        await build(async () => claim, reporter, deps, ENABLED, log).kick();

        expect(reporter.report).toHaveBeenCalledTimes(1);
        const failure = reporter.report.mock.calls[0]?.[0];
        expect(failure?.kind).toBe('step-gave-up');
        expect(failure?.sim).toBe(7);
        expect(failure?.step).toEqual({ index: 0, kind: 'thread_message' });
        expect(log).toHaveBeenCalledWith('sim-recap: sweep stopped: Missing Access');
    });

    function stuckKinds(reporter: ReturnType<typeof mockReporter>): string[] {
        return reporter.report.mock.calls.map((call) => String(call[0].kind)).filter((kind) => kind === 'stuck');
    }

    async function kickStuck(times: number) {
        const reporter = mockReporter();
        const deps = makeDeps({ finish: vi.fn(async () => { throw new Error('http 500'); }) });
        const sweeper = build(async () => emptyClaim(9), reporter, deps);
        for (let i = 0; i < times; i++) await sweeper.kick();
        return reporter;
    }

    it('alerts a sim stuck at the same step for three sweeps', async () => {
        const reporter = await kickStuck(3);

        expect(stuckKinds(reporter)).toHaveLength(1);
        const stuck = reporter.report.mock.calls.map((call) => call[0]).find((f) => f.kind === 'stuck');
        expect(stuck?.sim).toBe(9);
    });

    it('does not alert stuck after only two sweeps', async () => {
        const reporter = await kickStuck(2);

        expect(stuckKinds(reporter)).toHaveLength(0);
    });

    it('a failing reporter never stops publishing', async () => {
        const reporter = { report: vi.fn(async () => { throw new Error('webhook down'); }), resolve: vi.fn() };
        const deps = makeDeps();

        await expect(build(throwing, reporter, deps).kick()).resolves.toBeUndefined();

        const claims: Array<PublishClaim | null> = [emptyClaim(5), null];
        await build(async () => claims.shift() ?? null, reporter, deps).kick();

        expect(deps.finish).toHaveBeenCalledWith(5);
    });

    it('sends no alert when the publisher is disabled', async () => {
        const reporter = mockReporter();

        await build(throwing, reporter, makeDeps(), { enabled: false }).kick();

        expect(reporter.report).not.toHaveBeenCalled();
    });

    it('a clean sweep re-arms the sweep-crash alert', async () => {
        const { reporter, fetchFn } = realReporter();
        let mode: 'crash' | 'clean' = 'crash';
        const sweeper = build(async () => {
            if (mode === 'crash') throw new Error('http 500');
            return null;
        }, reporter);

        await sweeper.kick();
        mode = 'clean';
        await sweeper.kick();
        mode = 'crash';
        await sweeper.kick();

        expect(fetchFn).toHaveBeenCalledTimes(2);
    });
});
