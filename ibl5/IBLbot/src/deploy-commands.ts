import { REST, Routes } from 'discord.js';
import { config } from './config.js';
import { commands } from './commands/index.js';

const rest = new REST().setToken(config.discord.token);

const commandData = commands.map(cmd => cmd.data.toJSON());

async function deploy() {
    try {
        console.log(`Deploying ${commandData.length} slash commands...`);

        if (config.discord.guildId !== '') {
            // Guild-specific (instant, good for dev)
            await rest.put(
                Routes.applicationGuildCommands(config.discord.clientId, config.discord.guildId),
                { body: commandData },
            );
            console.log(`Deployed ${commandData.length} commands to guild ${config.discord.guildId}`);
        } else {
            // Global (takes up to 1 hour to propagate)
            await rest.put(
                Routes.applicationCommands(config.discord.clientId),
                { body: commandData },
            );
            console.log(`Deployed ${commandData.length} commands globally`);
        }
    } catch (error) {
        console.error('Failed to deploy commands:', error);
        process.exit(1);
    }
}

deploy();
