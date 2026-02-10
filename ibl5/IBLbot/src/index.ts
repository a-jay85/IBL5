import { Client, Events, GatewayIntentBits } from 'discord.js';
import { config } from './config.js';
import { commands } from './commands/index.js';
import { startExpressServer } from './server/express.js';
import { handleTradeButton } from './interactions/trade-buttons.js';

const client = new Client({
    intents: [GatewayIntentBits.Guilds],
}); 

// Bot ready
client.once(Events.ClientReady, c => {
    console.log(`Ready! Logged in as ${c.user.tag}`);
    console.log(`Serving ${commands.size} slash commands`);
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
startExpressServer(client);
