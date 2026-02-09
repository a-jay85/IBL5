import type { Boxscore } from '../api/types.js';
import { createBaseEmbed } from './common.js';
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

    // Quarter scoring — compact variable-width format
    const vLine = `**${game.visitor.city}:** ${qs.q1.visitor} | ${qs.q2.visitor} | ${qs.q3.visitor} | ${qs.q4.visitor}${qs.ot.visitor > 0 ? ` | OT: ${qs.ot.visitor}` : ''} — **${game.visitor.score}**`;
    const hLine = `**${game.home.city}:** ${qs.q1.home} | ${qs.q2.home} | ${qs.q3.home} | ${qs.q4.home}${qs.ot.home > 0 ? ` | OT: ${qs.ot.home}` : ''} — **${game.home.score}**`;

    mainEmbed.addFields({
        name: 'Score by Quarter',
        value: vLine + '\n' + hLine,
    });

    // Team totals comparison
    const vt = vStats.totals;
    const ht = hStats.totals;
    mainEmbed.addFields(
        {
            name: game.visitor.city,
            value: [
                `FG: ${vt.fg_made}/${vt.fg_attempted}`,
                `3PT: ${vt.three_pt_made}/${vt.three_pt_attempted}`,
                `FT: ${vt.ft_made}/${vt.ft_attempted}`,
                `REB: ${vt.rebounds} | AST: ${vt.assists}`,
                `STL: ${vt.steals} | BLK: ${vt.blocks}`,
                `TO: ${vt.turnovers}`,
            ].join('\n'),
            inline: true,
        },
        {
            name: game.home.city,
            value: [
                `FG: ${ht.fg_made}/${ht.fg_attempted}`,
                `3PT: ${ht.three_pt_made}/${ht.three_pt_attempted}`,
                `FT: ${ht.ft_made}/${ht.ft_attempted}`,
                `REB: ${ht.rebounds} | AST: ${ht.assists}`,
                `STL: ${ht.steals} | BLK: ${ht.blocks}`,
                `TO: ${ht.turnovers}`,
            ].join('\n'),
            inline: true,
        },
    );

    const embeds: EmbedBuilder[] = [mainEmbed];

    // Player box score embeds (one per team)
    for (const side of ['visitor', 'home'] as const) {
        const teamData = side === 'visitor' ? box.visitor : box.home;
        const teamGame = side === 'visitor' ? game.visitor : game.home;
        const playerEmbed = createBaseEmbed()
            .setColor(0x1E90FF)
            .setTitle(`${teamGame.full_name} Box Score`);

        const lines = teamData.players.map(p => {
            return `**${p.name}** ${p.minutes} MIN\n${p.points} PTS | ${p.rebounds} REB | ${p.assists} AST | ${p.steals} STL | ${p.blocks} BLK | ${p.fg_made}-${p.fg_attempted} FG`;
        });

        const content = lines.join('\n');
        if (content.length <= 4096) {
            playerEmbed.setDescription(content);
        } else {
            const mid = Math.ceil(lines.length / 2);
            playerEmbed.addFields(
                { name: 'Players', value: lines.slice(0, mid).join('\n') },
                { name: '\u200B', value: lines.slice(mid).join('\n') },
            );
        }

        embeds.push(playerEmbed);
    }

    return embeds;
}
