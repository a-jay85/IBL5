import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Game, SeasonInfo } from '../api/types.js';
import { lastsimLeagueEmbed, lastsimTeamEmbed } from '../embeds/schedule-embed.js';
import { handleCommandError, requireUuid } from '../embeds/common.js';
import { teamAutocomplete, resolveTeam } from '../autocomplete.js';
import type { Command } from './index.js';

export const lastsim: Command = {
    data: new SlashCommandBuilder()
        .setName('lastsim')
        .setDescription('View scores from the last simulation')
        .addStringOption(option =>
            option
                .setName('team')
                .setDescription('Team name (omit for league-wide view)')
                .setAutocomplete(true),
        ),

    autocomplete: teamAutocomplete,

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('team');

        try {
            const seasonResponse = await apiGet<SeasonInfo>('season');
            const season = seasonResponse.data;

            if (uuid) {
                if (!await requireUuid(interaction, uuid, 'team')) return;

                const { name: teamName, id: teamId } = await resolveTeam(uuid);

                const response = await apiGet<Game[]>('games', {
                    team: uuid,
                    status: 'completed',
                    date_start: season.last_sim.start_date,
                    date_end: season.last_sim.end_date,
                    per_page: 25,
                    sort: 'game_date',
                    order: 'asc',
                }, { resourceType: 'team' });

                await interaction.editReply({
                    embeds: [lastsimTeamEmbed(response.data, season, teamName, teamId)],
                });
            } else {
                const response = await apiGet<Game[]>('games', {
                    status: 'completed',
                    date_start: season.last_sim.start_date,
                    date_end: season.last_sim.end_date,
                    per_page: 100,
                    sort: 'game_date',
                    order: 'asc',
                });

                await interaction.editReply({
                    embeds: [lastsimLeagueEmbed(response.data, season)],
                });
            }
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
