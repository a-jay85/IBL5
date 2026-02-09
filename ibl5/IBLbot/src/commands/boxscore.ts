import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Game, Boxscore } from '../api/types.js';
import { boxscoreEmbeds } from '../embeds/boxscore-embed.js';
import { errorEmbed, isUuid } from '../embeds/common.js';
import type { Command } from './index.js';

export const boxscore: Command = {
    data: new SlashCommandBuilder()
        .setName('boxscore')
        .setDescription('View a game\'s box score')
        .addStringOption(option =>
            option
                .setName('date')
                .setDescription('Game date (YYYY-MM-DD format)')
                .setRequired(true)
                .setAutocomplete(true),
        )
        .addStringOption(option =>
            option
                .setName('game')
                .setDescription('Select a game from the chosen date')
                .setRequired(true)
                .setAutocomplete(true),
        ),

    async autocomplete(interaction: AutocompleteInteraction) {
        const focusedOption = interaction.options.getFocused(true);

        try {
            if (focusedOption.name === 'date') {
                // Return recent game dates
                const response = await apiGet<Game[]>('games', {
                    status: 'completed',
                    per_page: 25,
                    sort: 'game_date',
                    order: 'desc',
                });

                // Get unique dates
                const uniqueDates = [...new Set(response.data.map(g => g.date))];

                await interaction.respond(
                    uniqueDates.map(date => ({
                        name: date,
                        value: date,
                    })),
                );
            } else if (focusedOption.name === 'game') {
                // Return games for the selected date
                const selectedDate = interaction.options.getString('date');

                if (!selectedDate) {
                    await interaction.respond([]);
                    return;
                }

                const response = await apiGet<Game[]>('games', {
                    status: 'completed',
                    date: selectedDate,
                    per_page: 25,
                    sort: 'game_date',
                    order: 'desc',
                });

                await interaction.respond(
                    response.data.map(g => ({
                        name: `${g.visitor.city} ${g.visitor.score} @ ${g.home.city} ${g.home.score}`,
                        value: g.uuid,
                    })),
                );
            }
        } catch {
            await interaction.respond([]);
        }
    },

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('game', true);

        try {
            if (!isUuid(uuid)) {
                await interaction.editReply({ embeds: [errorEmbed('Please use autocomplete to select a game.')] });
                return;
            }

            const response = await apiGet<Boxscore>(`games/${uuid}/boxscore`, undefined, { resourceType: 'game' });
            const embeds = boxscoreEmbeds(response.data);
            await interaction.editReply({ embeds });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
