import { describe, it, expect, beforeAll, afterAll } from 'vitest';
import { Client, GatewayIntentBits, EmbedBuilder, ActionRowBuilder, ButtonBuilder, ButtonStyle } from 'discord.js';

// Opt-in live smoke for the plan-review DM feature. Everything in the unit
// tests runs against mocked discord.js and proves handler logic only — this
// file proves the configured bot token can actually send a DM to the owner.
//
// Default-SKIP because it touches live Discord with a real bot token and a
// real DM. It does NOT press any button: a real press writes a real decision
// into the real prod sink that a real drain would later act on.
//
//   LIVE_DISCORD=1 npm test src/interactions/plan-review.live.test.ts
//
// Requires DISCORD_TOKEN and PLAN_REVIEW_OWNER_DISCORD_ID to be set.
//
// config.ts is deliberately NOT imported: requireEnv() throws on missing env,
// which would break the default-skip path in CI.
const LIVE = process.env['LIVE_DISCORD'] === '1';
const TOKEN = process.env['DISCORD_TOKEN'] ?? '';
const OWNER = process.env['PLAN_REVIEW_OWNER_DISCORD_ID'] ?? '';

let client: Client | null = null;

describe.runIf(LIVE && TOKEN !== '' && OWNER !== '')('live plan-review DM (opt-in)', () => {
    beforeAll(async () => {
        client = new Client({ intents: [GatewayIntentBits.Guilds] });
        await client.login(TOKEN);
    });

    afterAll(async () => {
        if (client) {
            await client.destroy();
            client = null;
        }
    });

    it('sends a plan-review embed with Queue/Discard buttons to the owner', async () => {
        if (!client) throw new Error('client not initialised');

        const embed = new EmbedBuilder()
            .setTitle('[smoke] Plan Review Request')
            .setDescription('This is a live smoke test — no real plan. Discard it.')
            .setColor(0xffa500)
            .setTimestamp();

        const row = new ActionRowBuilder<ButtonBuilder>().addComponents(
            new ButtonBuilder()
                .setCustomId('plan_queue_smoke-test')
                .setLabel('Queue')
                .setStyle(ButtonStyle.Success),
            new ButtonBuilder()
                .setCustomId('plan_discard_smoke-test')
                .setLabel('Discard')
                .setStyle(ButtonStyle.Danger),
        );

        const user = await client.users.fetch(OWNER);
        const dm = await user.send({ embeds: [embed], components: [row] });
        expect(dm.id).toBeTruthy();
    });
});

describe.skipIf(LIVE && TOKEN !== '' && OWNER !== '')('live plan-review DM (opt-in)', () => {
    it.skip('SKIP: set LIVE_DISCORD=1 + DISCORD_TOKEN=<token> + PLAN_REVIEW_OWNER_DISCORD_ID=<snowflake> (sends a real DM; does NOT press any button)', () => {});
});
