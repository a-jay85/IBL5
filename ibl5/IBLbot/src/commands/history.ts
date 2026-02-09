import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Player, PlayerSeasonStats } from '../api/types.js';
import { createBaseEmbed, pad, errorEmbed } from '../embeds/common.js';
import type { Command } from './index.js';

export const history: Command = {
    data: new SlashCommandBuilder()
        .setName('history')
        .setDescription('View a player\'s season-by-season stats')
        .addStringOption(option =>
            option
                .setName('name')
                .setDescription('Player name')
                .setRequired(true)
                .setAutocomplete(true),
        ),

    async autocomplete(interaction: AutocompleteInteraction) {
        const focused = interaction.options.getFocused();
        if (focused.length < 2) {
            await interaction.respond([]);
            return;
        }

        try {
            const response = await apiGet<Player[]>('players', {
                search: focused,
                per_page: 10,
            });
            await interaction.respond(
                response.data.map(p => ({
                    name: `${p.name} (${p.position} - ${p.team?.full_name ?? 'FA'})`,
                    value: p.uuid,
                })),
            );
        } catch {
            await interaction.respond([]);
        }
    },

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('name', true);

        try {
            const response = await apiGet<PlayerSeasonStats[]>(`players/${uuid}/history`);
            const seasons = response.data;

            if (seasons.length === 0) {
                await interaction.editReply({ embeds: [errorEmbed('No season history found for this player.')] });
                return;
            }

            // Get player name from the first season's context or UUID
            const playerName = seasons[0].team.name !== '' ? `Season History` : 'Season History';

            const embed = createBaseEmbed()
                .setColor(0x1E90FF)
                .setTitle(`Season History`);

            const header = `${pad('Year', 5)} ${pad('Team', 14)} ${pad('GP', 3, 'right')} ${pad('PPG', 5, 'right')} ${pad('RPG', 5, 'right')} ${pad('APG', 5, 'right')} ${pad('FG%', 5, 'right')}`;
            const lines = seasons.map(s => {
                return `${pad(String(s.year), 5)} ${pad(s.team.name, 14)} ${pad(String(s.games), 3, 'right')} ${pad(s.per_game.points.toFixed(1), 5, 'right')} ${pad(s.per_game.rebounds.toFixed(1), 5, 'right')} ${pad(s.per_game.assists.toFixed(1), 5, 'right')} ${pad(s.percentages.fg.toFixed(1), 5, 'right')}`;
            });

            const table = '```\n' + header + '\n' + lines.join('\n') + '\n```';

            if (table.length <= 4096) {
                embed.setDescription(table);
            } else {
                const mid = Math.ceil(lines.length / 2);
                embed.addFields(
                    { name: 'Seasons', value: '```\n' + header + '\n' + lines.slice(0, mid).join('\n') + '\n```' },
                    { name: '\u200B', value: '```\n' + lines.slice(mid).join('\n') + '\n```' },
                );
            }

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
