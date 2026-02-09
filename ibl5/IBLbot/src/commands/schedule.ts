import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Game } from '../api/types.js';
import { scheduleEmbed } from '../embeds/schedule-embed.js';
import { handleCommandError, requireUuid } from '../embeds/common.js';
import { teamAutocomplete, getTeams } from '../autocomplete.js';
import type { Command } from './index.js';

export const schedule: Command = {
    data: new SlashCommandBuilder()
        .setName('schedule')
        .setDescription('View a team\'s schedule')
        .addStringOption(option =>
            option
                .setName('team')
                .setDescription('Team name')
                .setRequired(true)
                .setAutocomplete(true),
        )
        .addIntegerOption(option =>
            option
                .setName('count')
                .setDescription('Number of games to show (default: 10)')
                .setMinValue(1)
                .setMaxValue(25),
        ),

    autocomplete: teamAutocomplete,

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('team', true);
        const count = interaction.options.getInteger('count') ?? 10;

        try {
            if (!await requireUuid(interaction, uuid, 'team')) return;

            // Look up team name for display
            const teams = await getTeams();
            const team = teams.find(t => t.uuid === uuid);
            const teamName = team?.full_name ?? uuid;

            const response = await apiGet<Game[]>('games', {
                team: uuid,
                per_page: count,
                sort: 'game_date',
                order: 'desc',
            }, { resourceType: 'team' });

            await interaction.editReply({
                embeds: [scheduleEmbed(response.data, teamName)],
            });
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
