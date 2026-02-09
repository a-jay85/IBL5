import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { TeamDetail } from '../api/types.js';
import { createBaseEmbed, getTeamColor, formatRecord, formatWinPct, teamUrl, discordProfileUrl, handleCommandError, requireUuid } from '../embeds/common.js';
import { teamAutocomplete } from '../autocomplete.js';
import type { Command } from './index.js';

export const team: Command = {
    data: new SlashCommandBuilder()
        .setName('team')
        .setDescription('View team details and record')
        .addStringOption(option =>
            option
                .setName('name')
                .setDescription('Team name')
                .setRequired(true)
                .setAutocomplete(true),
        ),

    autocomplete: teamAutocomplete,

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('name', true);

        try {
            if (!await requireUuid(interaction, uuid, 'team')) return;

            const response = await apiGet<TeamDetail>(`teams/${uuid}`, undefined, { resourceType: 'team' });
            const t = response.data;

            const embed = createBaseEmbed()
                .setColor(getTeamColor(t.city))
                .setTitle(t.full_name)
                .setURL(teamUrl(t.team_id))
                .setDescription(`${t.conference ?? '-'} Conference | ${t.division ?? '-'} Division`)
                .addFields(
                    {
                        name: 'Info',
                        value: [
                            `Owner: ${t.owner_discord_id !== null ? `[${t.owner}](${discordProfileUrl(t.owner_discord_id)})` : t.owner}`,
                            `Arena: ${t.arena}`,
                        ].join('\n'),
                        inline: true,
                    },
                    {
                        name: 'Record',
                        value: [
                            `Overall: **${formatRecord(t.record.league)}**`,
                            `Conference: ${formatRecord(t.record.conference)}`,
                            `Division: ${formatRecord(t.record.division)}`,
                            `Home: ${formatRecord(t.record.home)}`,
                            `Away: ${formatRecord(t.record.away)}`,
                        ].join('\n'),
                        inline: true,
                    },
                    {
                        name: 'Standings',
                        value: [
                            `Win%: ${formatWinPct(t.standings.win_percentage)}`,
                            `Conf GB: ${t.standings.conference_games_back ?? '-'}`,
                            `Div GB: ${t.standings.division_games_back ?? '-'}`,
                            `Games Left: ${t.standings.games_remaining ?? '-'}`,
                        ].join('\n'),
                        inline: true,
                    },
                );

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
