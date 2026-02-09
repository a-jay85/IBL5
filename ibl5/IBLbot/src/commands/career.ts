import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { PlayerCareerStats } from '../api/types.js';
import { createBaseEmbed, IBL_BLUE, getTeamColor, formatStat, formatPercentage, errorEmbed, playerUrl, draftHistoryUrl, handleCommandError, requireUuid } from '../embeds/common.js';
import { playerAutocomplete } from '../autocomplete.js';
import type { Command } from './index.js';

export const career: Command = {
    data: new SlashCommandBuilder()
        .setName('career')
        .setDescription('View a player\'s career stats')
        .addStringOption(option =>
            option
                .setName('name')
                .setDescription('Player name')
                .setRequired(true)
                .setAutocomplete(true),
        ),

    autocomplete: playerAutocomplete,

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('name', true);

        try {
            if (!await requireUuid(interaction, uuid, 'player')) return;

            const response = await apiGet<PlayerCareerStats>(`players/${uuid}/stats`, undefined, { resourceType: 'player' });
            const stats = response.data;

            const embed = createBaseEmbed()
                .setColor(IBL_BLUE)
                .setTitle(`${stats.name} - Career Stats`)
                .setURL(playerUrl(stats.pid))
                .addFields(
                    {
                        name: 'Career Totals',
                        value: [
                            `Games: ${stats.career_totals.games}`,
                            `Minutes: ${stats.career_totals.minutes}`,
                            `Points: ${stats.career_totals.points}`,
                            `Rebounds: ${stats.career_totals.rebounds}`,
                            `Assists: ${stats.career_totals.assists}`,
                            `Steals: ${stats.career_totals.steals}`,
                            `Blocks: ${stats.career_totals.blocks}`,
                        ].join('\n'),
                        inline: true,
                    },
                    {
                        name: 'Career Averages',
                        value: [
                            `PPG: **${formatStat(stats.career_averages.points_per_game)}**`,
                            `RPG: ${formatStat(stats.career_averages.rebounds_per_game)}`,
                            `APG: ${formatStat(stats.career_averages.assists_per_game)}`,
                        ].join('\n'),
                        inline: true,
                    },
                    {
                        name: 'Career Shooting',
                        value: [
                            `FG%: ${formatPercentage(stats.career_percentages.fg_percentage)}`,
                            `FT%: ${formatPercentage(stats.career_percentages.ft_percentage)}`,
                            `3PT%: ${formatPercentage(stats.career_percentages.three_pt_percentage)}`,
                        ].join('\n'),
                        inline: true,
                    },
                );

            if (stats.draft.year !== null) {
                embed.addFields({
                    name: 'Draft Info',
                    value: stats.draft.team_id !== null
                        ? `[${stats.draft.year} Round ${stats.draft.round}, Pick ${stats.draft.pick} (${stats.draft.team})](${draftHistoryUrl(stats.draft.team_id)})`
                        : `${stats.draft.year} Round ${stats.draft.round}, Pick ${stats.draft.pick} (${stats.draft.team ?? 'N/A'})`,
                    inline: false,
                });
            }

            if (stats.playoff_minutes > 0) {
                embed.addFields({
                    name: 'Playoff Experience',
                    value: `${stats.playoff_minutes} minutes`,
                    inline: false,
                });
            }

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
