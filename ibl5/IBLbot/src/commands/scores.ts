import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Game } from '../api/types.js';
import { scoresEmbed } from '../embeds/schedule-embed.js';
import { errorEmbed } from '../embeds/common.js';
import type { Command } from './index.js';

export const scores: Command = {
    data: new SlashCommandBuilder()
        .setName('scores')
        .setDescription('View recent game scores')
        .addIntegerOption(option =>
            option
                .setName('count')
                .setDescription('Number of games to show (default: 10)')
                .setMinValue(1)
                .setMaxValue(25),
        ),

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const count = interaction.options.getInteger('count') ?? 10;

        try {
            const response = await apiGet<Game[]>('games', {
                status: 'played',
                per_page: count,
                sort: 'game_date',
                order: 'desc',
            });
            await interaction.editReply({ embeds: [scoresEmbed(response.data)] });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
