import type { Game } from '../api/types.js';
import { createBaseEmbed, getTeamColor } from './common.js';

export function scoresEmbed(games: Game[]) {
    const embed = createBaseEmbed()
        .setColor(0x1E90FF)
        .setTitle('Recent Scores');

    if (games.length === 0) {
        embed.setDescription('No recent games found.');
        return embed;
    }

    const lines = games.map(g => {
        const visitor = `${g.visitor.city}`;
        const home = `${g.home.city}`;
        if (g.status === 'played' || g.status === 'completed') {
            const winner = g.visitor.score > g.home.score ? 'visitor' : 'home';
            const vScore = g.visitor.score;
            const hScore = g.home.score;
            const vBold = winner === 'visitor' ? '**' : '';
            const hBold = winner === 'home' ? '**' : '';
            return `${g.date} | ${vBold}${visitor} ${vScore}${vBold} @ ${hBold}${home} ${hScore}${hBold}`;
        }
        return `${g.date} | ${visitor} @ ${home} (${g.status})`;
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
