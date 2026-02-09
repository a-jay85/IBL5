import type { PlayerDetail } from '../api/types.js';
import { createBaseEmbed, getTeamColor, formatStat, formatPercentage } from './common.js';

export function playerDetailEmbed(player: PlayerDetail) {
    const teamName = player.team ? player.team.full_name : 'Free Agent';
    const color = player.team ? getTeamColor(player.team.city) : 0x888888;

    const embed = createBaseEmbed()
        .setColor(color)
        .setTitle(player.name)
        .setDescription(`${player.position} | ${teamName}`)
        .addFields(
            {
                name: 'Bio',
                value: [
                    `Age: ${player.age}`,
                    `Height: ${player.height || '-'}`,
                    `Experience: ${player.experience} yr${player.experience !== 1 ? 's' : ''}`,
                ].join('\n'),
                inline: true,
            },
            {
                name: 'Contract',
                value: [
                    `Salary: ${player.contract.current_salary}`,
                    `Yr 1: ${player.contract.year1}`,
                    `Yr 2: ${player.contract.year2}`,
                    `Bird Rights: ${player.bird_rights} yr${player.bird_rights !== 1 ? 's' : ''}`,
                ].join('\n'),
                inline: true,
            },
            {
                name: '\u200B',
                value: '\u200B',
                inline: true,
            },
            {
                name: 'Scoring',
                value: [
                    `PPG: **${formatStat(player.stats.points_per_game)}**`,
                    `FG: ${player.stats.field_goals_made}/${player.stats.field_goals_attempted} (${formatPercentage(player.stats.fg_percentage)})`,
                    `FT: ${player.stats.free_throws_made}/${player.stats.free_throws_attempted} (${formatPercentage(player.stats.ft_percentage)})`,
                    `3PT: ${player.stats.three_pointers_made}/${player.stats.three_pointers_attempted} (${formatPercentage(player.stats.three_pt_percentage)})`,
                ].join('\n'),
                inline: true,
            },
            {
                name: 'Other Stats',
                value: [
                    `GP: ${player.stats.games_played} | MIN: ${player.stats.minutes_played}`,
                    `REB: ${player.stats.offensive_rebounds + player.stats.defensive_rebounds} (${player.stats.offensive_rebounds} OFF / ${player.stats.defensive_rebounds} DEF)`,
                    `AST: ${player.stats.assists} | STL: ${player.stats.steals}`,
                    `BLK: ${player.stats.blocks} | TO: ${player.stats.turnovers}`,
                    `PF: ${player.stats.personal_fouls}`,
                ].join('\n'),
                inline: true,
            },
        );

    return embed;
}
