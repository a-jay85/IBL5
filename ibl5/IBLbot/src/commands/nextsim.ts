import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Game, SeasonInfo } from '../api/types.js';
import { nextsimEmbed } from '../embeds/schedule-embed.js';
import { handleCommandError, requireUuid } from '../embeds/common.js';
import { teamAutocomplete, getTeams } from '../autocomplete.js';
import type { Command } from './index.js';

export const nextsim: Command = {
    data: new SlashCommandBuilder()
        .setName('nextsim')
        .setDescription('View a team\'s projected games for the next sim')
        .addStringOption(option =>
            option
                .setName('team')
                .setDescription('Team name')
                .setRequired(true)
                .setAutocomplete(true),
        ),

    autocomplete: teamAutocomplete,

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('team', true);

        try {
            if (!await requireUuid(interaction, uuid, 'team')) return;

            const teams = await getTeams();
            const team = teams.find(t => t.uuid === uuid);
            const teamName = team?.full_name ?? uuid;
            const teamId = team?.team_id ?? 0;

            const seasonResponse = await apiGet<SeasonInfo>('season');
            const season = seasonResponse.data;

            // Compute the day after last sim end date (matches PHP ADDDATE(?, 1))
            const lastEnd = new Date(season.last_sim.end_date + 'T00:00:00');
            lastEnd.setDate(lastEnd.getDate() + 1);
            const dateStart = lastEnd.toISOString().slice(0, 10);

            const response = await apiGet<Game[]>('games', {
                team: uuid,
                status: 'scheduled',
                date_start: dateStart,
                date_end: season.projected_next_sim_end_date,
                per_page: 25,
                sort: 'game_date',
                order: 'asc',
            }, { resourceType: 'team' });

            await interaction.editReply({
                embeds: [nextsimEmbed(response.data, teamName, teamId)],
            });
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
