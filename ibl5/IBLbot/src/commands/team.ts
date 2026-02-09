import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Team, TeamDetail } from '../api/types.js';
import { createBaseEmbed, getTeamColor, formatRecord, errorEmbed, isUuid, teamUrl, discordProfileUrl } from '../embeds/common.js';
import type { Command } from './index.js';

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

        const uuid = interaction.options.getString('name', true);

        try {
            if (!isUuid(uuid)) {
                await interaction.editReply({ embeds: [errorEmbed('Please use autocomplete to select a team.')] });
                return;
            }

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
                            `Win%: ${t.standings.win_percentage !== null ? (typeof t.standings.win_percentage === 'string' ? parseFloat(t.standings.win_percentage) : t.standings.win_percentage).toFixed(3) : '-'}`,
                            `Conf GB: ${t.standings.conference_games_back ?? '-'}`,
                            `Div GB: ${t.standings.division_games_back ?? '-'}`,
                            `Games Left: ${t.standings.games_remaining ?? '-'}`,
                        ].join('\n'),
                        inline: true,
                    },
                );

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
