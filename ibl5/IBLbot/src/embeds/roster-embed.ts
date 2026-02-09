import type { Player, TeamDetail } from '../api/types.js';
import { createBaseEmbed, getTeamColor, pad, formatStat } from './common.js';

export function rosterEmbed(team: TeamDetail, players: Player[]) {
    const embed = createBaseEmbed()
        .setColor(getTeamColor(team.city))
        .setTitle(`${team.full_name} Roster`)
        .setDescription(`Owner: ${team.owner} | Arena: ${team.arena}`);

    if (players.length === 0) {
        embed.addFields({ name: 'Roster', value: 'No players on roster.' });
        return embed;
    }

    const header = `${pad('Name', 20)} ${pad('Pos', 3)} ${pad('Age', 3, 'right')} ${pad('PPG', 5, 'right')} ${pad('Sal', 5, 'right')}`;
    const lines = players.map(p => {
        const name = pad(p.name, 20);
        const pos = pad(p.position, 3);
        const age = pad(String(p.age), 3, 'right');
        const ppg = pad(formatStat(p.stats.points_per_game), 5, 'right');
        const sal = pad(`$${p.contract.current_salary}K`, 5, 'right');
        return `${name} ${pos} ${age} ${ppg} ${sal}`;
    });

    const table = '```\n' + header + '\n' + lines.join('\n') + '\n```';

    // Discord has a 1024 char limit per field value
    if (table.length <= 1024) {
        embed.addFields({ name: 'Players', value: table });
    } else {
        // Split into two fields if needed
        const mid = Math.ceil(lines.length / 2);
        const part1 = '```\n' + header + '\n' + lines.slice(0, mid).join('\n') + '\n```';
        const part2 = '```\n' + lines.slice(mid).join('\n') + '\n```';
        embed.addFields(
            { name: 'Players', value: part1 },
            { name: '\u200B', value: part2 },
        );
    }

    return embed;
}
