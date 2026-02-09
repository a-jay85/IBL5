import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Leader } from '../api/types.js';
import { leadersEmbed } from '../embeds/leaders-embed.js';
import { errorEmbed } from '../embeds/common.js';
import type { Command } from './index.js';

export const leaders: Command = {
    data: new SlashCommandBuilder()
        .setName('leaders')
        .setDescription('View league statistical leaders')
        .addStringOption(option =>
            option
                .setName('stat')
                .setDescription('Statistical category')
                .setRequired(true)
                .addChoices(
                    { name: 'Points', value: 'ppg' },
                    { name: 'Rebounds', value: 'rpg' },
                    { name: 'Assists', value: 'apg' },
                    { name: 'Steals', value: 'spg' },
                    { name: 'Blocks', value: 'bpg' },
                    { name: 'FG%', value: 'fgp' },
                    { name: 'FT%', value: 'ftp' },
                    { name: '3PT%', value: 'tgp' },
                ),
        )
        .addIntegerOption(option =>
            option
                .setName('count')
                .setDescription('Number of leaders to show (default: 10)')
                .setMinValue(1)
                .setMaxValue(25),
        ),

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const category = interaction.options.getString('stat', true);
        const count = interaction.options.getInteger('count') ?? 10;

        try {
            const response = await apiGet<Leader[]>('stats/leaders', {
                category,
                per_page: count,
            });
            await interaction.editReply({ embeds: [leadersEmbed(response.data, category)] });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
