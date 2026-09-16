import { MessageFlags } from 'discord.js';
import type { ButtonInteraction } from 'discord.js';
import { config } from '../config.js';
import { PLAN_SLUG_RE, buildPlanReviewRow } from '../server/plan-review-dm.js';
import { appendDecision, hasDecision } from '../server/decision-store.js';

const OUTCOME = {
    queue: 'queued — pending pickup',
    discard: 'discarded',
} as const;

const ALREADY = {
    queue: 'already queued',
    discard: 'already discarded',
} as const;

/**
 * `dir` is the decision-store test seam; the router passes one argument.
 */
export async function handlePlanReviewButton(interaction: ButtonInteraction, dir?: string): Promise<void> {
    // Parse: plan_queue_<slug> or plan_discard_<slug>
    const match = interaction.customId.match(/^plan_(queue|discard)_(.+)$/);
    if (!match) {
        return;
    }

    const action = match[1] as 'queue' | 'discard';
    const slug = match[2] as string;

    // A custom_id is Discord-supplied input echoed back at us, not trusted server
    // state, so the slug shape is re-tested here even though the DM route
    // validated it on the way out. No match means this bot never minted the id.
    if (!PLAN_SLUG_RE.test(slug)) {
        return;
    }

    // The owner compare runs BEFORE deferUpdate() — a deliberate divergence from
    // trade-buttons.ts:29. deferUpdate() binds the interaction token to *this*
    // presser, and the editReply that follows would rewrite the owner's message
    // and strip buttons the owner has not yet pressed. Any non-owner who reaches
    // the component could therefore neutralise the approval path while writing
    // no record. A DM is not an authorization boundary: Discord delivers a
    // component press from anyone who can reach the message.
    const owner = config.planReview.ownerDiscordId;
    if (!owner || interaction.user.id !== owner) {
        await interaction.reply({ content: 'Not your plan review.', flags: MessageFlags.Ephemeral });
        return;
    }

    // Cross-pass half of the double-press race. The store is the enforcement
    // point; this check exists so the presser sees a comprehensible message
    // instead of a swallowed DecisionExistsError.
    const existing = hasDecision(slug, dir);
    if (existing) {
        await interaction.reply({ content: ALREADY[existing.action], flags: MessageFlags.Ephemeral });
        return;
    }

    await interaction.deferUpdate();

    // Disk first, UI second: if editReply throws on an expired token the decision
    // is already durable and the drain still finds it. The reverse order shows
    // the owner a confirmation for a record no drain will ever see.
    try {
        appendDecision({ slug, action, actor: interaction.user.id }, dir);
    } catch (error) {
        console.error(`Failed to record plan decision (${action} ${slug}):`, error);
        try {
            // Buttons stay enabled so the press can be retried.
            await interaction.editReply({
                content: 'Could not record that decision — please press again.',
                components: [buildPlanReviewRow(slug)],
            });
        } catch {
            // Interaction may have expired — nothing we can do
        }
        return;
    }

    // The row is disabled, not removed: it leaves the decision legible in the DM
    // as an audit trail.
    await interaction.editReply({
        content: OUTCOME[action],
        components: [buildPlanReviewRow(slug, true)],
    });
}
