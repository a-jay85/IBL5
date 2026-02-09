import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Game, Boxscore } from '../api/types.js';
import { boxscoreEmbeds } from '../embeds/boxscore-embed.js';
import { errorEmbed } from '../embeds/common.js';
import type { Command } from './index.js';

export const boxscore: Command = {
    data: new SlashCommandBuilder()
        .setName('boxscore')
        .setDescription('View a game\'s box score')
        .addStringOption(option =>
            option
                .setName('game')
                .setDescription('Select a recent game')
                .setRequired(true)
                .setAutocomplete(true),
        ),

    async autocomplete(interaction: AutocompleteInteraction) {
        try {
            const response = await apiGet<Game[]>('games', {
                status: 'played',
                per_page: 25,
                sort: 'game_date',
                order: 'desc',
            });
            await interaction.respond(
                response.data.map(g => ({
                    name: `${g.date} | ${g.visitor.city} ${g.visitor.score} @ ${g.home.city} ${g.home.score}`,
                    value: g.uuid,
                })),
            );
        } catch {
            await interaction.respond([]);
        }
    },

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const gameUuid = interaction.options.getString('game', true);

        try {
            const response = await apiGet<Boxscore>(`games/${gameUuid}/boxscore`);
            const embeds = boxscoreEmbeds(response.data);
            await interaction.editReply({ embeds });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
