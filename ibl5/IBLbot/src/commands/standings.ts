import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { StandingsEntry } from '../api/types.js';
import { standingsEmbed } from '../embeds/standings-embed.js';
import { handleCommandError } from '../embeds/common.js';
import type { Command } from './index.js';

export const standings: Command = {
    data: new SlashCommandBuilder()
        .setName('standings')
        .setDescription('View league standings')
        .addStringOption(option =>
            option
                .setName('conference')
                .setDescription('Filter by conference')
                .addChoices(
                    { name: 'Eastern', value: 'Eastern' },
                    { name: 'Western', value: 'Western' },
                ),
        ),

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const conference = interaction.options.getString('conference');

        try {
            const endpoint = conference
                ? `standings/${conference}`
                : 'standings';
            const response = await apiGet<StandingsEntry[]>(endpoint);
            await interaction.editReply({ embeds: [standingsEmbed(response.data, conference ?? undefined)] });
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
