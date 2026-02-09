import type { Player, TeamDetail } from '../api/types.js';
import { createBaseEmbed, getTeamColor, pad, teamUrl, discordProfileUrl, addMonospaceField } from './common.js';

export function rosterEmbed(team: TeamDetail, players: Player[]) {
    const embed = createBaseEmbed()
        .setColor(getTeamColor(team.city))
        .setTitle(`${team.full_name} Roster`)
        .setURL(teamUrl(team.team_id))
        .setDescription(`Owner: ${team.owner_discord_id !== null ? `[${team.owner}](${discordProfileUrl(team.owner_discord_id)})` : team.owner} | Arena: ${team.arena}`);

    if (players.length === 0) {
        embed.addFields({ name: 'Roster', value: 'No players on roster.' });
        return embed;
    }

    const header = `${pad('Name', 17)} ${pad('Pos', 3)} ${pad('Age', 3, 'right')} ${pad('Sal', 4, 'right')}`;
    const lines = players.map(p => {
        const name = pad(p.name, 17);
        const pos = pad(p.position, 3);
        const age = pad(String(p.age), 3, 'right');
        const sal = pad(String(p.contract.current_salary), 4, 'right');
        return `${name} ${pos} ${age} ${sal}`;
    });

    addMonospaceField(embed, 'Players', header, lines);

    return embed;
}
