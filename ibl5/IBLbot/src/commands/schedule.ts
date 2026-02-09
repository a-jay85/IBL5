import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Game, Team } from '../api/types.js';
import { scheduleEmbed } from '../embeds/schedule-embed.js';
import { errorEmbed, isUuid } from '../embeds/common.js';
import type { Command } from './index.js';

// Cache teams list for autocomplete (refreshed every 10 min)
let teamCache: Team[] = [];
let teamCacheTime = 0;
const TEAM_CACHE_TTL = 10 * 60 * 1000;

async function getTeams(): Promise<Team[]> {
    if (Date.now() - teamCacheTime < TEAM_CACHE_TTL && teamCache.length > 0) {
        return teamCache;
    }
    const response = await apiGet<Team[]>('teams', { per_page: 30 });
    teamCache = response.data;
    teamCacheTime = Date.now();
    return teamCache;
}

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

    async autocomplete(interaction: AutocompleteInteraction) {
        const focused = interaction.options.getFocused().toLowerCase();

        try {
            const teams = await getTeams();
            const filtered = teams
                .filter(t =>
                    t.full_name.toLowerCase().includes(focused) ||
                    t.city.toLowerCase().includes(focused) ||
                    t.name.toLowerCase().includes(focused),
                )
                .slice(0, 25);

            await interaction.respond(
                filtered.map(t => ({ name: t.full_name, value: t.uuid })),
            );
        } catch {
            await interaction.respond([]);
        }
    },

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('team', true);
        const count = interaction.options.getInteger('count') ?? 10;

        try {
            if (!isUuid(uuid)) {
                await interaction.editReply({ embeds: [errorEmbed('Please use autocomplete to select a team.')] });
                return;
            }

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
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
