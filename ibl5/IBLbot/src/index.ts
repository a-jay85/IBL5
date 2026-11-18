import { Client, Events, GatewayIntentBits } from 'discord.js';
import { config } from './config.js';
import { commands } from './commands/index.js';
import { startExpressServer } from './server/express.js';
import { handleTradeButton } from './interactions/trade-buttons.js';
import { handlePlanReviewButton } from './interactions/plan-review-buttons.js';
import { handleApproveButton, handleApproveModal } from './interactions/approve-buttons.js';
import { startStaleSweeper } from './server/stale-decision-sweeper.js';

import { loadSimRecapSettings } from './sim-recap/settings.js';
import { createDiscordSweeper } from './sim-recap/discord-adapter.js';
import type { Sweeper } from './sim-recap/sweeper.js';

let sweepHandle: NodeJS.Timeout | undefined;
let simRecapHandle: NodeJS.Timeout | undefined;
let simRecapSweeper: Sweeper | undefined;

const client = new Client({
    intents: [GatewayIntentBits.Guilds],
});

// Bot ready
client.once(Events.ClientReady, c => {
    console.log(`Ready! Logged in as ${c.user.tag}`);
    console.log(`Serving ${commands.size} slash commands`);
    sweepHandle = startStaleSweeper(client);
    console.log('Stale plan-decision sweeper started');
    // Starts last, after the existing startup order above.
    simRecapSweeper = createDiscordSweeper(client, loadSimRecapSettings());
    simRecapHandle = simRecapSweeper.start();
});

// Slash command handler
client.on(Events.InteractionCreate, async interaction => {
    if (interaction.isChatInputCommand()) {
        const command = commands.get(interaction.commandName);
        if (!command) {
            console.warn(`Unknown command: ${interaction.commandName}`);
            return;
        }

        try {
            await command.execute(interaction);
        } catch (error) {
            console.error(`Error executing /${interaction.commandName}:`, error);
            try {
                const reply = { content: 'An error occurred while executing this command.', ephemeral: true };
                if (interaction.replied || interaction.deferred) {
                    await interaction.followUp(reply);
                } else {
                    await interaction.reply(reply);
                }
            } catch {
                // Interaction expired — nothing we can do
            }
        }
    } else if (interaction.isButton()) {
        if (interaction.customId.startsWith('trade_')) {
            try {
                await handleTradeButton(interaction);
            } catch (error) {
                console.error(`Trade button error for ${interaction.customId}:`, error);
            }
        } else if (interaction.customId.startsWith('plan_')) {
            try {
                await handlePlanReviewButton(interaction);
            } catch (error) {
                console.error(`Plan review button error for ${interaction.customId}:`, error);
            }
        } else if (interaction.customId.startsWith('approve_')) {
            try {
                await handleApproveButton(interaction);
            } catch (error) {
                console.error(`Approve button error for ${interaction.customId}:`, error);
            }
        }
    } else if (interaction.isModalSubmit() && interaction.customId.startsWith('approve_cond_')) {
        try {
            await handleApproveModal(interaction);
        } catch (error) {
            console.error(`Approve modal error for ${interaction.customId}:`, error);
        }
    } else if (interaction.isAutocomplete()) {
        const command = commands.get(interaction.commandName);
        if (command?.autocomplete) {
            try {
                await command.autocomplete(interaction);
            } catch (error) {
                console.error(`Autocomplete error for /${interaction.commandName}:`, error);
            }
        }
    }
});

// Start bot
client.login(config.discord.token);

// Start Express server for /discordDM endpoint
startExpressServer(client, undefined, () => simRecapSweeper);

// No shutdown handlers existed before the sweeper. The interval is the only thing
// that keeps the event loop alive past a signal, so it is the only thing torn down
// here; pm2 owns the rest of the process lifecycle.
for (const signal of ['SIGTERM', 'SIGINT'] as const) {
    process.on(signal, () => {
        if (sweepHandle) clearInterval(sweepHandle);
        if (simRecapHandle) clearInterval(simRecapHandle);
        process.exit(0);
    });
}
