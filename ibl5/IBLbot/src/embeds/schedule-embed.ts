import type { Game, SeasonInfo } from '../api/types.js';
import { createBaseEmbed, IBL_BLUE, getTeamColor, boxScoreUrl, teamUrl, scheduleUrl } from './common.js';

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

export function nextsimEmbed(games: Game[], teamName: string, teamId: number) {
    const embed = createBaseEmbed()
        .setColor(getTeamColor(teamName))
        .setTitle(`${teamName} — Next Sim Schedule`)
        .setURL(scheduleUrl(teamId));

    if (games.length === 0) {
        embed.setDescription('No games projected for the next sim.');
        return embed;
    }

    const lines = games.map(g => {
        const isHome = g.home.full_name === teamName;
        const opponent = isHome ? g.visitor : g.home;
        const prefix = isHome ? 'vs' : '@';
        const shortDate = g.date.slice(5); // "MM-DD" from "YYYY-MM-DD"
        const oppLink = `[${opponent.name}](${teamUrl(opponent.team_id)})`;

        return `${shortDate} | ${prefix} ${oppLink}`;
    });

    embed.setDescription(lines.join('\n'));
    return embed;
}
