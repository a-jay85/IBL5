import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Player, PlayerDetail } from '../api/types.js';
import { createBaseEmbed, formatStat, formatPercentage, pad, errorEmbed } from '../embeds/common.js';
import type { Command } from './index.js';

async function playerAutocomplete(interaction: AutocompleteInteraction) {
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
}

export const compare: Command = {
    data: new SlashCommandBuilder()
        .setName('compare')
        .setDescription('Compare two players side-by-side')
        .addStringOption(option =>
            option
                .setName('player1')
                .setDescription('First player')
                .setRequired(true)
                .setAutocomplete(true),
        )
        .addStringOption(option =>
            option
                .setName('player2')
                .setDescription('Second player')
                .setRequired(true)
                .setAutocomplete(true),
        ),

    async autocomplete(interaction: AutocompleteInteraction) {
        await playerAutocomplete(interaction);
    },

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid1 = interaction.options.getString('player1', true);
        const uuid2 = interaction.options.getString('player2', true);

        try {
            const [res1, res2] = await Promise.all([
                apiGet<PlayerDetail>(`players/${uuid1}`),
                apiGet<PlayerDetail>(`players/${uuid2}`),
            ]);

            const p1 = res1.data;
            const p2 = res2.data;

            const embed = createBaseEmbed()
                .setColor(0x1E90FF)
                .setTitle(`${p1.name} vs ${p2.name}`);

            const rows = [
                ['Team', p1.team?.full_name ?? 'FA', p2.team?.full_name ?? 'FA'],
                ['Pos', p1.position, p2.position],
                ['Age', String(p1.age), String(p2.age)],
                ['GP', String(p1.stats.games_played), String(p2.stats.games_played)],
                ['PPG', formatStat(p1.stats.points_per_game), formatStat(p2.stats.points_per_game)],
                ['FG%', formatPercentage(p1.stats.fg_percentage), formatPercentage(p2.stats.fg_percentage)],
                ['FT%', formatPercentage(p1.stats.ft_percentage), formatPercentage(p2.stats.ft_percentage)],
                ['3P%', formatPercentage(p1.stats.three_pt_percentage), formatPercentage(p2.stats.three_pt_percentage)],
                ['REB', String(p1.stats.offensive_rebounds + p1.stats.defensive_rebounds), String(p2.stats.offensive_rebounds + p2.stats.defensive_rebounds)],
                ['AST', String(p1.stats.assists), String(p2.stats.assists)],
                ['STL', String(p1.stats.steals), String(p2.stats.steals)],
                ['BLK', String(p1.stats.blocks), String(p2.stats.blocks)],
                ['TO', String(p1.stats.turnovers), String(p2.stats.turnovers)],
                ['Salary', `$${p1.contract.current_salary}K`, `$${p2.contract.current_salary}K`],
            ];

            const header = `${pad('', 6)} ${pad(p1.name, 18)} ${pad(p2.name, 18)}`;
            const lines = rows.map(([label, v1, v2]) =>
                `${pad(label, 6)} ${pad(v1, 18)} ${pad(v2, 18)}`,
            );

            embed.setDescription('```\n' + header + '\n' + lines.join('\n') + '\n```');

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
