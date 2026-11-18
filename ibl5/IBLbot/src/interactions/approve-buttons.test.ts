import { describe, it, expect, beforeEach, vi } from 'vitest';
import { MessageFlags } from 'discord.js';

const h = vi.hoisted(() => ({ state: { owner: 'OWNER_SNOWFLAKE' } }));

vi.mock('../config.js', () => ({
    config: {
        planReview: {
            get ownerDiscordId() { return h.state.owner; },
        },
    },
}));

vi.mock('../server/approve-click-store.js', async (importOriginal) => {
    const real = await importOriginal<typeof import('../server/approve-click-store.js')>();
    return {
        ...real,
        lookupLive: vi.fn(),
        recordClick: vi.fn(),
    };
});

import { CONDITION_MAX, handleApproveButton, handleApproveModal } from './approve-buttons.js';
import { ClickRefusedError, lookupLive, recordClick } from '../server/approve-click-store.js';

const NONCE = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
const lookupMock = vi.mocked(lookupLive);
const recordMock = vi.mocked(recordClick);

function makeButton(customId: string, userId = 'OWNER_SNOWFLAKE') {
    return {
        customId,
        user: { id: userId },
        reply: vi.fn(async () => undefined),
        update: vi.fn(async () => undefined),
        showModal: vi.fn(async () => undefined),
        deferUpdate: vi.fn(async () => undefined),
    };
}

function makeModal(customId: string, condition: string, userId = 'OWNER_SNOWFLAKE') {
    return {
        customId,
        user: { id: userId },
        fields: { getTextInputValue: vi.fn(() => condition) },
        reply: vi.fn(async () => undefined),
    };
}

beforeEach(() => {
    h.state.owner = 'OWNER_SNOWFLAKE';
    lookupMock.mockReset();
    recordMock.mockReset();
});

describe('handleApproveButton', () => {
    it('refuses a non-owner press with no update, no showModal and no store write', async () => {
        for (const id of [`approve_ok_${NONCE}`, `approve_fix_${NONCE}`]) {
            const i = makeButton(id, 'INTRUDER');
            await handleApproveButton(i as never);
            expect(i.reply).toHaveBeenCalledTimes(1);
            expect(i.reply.mock.calls[0]![0]).toMatchObject({ flags: MessageFlags.Ephemeral });
            expect(i.update).not.toHaveBeenCalled();
            expect(i.showModal).not.toHaveBeenCalled();
            expect(i.deferUpdate).not.toHaveBeenCalled();
        }
        expect(lookupMock).not.toHaveBeenCalled();
        expect(recordMock).not.toHaveBeenCalled();
    });

    it('refuses a non-owner press when the owner id is empty', async () => {
        h.state.owner = '';
        const i = makeButton(`approve_ok_${NONCE}`, '');
        await handleApproveButton(i as never);
        expect(i.reply).toHaveBeenCalledTimes(1);
        expect(recordMock).not.toHaveBeenCalled();
    });

    it('records a valid Approve press once and refuses the second press', async () => {
        const first = makeButton(`approve_ok_${NONCE}`);
        await handleApproveButton(first as never);
        expect(recordMock).toHaveBeenCalledTimes(1);
        expect(recordMock.mock.calls[0]!.slice(0, 4)).toEqual([NONCE, 'ok', 'OWNER_SNOWFLAKE', null]);
        expect(first.update).toHaveBeenCalledTimes(1);
        const payload = first.update.mock.calls[0]![0] as unknown as {
            content: string;
            components: { components: { data: { disabled?: boolean } }[] }[];
        };
        expect(payload.content).toContain('Approve recorded');
        expect(payload.components[0]!.components.every((c) => c.data.disabled === true)).toBe(true);

        lookupMock.mockImplementation(() => { throw new ClickRefusedError('used'); });
        const second = makeButton(`approve_ok_${NONCE}`);
        await handleApproveButton(second as never);
        expect(recordMock).toHaveBeenCalledTimes(1);
        expect(second.update).not.toHaveBeenCalled();
        expect(second.reply).toHaveBeenCalledTimes(1);
        expect(JSON.stringify(second.reply.mock.calls[0]![0])).toContain('bin/owner-approve');
    });

    it('Fix first shows the modal before any defer and writes nothing', async () => {
        const i = makeButton(`approve_fix_${NONCE}`);
        await handleApproveButton(i as never);
        expect(i.showModal).toHaveBeenCalledTimes(1);
        expect(i.deferUpdate).not.toHaveBeenCalled();
        expect(i.update).not.toHaveBeenCalled();
        expect(i.reply).not.toHaveBeenCalled();
        expect(recordMock).not.toHaveBeenCalled();
        const modal = i.showModal.mock.calls[0]![0] as unknown as { data: { custom_id: string } };
        expect(modal.data.custom_id).toBe(`approve_cond_${NONCE}`);
    });

    it('ignores near-miss custom ids with no store call', async () => {
        const nearMisses = [
            `approve_ok_${NONCE.slice(0, 31)}`,
            `approve_okx_${NONCE}`,
            `approve_fix_${NONCE.toUpperCase()}`,
            'plan_queue_x',
        ];
        for (const id of nearMisses) {
            const i = makeButton(id);
            await handleApproveButton(i as never);
            expect(i.update).not.toHaveBeenCalled();
            expect(i.showModal).not.toHaveBeenCalled();
        }
        expect(lookupMock).not.toHaveBeenCalled();
        expect(recordMock).not.toHaveBeenCalled();
    });

    it('replies with the reason when the token is expired', async () => {
        lookupMock.mockImplementation(() => { throw new ClickRefusedError('expired'); });
        const i = makeButton(`approve_fix_${NONCE}`);
        await handleApproveButton(i as never);
        expect(i.showModal).not.toHaveBeenCalled();
        expect(JSON.stringify(i.reply.mock.calls[0]![0])).toContain('expired');
    });
});

describe('handleApproveModal', () => {
    const modalId = `approve_cond_${NONCE}`;

    it('refuses a non-owner modal submit and records nothing', async () => {
        const i = makeModal(modalId, 'rebase first', 'INTRUDER');
        await handleApproveModal(i as never);
        expect(i.reply).toHaveBeenCalledTimes(1);
        expect(i.fields.getTextInputValue).not.toHaveBeenCalled();
        expect(lookupMock).not.toHaveBeenCalled();
        expect(recordMock).not.toHaveBeenCalled();
    });

    it('records a valid condition as a fix click', async () => {
        const i = makeModal(modalId, '  rebase first  ');
        await handleApproveModal(i as never);
        expect(recordMock).toHaveBeenCalledTimes(1);
        expect(recordMock.mock.calls[0]!.slice(0, 4)).toEqual([NONCE, 'fix', 'OWNER_SNOWFLAKE', 'rebase first']);
        expect(JSON.stringify(i.reply.mock.calls[0]![0])).toContain('Fix first recorded');
    });

    it('caps the condition at 300', async () => {
        expect(CONDITION_MAX).toBe(300);
        const over = makeModal(modalId, 'x'.repeat(301));
        await handleApproveModal(over as never);
        expect(recordMock).not.toHaveBeenCalled();
        expect(over.reply).toHaveBeenCalledTimes(1);

        const exact = makeModal(modalId, 'x'.repeat(300));
        await handleApproveModal(exact as never);
        expect(recordMock).toHaveBeenCalledTimes(1);
        expect(recordMock.mock.calls[0]![3]).toBe('x'.repeat(300));
    });

    it('refuses control characters', async () => {
        for (const bad of ['line one\nline two', 'bell\u0007here', 'tab\there', 'del\u007fhere', 'c1\u0085here', 'c1\u009fhere']) {
            const i = makeModal(modalId, bad);
            await handleApproveModal(i as never);
            expect(i.reply).toHaveBeenCalledTimes(1);
        }
        expect(recordMock).not.toHaveBeenCalled();
    });

    it('refuses an empty condition', async () => {
        const i = makeModal(modalId, '   ');
        await handleApproveModal(i as never);
        expect(recordMock).not.toHaveBeenCalled();
    });

    it('refuses when the token expired while the modal was open', async () => {
        lookupMock.mockImplementation(() => { throw new ClickRefusedError('expired'); });
        const i = makeModal(modalId, 'rebase first');
        await handleApproveModal(i as never);
        expect(recordMock).not.toHaveBeenCalled();
        expect(JSON.stringify(i.reply.mock.calls[0]![0])).toContain('expired');
    });

    it('ignores a modal id that does not match', async () => {
        const i = makeModal(`approve_cond_${NONCE.slice(0, 31)}`, 'x');
        await handleApproveModal(i as never);
        expect(lookupMock).not.toHaveBeenCalled();
        expect(recordMock).not.toHaveBeenCalled();
    });
});
