import type { Game, SeasonInfo } from '../api/types.js';
import { createBaseEmbed, IBL_BLUE, getTeamColor, boxScoreUrl } from './common.js';

export function lastsimEmbed(games: Game[], season: SeasonInfo) {
    const embed = createBaseEmbed()
        .setColor(IBL_BLUE)
        .setTitle(`Last Sim Scores — ${season.phase} Sim #${season.last_sim.phase_sim_number}`);

    if (games.length === 0) {
        embed.setDescription('No games found for the last sim.');
        return embed;
    }

    const lines = games.map(g => {
        const boxUrl = boxScoreUrl(g.box_score_id);
        const shortDate = g.date.slice(5); // "MM-DD" from "YYYY-MM-DD"

        if (g.status === 'played' || g.status === 'completed') {
            const winner = g.visitor.score > g.home.score ? 'visitor' : 'home';
            const vBold = winner === 'visitor' ? '**' : '';
            const hBold = winner === 'home' ? '**' : '';
            return `[${shortDate}](${boxUrl}) | ${vBold}${g.visitor.name} ${g.visitor.score}${vBold} @ ${hBold}${g.home.name} ${g.home.score}${hBold}`;
        }
        return `${shortDate} | ${g.visitor.name} @ ${g.home.name} (${g.status})`;
    });

    embed.setDescription(lines.join('\n'));
    return embed;
}

export function scheduleEmbed(games: Game[], teamName: string) {
    const embed = createBaseEmbed()
        .setColor(getTeamColor(teamName))
        .setTitle(`${teamName} Schedule`);

    if (games.length === 0) {
        embed.setDescription('No games found.');
        return embed;
    }

    const lines = games.map(g => {
        const isHome = g.home.city === teamName || g.home.name === teamName || g.home.full_name === teamName;
        const opponent = isHome ? g.visitor : g.home;
        const prefix = isHome ? 'vs' : '@';

        if (g.status === 'played' || g.status === 'completed') {
            const teamScore = isHome ? g.home.score : g.visitor.score;
            const oppScore = isHome ? g.visitor.score : g.home.score;
            const result = teamScore > oppScore ? 'W' : 'L';
            return `${g.date} | ${prefix} ${opponent.city} — **${result}** ${teamScore}-${oppScore}`;
        }
        return `${g.date} | ${prefix} ${opponent.city}`;
    });

    embed.setDescription(lines.join('\n'));
    return embed;
}
