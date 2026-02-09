import type { Boxscore } from '../api/types.js';
import { createBaseEmbed, pad } from './common.js';
import { EmbedBuilder } from 'discord.js';

export function boxscoreEmbeds(box: Boxscore): EmbedBuilder[] {
    const game = box.game;
    const vStats = box.visitor.team_stats;
    const hStats = box.home.team_stats;
    const qs = vStats.quarter_scoring;

    // Main embed with score and quarter breakdown
    const mainEmbed = createBaseEmbed()
        .setColor(0x1E90FF)
        .setTitle(`${game.visitor.full_name} @ ${game.home.full_name}`)
        .setDescription(`**${game.visitor.score} - ${game.home.score}** | ${game.date}`);

    // Quarter scoring table
    const qHeader = `${pad('', 12)} ${pad('Q1', 4, 'right')} ${pad('Q2', 4, 'right')} ${pad('Q3', 4, 'right')} ${pad('Q4', 4, 'right')} ${pad('OT', 4, 'right')} ${pad('TOT', 4, 'right')}`;
    const qVisitor = `${pad(game.visitor.city, 12)} ${pad(String(qs.q1.visitor), 4, 'right')} ${pad(String(qs.q2.visitor), 4, 'right')} ${pad(String(qs.q3.visitor), 4, 'right')} ${pad(String(qs.q4.visitor), 4, 'right')} ${pad(String(qs.ot.visitor), 4, 'right')} ${pad(String(game.visitor.score), 4, 'right')}`;
    const qHome = `${pad(game.home.city, 12)} ${pad(String(qs.q1.home), 4, 'right')} ${pad(String(qs.q2.home), 4, 'right')} ${pad(String(qs.q3.home), 4, 'right')} ${pad(String(qs.q4.home), 4, 'right')} ${pad(String(qs.ot.home), 4, 'right')} ${pad(String(game.home.score), 4, 'right')}`;

    mainEmbed.addFields({
        name: 'Score by Quarter',
        value: '```\n' + qHeader + '\n' + qVisitor + '\n' + qHome + '\n```',
    });

    // Team totals comparison
    const vt = vStats.totals;
    const ht = hStats.totals;
    const teamStats = [
        `FG:  ${vt.fg_made}/${vt.fg_attempted} vs ${ht.fg_made}/${ht.fg_attempted}`,
        `3PT: ${vt.three_pt_made}/${vt.three_pt_attempted} vs ${ht.three_pt_made}/${ht.three_pt_attempted}`,
        `FT:  ${vt.ft_made}/${vt.ft_attempted} vs ${ht.ft_made}/${ht.ft_attempted}`,
        `REB: ${vt.rebounds} vs ${ht.rebounds}`,
        `AST: ${vt.assists} vs ${ht.assists}`,
        `STL: ${vt.steals} vs ${ht.steals}`,
        `BLK: ${vt.blocks} vs ${ht.blocks}`,
        `TO:  ${vt.turnovers} vs ${ht.turnovers}`,
    ].join('\n');

    mainEmbed.addFields({
        name: `Team Stats (${game.visitor.city} vs ${game.home.city})`,
        value: '```\n' + teamStats + '\n```',
    });

    const embeds: EmbedBuilder[] = [mainEmbed];

    // Player box score embeds (one per team)
    for (const side of ['visitor', 'home'] as const) {
        const teamData = side === 'visitor' ? box.visitor : box.home;
        const teamGame = side === 'visitor' ? game.visitor : game.home;
        const playerEmbed = createBaseEmbed()
            .setColor(0x1E90FF)
            .setTitle(`${teamGame.full_name} Box Score`);

        const header = `${pad('Player', 16)} ${pad('MIN', 3, 'right')} ${pad('PTS', 3, 'right')} ${pad('REB', 3, 'right')} ${pad('AST', 3, 'right')} ${pad('STL', 2, 'right')} ${pad('BLK', 2, 'right')} ${pad('FG', 7, 'right')}`;
        const lines = teamData.players.map(p => {
            return `${pad(p.name, 16)} ${pad(String(p.minutes), 3, 'right')} ${pad(String(p.points), 3, 'right')} ${pad(String(p.rebounds), 3, 'right')} ${pad(String(p.assists), 3, 'right')} ${pad(String(p.steals), 2, 'right')} ${pad(String(p.blocks), 2, 'right')} ${pad(`${p.fg_made}-${p.fg_attempted}`, 7, 'right')}`;
        });

        const table = '```\n' + header + '\n' + lines.join('\n') + '\n```';
        if (table.length <= 1024) {
            playerEmbed.addFields({ name: 'Players', value: table });
        } else {
            const mid = Math.ceil(lines.length / 2);
            playerEmbed.addFields(
                { name: 'Players', value: '```\n' + header + '\n' + lines.slice(0, mid).join('\n') + '\n```' },
                { name: '\u200B', value: '```\n' + lines.slice(mid).join('\n') + '\n```' },
            );
        }

        embeds.push(playerEmbed);
    }

    return embeds;
}
