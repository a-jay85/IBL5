import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Player, Team, TeamDetail } from '../api/types.js';
import { rosterEmbed } from '../embeds/roster-embed.js';
import { errorEmbed, isUuid } from '../embeds/common.js';
import type { Command } from './index.js';

// Shared team cache
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

export const roster: Command = {
    data: new SlashCommandBuilder()
        .setName('roster')
        .setDescription('View a team\'s roster')
        .addStringOption(option =>
            option
                .setName('team')
                .setDescription('Team name')
                .setRequired(true)
                .setAutocomplete(true),
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

        try {
            if (!isUuid(uuid)) {
                await interaction.editReply({ embeds: [errorEmbed('Please use autocomplete to select a team.')] });
                return;
            }

            const [teamResponse, rosterResponse] = await Promise.all([
                apiGet<TeamDetail>(`teams/${uuid}`, undefined, { resourceType: 'team' }),
                apiGet<Player[]>(`teams/${uuid}/roster`, undefined, { resourceType: 'team' }),
            ]);

            await interaction.editReply({
                embeds: [rosterEmbed(teamResponse.data, rosterResponse.data)],
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
