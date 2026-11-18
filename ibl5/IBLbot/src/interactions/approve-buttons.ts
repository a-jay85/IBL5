import {
    ActionRowBuilder,
    MessageFlags,
    ModalBuilder,
    TextInputBuilder,
    TextInputStyle,
} from 'discord.js';
import type { ButtonInteraction, ModalSubmitInteraction } from 'discord.js';
import { config } from '../config.js';
import { buildApproveRow } from '../server/approve-dm.js';
import { ClickRefusedError, lookupLive, recordClick } from '../server/approve-click-store.js';

const BUTTON_ID_RE = /^approve_(ok|fix)_([0-9a-f]{32})$/;
const MODAL_ID_RE = /^approve_cond_([0-9a-f]{32})$/;
export const CONDITION_MAX = 300;
const CONTROL_RE = /[\u0000-\u001f\u007f-\u009f]/;

const REFUSAL_TAIL = 'Run bin/owner-approve on the Mac, or wait for the next held notice.';

const REASON_TEXT: Record<string, string> = {
    expired: 'This button expired.',
    used: 'This button was already used.',
    superseded: 'A newer button replaced this one.',
    unknown: 'This button is not known.',
};

function isOwner(userId: string): boolean {
    const owner = config.planReview.ownerDiscordId;
    return owner !== '' && userId === owner;
}

function refusalText(error: ClickRefusedError): string {
    return `${REASON_TEXT[error.reason] ?? 'This button cannot be used.'} ${REFUSAL_TAIL}`;
}

export function buildConditionModal(nonce: string): ModalBuilder {
    const input = new TextInputBuilder()
        .setCustomId('condition')
        .setLabel('Condition to meet before approval')
        .setStyle(TextInputStyle.Short)
        .setRequired(true)
        .setMinLength(1)
        .setMaxLength(CONDITION_MAX);
    return new ModalBuilder()
        .setCustomId(`approve_cond_${nonce}`)
        .setTitle('Fix first')
        .addComponents(new ActionRowBuilder<TextInputBuilder>().addComponents(input));
}

/**
 * Approve and Fix-first button presses. The owner compare runs before any
 * interaction response: showModal() cannot follow deferUpdate(), and a deferral
 * by a non-owner would bind the token to them. Fix first changes no state, so a
 * dismissed modal leaves the token live.
 */
export async function handleApproveButton(interaction: ButtonInteraction): Promise<void> {
    const match = interaction.customId.match(BUTTON_ID_RE);
    if (!match) {
        await interaction.reply({ content: 'Button not recognized.', flags: MessageFlags.Ephemeral });
        return;
    }
    const kind = match[1] as 'ok' | 'fix';
    const nonce = match[2] as string;

    if (!isOwner(interaction.user.id)) {
        await interaction.reply({ content: 'Not your approval.', flags: MessageFlags.Ephemeral });
        return;
    }

    try {
        lookupLive(nonce, Date.now());
    } catch (error) {
        if (error instanceof ClickRefusedError) {
            await interaction.reply({ content: refusalText(error), flags: MessageFlags.Ephemeral });
            return;
        }
        throw error;
    }

    if (kind === 'ok') {
        try {
            recordClick(nonce, 'ok', interaction.user.id, null, Date.now());
        } catch (error) {
            if (error instanceof ClickRefusedError) {
                await interaction.reply({ content: refusalText(error), flags: MessageFlags.Ephemeral });
                return;
            }
            throw error;
        }
        await interaction.update({
            content: 'Approve recorded. The Mac picks it up within about 3 minutes.',
            components: [buildApproveRow(nonce, true)],
        });
        return;
    }

    await interaction.showModal(buildConditionModal(nonce));
}

/**
 * Submit of the Fix-first modal. Re-checks the owner, then the token, because
 * the token may have expired while the modal was open.
 */
export async function handleApproveModal(interaction: ModalSubmitInteraction): Promise<void> {
    const match = interaction.customId.match(MODAL_ID_RE);
    if (!match) {
        await interaction.reply({ content: 'Form not recognized.', flags: MessageFlags.Ephemeral });
        return;
    }
    const nonce = match[1] as string;

    const submitter = interaction.user.id;
    if (!isOwner(submitter)) {
        await interaction.reply({ content: 'Not your approval.', flags: MessageFlags.Ephemeral });
        return;
    }

    const cond = interaction.fields.getTextInputValue('condition').trim();
    if (cond.length === 0 || cond.length > CONDITION_MAX || CONTROL_RE.test(cond)) {
        await interaction.reply({
            content: `Condition must be 1 to ${CONDITION_MAX} characters with no control characters or newlines. Nothing was recorded.`,
            flags: MessageFlags.Ephemeral,
        });
        return;
    }

    try {
        lookupLive(nonce, Date.now());
        recordClick(nonce, 'fix', submitter, cond, Date.now());
    } catch (error) {
        if (error instanceof ClickRefusedError) {
            await interaction.reply({ content: refusalText(error), flags: MessageFlags.Ephemeral });
            return;
        }
        throw error;
    }

    await interaction.reply({
        content: 'Fix first recorded. The Mac picks it up within about 3 minutes.',
        flags: MessageFlags.Ephemeral,
    });
}
