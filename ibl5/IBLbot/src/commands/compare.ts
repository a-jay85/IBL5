import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Player, PlayerDetail } from '../api/types.js';
import { createBaseEmbed, formatStat, formatPercentage, errorEmbed, isUuid, playerUrl, teamUrl } from '../embeds/common.js';
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
            if (!isUuid(uuid1) || !isUuid(uuid2)) {
                await interaction.editReply({ embeds: [errorEmbed('Please use autocomplete to select both players.')] });
                return;
            }

            const [res1, res2] = await Promise.all([
                apiGet<PlayerDetail>(`players/${uuid1}`, undefined, { resourceType: 'player' }),
                apiGet<PlayerDetail>(`players/${uuid2}`, undefined, { resourceType: 'player' }),
            ]);

            const p1 = res1.data;
            const p2 = res2.data;

            function playerStats(p: typeof p1) {
                return [
                    `Team: ${p.team ? `[${p.team.full_name}](${teamUrl(p.team.team_id)})` : 'FA'}`,
                    `Pos: ${p.position} | Age: ${p.age}`,
                    `GP: ${p.stats.games_played}`,
                    `PPG: **${formatStat(p.stats.points_per_game)}**`,
                    `FG: ${formatPercentage(p.stats.fg_percentage)}`,
                    `FT: ${formatPercentage(p.stats.ft_percentage)}`,
                    `3PT: ${formatPercentage(p.stats.three_pt_percentage)}`,
                    `REB: ${p.stats.offensive_rebounds + p.stats.defensive_rebounds}`,
                    `AST: ${p.stats.assists} | STL: ${p.stats.steals}`,
                    `BLK: ${p.stats.blocks} | TO: ${p.stats.turnovers}`,
                    `Salary: ${p.contract.current_salary}`,
                ].join('\n');
            }

            const embed = createBaseEmbed()
                .setColor(0x1E90FF)
                .setTitle(`Compare Players`)
                .setDescription(`[${p1.name}](${playerUrl(p1.pid)}) vs [${p2.name}](${playerUrl(p2.pid)})`)
                .addFields(
                    { name: p1.name, value: playerStats(p1), inline: true },
                    { name: p2.name, value: playerStats(p2), inline: true },
                );

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
