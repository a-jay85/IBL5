import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Game, SeasonInfo } from '../api/types.js';
import { lastsimEmbed } from '../embeds/schedule-embed.js';
import { handleCommandError } from '../embeds/common.js';
import type { Command } from './index.js';

export const lastsim: Command = {
    data: new SlashCommandBuilder()
        .setName('lastsim')
        .setDescription('View all scores from the last simulation'),

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        try {
            const seasonResponse = await apiGet<SeasonInfo>('season');
            const season = seasonResponse.data;

            const response = await apiGet<Game[]>('games', {
                status: 'completed',
                date_start: season.last_sim.start_date,
                date_end: season.last_sim.end_date,
                per_page: 100,
                sort: 'game_date',
                order: 'asc',
            });

            await interaction.editReply({ embeds: [lastsimEmbed(response.data, season)] });
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
