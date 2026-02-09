import type { AutocompleteInteraction } from 'discord.js';
import { apiGet } from './api/client.js';
import type { Player, Team } from './api/types.js';

// Shared team cache (single instance across all commands)
let teamCache: Team[] = [];
let teamCacheTime = 0;
const TEAM_CACHE_TTL = 10 * 60 * 1000;

export async function getTeams(): Promise<Team[]> {
    if (Date.now() - teamCacheTime < TEAM_CACHE_TTL && teamCache.length > 0) {
        return teamCache;
    }
    const response = await apiGet<Team[]>('teams', { per_page: 30 });
    teamCache = response.data;
    teamCacheTime = Date.now();
    return teamCache;
}

export async function playerAutocomplete(interaction: AutocompleteInteraction) {
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

export async function teamAutocomplete(interaction: AutocompleteInteraction) {
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
}
