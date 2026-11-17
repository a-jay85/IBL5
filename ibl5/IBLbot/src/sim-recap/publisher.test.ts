import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';
import { runPublish, type AllowedMentions, type PublishClaim, type PublisherDeps, type PublishTarget } from './publisher.js';

const fixture = JSON.parse(
    fs.readFileSync(path.join(__dirname, 'fixtures', 'two-day-script.json'), 'utf8'),
) as PublishClaim['script'];

const LIVE_SIMS = 'LIVE_SIMS_CH';
const INJURIES = 'INJURIES_CH';
const THREAD = 'THREAD_1';
const PROD_TARGET: PublishTarget = { liveSimsChannelId: LIVE_SIMS, injuriesChannelId: INJURIES, stripMentions: false };
const TESTING_TARGET: PublishTarget = { liveSimsChannelId: 'TEST_CH', injuriesChannelId: 'TEST_CH', stripMentions: true };

interface Sent { channelId: string; content: string; allowed: AllowedMentions }

interface Fakes {
    deps: PublisherDeps;
    sent: Sent[];
    sleeps: number[];
    logs: string[];
    threads: Array<{ channelId: string; messageId: string; name: string }>;
    progressCalls: Array<{ sim: number; step: number; threadId: string | null }>;
    finishCalls: number[];
}

function makeFakes(overrides: Partial<PublisherDeps> = {}): Fakes {
    const sent: Sent[] = [];
    const sleeps: number[] = [];
    const logs: string[] = [];
    const threads: Fakes['threads'] = [];
    const progressCalls: Fakes['progressCalls'] = [];
    const finishCalls: number[] = [];
    let nextId = 1;
    const deps: PublisherDeps = {
        async send(channelId, content, allowed) {
            sent.push({ channelId, content, allowed });
            return { id: `M${nextId++}` };
        },
        async startThread(channelId, messageId, name) {
            threads.push({ channelId, messageId, name });
            return { id: THREAD };
        },
        async lastOwnMessage() {
            return null;
        },
        async searchGif() {
            return 'https://giphy.com/gifs/abc';
        },
        async progress(sim, step, threadId) {
            progressCalls.push({ sim, step, threadId });
            return true;
        },
        async finish(sim) {
            finishCalls.push(sim);
            return true;
        },
        async postingEnabled() {
            return true;
        },
        async sleep(ms) {
            sleeps.push(ms);
        },
        log(line) {
            logs.push(line);
        },
        ...overrides,
    };
    return { deps, sent, sleeps, logs, threads, progressCalls, finishCalls };
}

function claim(lastStep = -1, threadId: string | null = null): PublishClaim {
    return { sim: fixture.sim, threadId, lastStep, script: fixture };
}

function channelFor(step: PublishClaim['script']['steps'][number]): string {
    if (step.channel === 'live_sims') return LIVE_SIMS;
    if (step.channel === 'injuries') return INJURIES;
    return THREAD;
}

describe('runPublish', () => {
    it('posts every step in fixture order for a two-day sim', async () => {
        const f = makeFakes();

        const result = await runPublish(claim(), PROD_TARGET, f.deps);

        expect(result).toBe('finished');
        const expected = fixture.steps.map(step => ({
            channelId: channelFor(step),
            content: step.kind === 'gif' ? 'https://giphy.com/gifs/abc' : step.content,
        }));
        expect(f.sent.map(s => ({ channelId: s.channelId, content: s.content }))).toEqual(expected);
        expect(f.threads).toHaveLength(1);
        expect(f.threads[0]).toEqual({ channelId: LIVE_SIMS, messageId: 'M1', name: fixture.title });
        const injuriesStep = fixture.steps.find(step => step.kind === 'injuries');
        expect(injuriesStep).toBeDefined();
        expect(f.sent.find(s => s.content === injuriesStep?.content)?.channelId).toBe(INJURIES);
        expect(f.finishCalls).toEqual([fixture.sim]);
    });

    it('sets allowedMentions with parse [] on every message', async () => {
        const f = makeFakes();

        await runPublish(claim(), PROD_TARGET, f.deps);

        expect(f.sent).toHaveLength(fixture.steps.length);
        for (const sent of f.sent) {
            expect(sent.allowed.parse).toEqual([]);
            expect(sent.allowed.repliedUser).toBe(false);
        }
        const withRoles = f.sent.filter(s => s.allowed.roles.length > 0);
        expect(withRoles).toHaveLength(1);
        expect(withRoles[0]?.allowed.roles).toEqual(['793564717402554368']);
        fixture.steps.forEach((step, i) => {
            if (step.kind === 'gif') return;
            expect(f.sent[i]?.allowed.users).toEqual(step.mentionUsers);
        });
    });

    it('strips all mentions on the testing target', async () => {
        const f = makeFakes();

        await runPublish(claim(), TESTING_TARGET, f.deps);

        expect(f.sent).toHaveLength(fixture.steps.length);
        for (const sent of f.sent) {
            expect(sent.allowed.users).toEqual([]);
            expect(sent.allowed.roles).toEqual([]);
            expect(sent.allowed.parse).toEqual([]);
        }
        expect(f.sent.every(s => s.channelId === 'TEST_CH' || s.channelId === THREAD)).toBe(true);
    });

    it('resume from a saved step posts nothing twice', async () => {
        const run1 = makeFakes();
        run1.deps.progress = async (sim, step, threadId) => {
            run1.progressCalls.push({ sim, step, threadId });
            if (step === 4) throw new Error('process died after step 4 was sent');
            return true;
        };

        await expect(runPublish(claim(), PROD_TARGET, run1.deps)).rejects.toThrow('process died');

        const step4 = fixture.steps[4];
        expect(step4).toBeDefined();
        const run2 = makeFakes({
            async lastOwnMessage() {
                return { id: 'M-prev', content: step4?.content ?? '', threadId: null };
            },
        });

        const result = await runPublish(claim(3, THREAD), PROD_TARGET, run2.deps);

        expect(result).toBe('finished');
        const contents = [...run1.sent, ...run2.sent].map(s => s.content);
        const expected = fixture.steps.map(step => (step.kind === 'gif' ? 'https://giphy.com/gifs/abc' : step.content));
        expect(contents).toEqual(expected);
        expect(run2.progressCalls[0]?.step).toBe(4);
    });

    it('stops without finishing when progress is refused', async () => {
        const f = makeFakes({
            async progress(_sim, step) {
                return step < 3;
            },
        });

        const result = await runPublish(claim(), PROD_TARGET, f.deps);

        expect(result).toBe('lost-claim');
        expect(f.finishCalls).toEqual([]);
        expect(f.sent).toHaveLength(4);
        expect(f.logs).toContain('sim-recap: claim lost at step 3');
    });

    it('retries a 429 after retry_after', async () => {
        let calls = 0;
        const f = makeFakes();
        const sentOk = f.sent;
        f.deps.send = async (channelId, content, allowed) => {
            calls++;
            if (calls === 1) {
                throw { status: 429, retryAfter: 1500 };
            }
            sentOk.push({ channelId, content, allowed });
            return { id: 'M1' };
        };

        await runPublish(claim(), PROD_TARGET, f.deps);

        expect(f.sleeps).toContain(1500);
        expect(sentOk.filter(s => s.content === fixture.steps[0]?.content)).toHaveLength(1);
        expect(calls).toBe(fixture.steps.length + 1);
    });

    it('GIPHY failure skips the GIF and still marks the sim finished', async () => {
        const f = makeFakes({
            async searchGif() {
                return null;
            },
        });

        const result = await runPublish(claim(), PROD_TARGET, f.deps);

        expect(result).toBe('finished');
        const gifStep = fixture.steps.find(step => step.kind === 'gif');
        expect(gifStep).toBeDefined();
        expect(f.sent).toHaveLength(fixture.steps.length - 1);
        expect(f.progressCalls.map(p => p.step)).toContain(gifStep?.index);
        expect(f.finishCalls).toEqual([fixture.sim]);
        expect(f.logs).toContain('sim-recap: gif skipped');
    });

    it('stops before the next step when posting is switched off', async () => {
        const answers = [true, false];
        const f = makeFakes({ postingEnabled: async () => answers.shift() ?? false });
        const script = { sim: 5, title: 'T', steps: fixture.steps.slice(0, 3) };

        const result = await runPublish({ sim: 5, threadId: null, lastStep: -1, script }, PROD_TARGET, f.deps);

        expect(result).toBe('stopped');
        expect(f.sent).toHaveLength(1);
        expect(f.progressCalls).toHaveLength(1);
        expect(f.progressCalls[0]?.step).toBe(script.steps[0]?.index);
        expect(f.finishCalls).toEqual([]);
    });

    it('posts nothing when the enabled read throws', async () => {
        const f = makeFakes({ postingEnabled: async () => { throw new Error('http 500'); } });

        const result = await runPublish(claim(), PROD_TARGET, f.deps);

        expect(result).toBe('stopped');
        expect(f.sent).toHaveLength(0);
        expect(f.progressCalls).toHaveLength(0);
        expect(f.finishCalls).toEqual([]);
    });

    it('posts every step while posting stays on', async () => {
        const f = makeFakes({ postingEnabled: async () => true });

        const result = await runPublish(claim(), PROD_TARGET, f.deps);

        expect(result).toBe('finished');
        expect(f.sent).toHaveLength(fixture.steps.length);
        expect(f.progressCalls).toHaveLength(fixture.steps.length);
        expect(f.finishCalls).toEqual([fixture.sim]);
    });
});
