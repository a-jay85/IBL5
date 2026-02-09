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
                .setName('view')
                .setDescription('How to display standings')
                .setRequired(true)
                .addChoices(
                    { name: 'League by Conference', value: 'League' },
                    { name: 'Eastern Conference', value: 'Eastern' },
                    { name: 'Western Conference', value: 'Western' },
                    { name: 'League by Division', value: 'All Divisions' },
                    { name: 'Atlantic Division', value: 'Atlantic' },
                    { name: 'Central Division', value: 'Central' },
                    { name: 'Midwest Division', value: 'Midwest' },
                    { name: 'Pacific Division', value: 'Pacific' },
                ),
        ),

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const view = interaction.options.getString('view', true);

        try {
            const isConference = view === 'Eastern' || view === 'Western';
            const endpoint = isConference ? `standings/${view}` : 'standings';
            const response = await apiGet<StandingsEntry[]>(endpoint);
            await interaction.editReply({ embeds: [standingsEmbed(response.data, view)] });
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
