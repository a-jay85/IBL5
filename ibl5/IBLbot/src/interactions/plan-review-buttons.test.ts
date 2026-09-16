import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { MessageFlags } from 'discord.js';
import fs from 'fs';
import os from 'os';
import path from 'path';

vi.mock('../config.js', () => {
    const state = { owner: 'OWNER_SNOWFLAKE' };
    return {
        config: {
            planReview: {
                get ownerDiscordId() { return state.owner; },
            },
        },
        // Expose state so tests can mutate it
        __state: state,
    };
});

// File-wide mock so appendDecision can be made to throw in property 7
// while readPending/hasDecision stay real.
vi.mock('../server/decision-store.js', async (importOriginal) => {
    const real = await importOriginal<typeof import('../server/decision-store.js')>();
    return {
        ...real,
        appendDecision: vi.fn(real.appendDecision),
    };
});

import { handlePlanReviewButton } from './plan-review-buttons.js';
import { readPending } from '../server/decision-store.js';
import { appendDecision } from '../server/decision-store.js';

type FakeInteraction = {
    customId: string;
    user: { id: string };
    reply: ReturnType<typeof vi.fn>;
    deferUpdate: ReturnType<typeof vi.fn>;
    editReply: ReturnType<typeof vi.fn>;
};

function makeInteraction(customId: string, userId = 'OWNER_SNOWFLAKE'): FakeInteraction {
    return {
        customId,
        user: { id: userId },
        reply: vi.fn(async () => undefined),
        deferUpdate: vi.fn(async () => undefined),
        editReply: vi.fn(async () => undefined),
    };
}

let tmp: string;

beforeEach(() => {
    tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'buttons-'));
});

afterEach(() => {
    fs.rmSync(tmp, { recursive: true, force: true });
});

describe('handlePlanReviewButton', () => {
    it('1. Non-owner press is rejected before any defer (security ordering)', async () => {
        // Security ordering assertion: reply (ephemeral rejection) fires BEFORE
        // deferUpdate. If deferUpdate fired first, a non-owner could neutralise
        // the approval path by binding the interaction token and rewriting the
        // owner's message — see plan-review-buttons.ts comment at line ~40.
        const interaction = makeInteraction('plan_queue_some-plan', 'INTRUDER');
        await handlePlanReviewButton(interaction as never, tmp);

        expect(interaction.reply).toHaveBeenCalledTimes(1);
        expect(interaction.reply).toHaveBeenCalledWith({
            content: 'Not your plan review.',
            flags: MessageFlags.Ephemeral,
        });
        expect(interaction.deferUpdate).not.toHaveBeenCalled();
        expect(interaction.editReply).not.toHaveBeenCalled();
        expect(readPending(tmp)).toEqual([]);
    });

    it('2. Owner Queue press records and confirms', async () => {
        const interaction = makeInteraction('plan_queue_some-plan');
        await handlePlanReviewButton(interaction as never, tmp);

        expect(interaction.deferUpdate).toHaveBeenCalledTimes(1);

        const pending = readPending(tmp);
        expect(pending).toHaveLength(1);
        expect(pending[0]).toMatchObject({
            slug: 'some-plan',
            action: 'queue',
            actor: 'OWNER_SNOWFLAKE',
        });

        // 'queued — pending pickup' is frozen by the shared context (OUTCOME.queue
        // in plan-review-buttons.ts) — this exact string must not be paraphrased.
        expect(interaction.editReply).toHaveBeenCalledWith(
            expect.objectContaining({ content: 'queued — pending pickup' }),
        );
    });

    it('3. Owner Discard press records discard and replies discarded', async () => {
        const interaction = makeInteraction('plan_discard_some-plan');
        await handlePlanReviewButton(interaction as never, tmp);

        expect(interaction.deferUpdate).toHaveBeenCalledTimes(1);

        const pending = readPending(tmp);
        expect(pending).toHaveLength(1);
        expect(pending[0]).toMatchObject({
            slug: 'some-plan',
            action: 'discard',
            actor: 'OWNER_SNOWFLAKE',
        });

        expect(interaction.editReply).toHaveBeenCalledWith(
            expect.objectContaining({ content: 'discarded' }),
        );
    });

    it('4. Disabled row on success — both buttons disabled, custom_ids preserved', async () => {
        const slug = 'some-plan';
        const interaction = makeInteraction(`plan_queue_${slug}`);
        await handlePlanReviewButton(interaction as never, tmp);

        expect(interaction.editReply).toHaveBeenCalledTimes(1);
        const payload = interaction.editReply.mock.calls[0]![0] as {
            components: { components: { data: { custom_id?: string; disabled?: boolean } }[] }[];
        };

        const buttons = payload.components[0]!.components;
        expect(buttons).toHaveLength(2);

        // Both buttons disabled, row not removed
        for (const btn of buttons) {
            expect(btn.data.disabled).toBe(true);
        }

        // custom_ids still present and correct
        expect(buttons[0]!.data.custom_id).toBe(`plan_queue_${slug}`);
        expect(buttons[1]!.data.custom_id).toBe(`plan_discard_${slug}`);
    });

    it('5. Double press is idempotent', async () => {
        const slug = 'some-plan';

        // First press — use fresh interaction object
        const first = makeInteraction(`plan_queue_${slug}`);
        await handlePlanReviewButton(first as never, tmp);

        expect(first.deferUpdate).toHaveBeenCalledTimes(1);
        expect(readPending(tmp)).toHaveLength(1);

        // Second press — separate interaction object so spies are fresh
        const second = makeInteraction(`plan_queue_${slug}`);
        await handlePlanReviewButton(second as never, tmp);

        // Second press sees the existing record and replies ephemerally
        expect(second.reply).toHaveBeenCalledWith(
            expect.objectContaining({ content: 'already queued', flags: MessageFlags.Ephemeral }),
        );
        // deferUpdate NOT called on the second invocation
        expect(second.deferUpdate).not.toHaveBeenCalled();

        // Still only one record
        expect(readPending(tmp)).toHaveLength(1);
    });

    it('6. Malformed custom_id is ignored silently', async () => {
        for (const customId of [
            'plan_bogus_x',
            'plan_queue_Bad_Slug!',
            'plan_queue_',
            'trade_accept_1',
        ]) {
            const interaction = makeInteraction(customId);
            await handlePlanReviewButton(interaction as never, tmp);

            expect(interaction.reply, customId).not.toHaveBeenCalled();
            expect(interaction.deferUpdate, customId).not.toHaveBeenCalled();
            expect(interaction.editReply, customId).not.toHaveBeenCalled();
        }

        expect(readPending(tmp)).toHaveLength(0);
    });

    it('7. Store write failure keeps the buttons pressable', async () => {
        // Make appendDecision throw once — deterministic vs. dir-permission trick
        vi.mocked(appendDecision).mockImplementationOnce(() => {
            throw new Error('disk full');
        });

        const interaction = makeInteraction('plan_queue_retry-plan');
        await handlePlanReviewButton(interaction as never, tmp);

        // editReply is called with a retry message
        expect(interaction.editReply).toHaveBeenCalledTimes(1);
        const payload = interaction.editReply.mock.calls[0]![0] as {
            content: string;
            components: { components: { data: { custom_id?: string; disabled?: boolean } }[] }[];
        };
        expect(payload.content).toBe('Could not record that decision — please press again.');

        // Both buttons NOT disabled — owner can retry
        const buttons = payload.components[0]!.components;
        for (const btn of buttons) {
            expect(btn.data.disabled).not.toBe(true);
        }
    });
});
