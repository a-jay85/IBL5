import type { StandingsEntry } from '../api/types.js';
import { createBaseEmbed, pad } from './common.js';

export function standingsEmbed(entries: StandingsEntry[], conference?: string) {
    const title = conference
        ? `${conference} Conference Standings`
        : 'IBL Standings';

    // Group by conference
    const conferences = new Map<string, StandingsEntry[]>();
    for (const entry of entries) {
        const conf = entry.conference;
        if (!conferences.has(conf)) {
            conferences.set(conf, []);
        }
        conferences.get(conf)!.push(entry);
    }

    const embed = createBaseEmbed()
        .setColor(0x1E90FF)
        .setTitle(title);

    for (const [conf, teams] of conferences) {
        const header = `${pad('Team', 18)} ${pad('W-L', 7)} ${pad('Pct', 5, 'right')} ${pad('GB', 5, 'right')}`;
        const lines = teams.map(t => {
            const name = t.team.city.length > 16
                ? t.team.city.substring(0, 16)
                : t.team.city;
            const pct = t.win_percentage !== null ? t.win_percentage.toFixed(3) : '  -  ';
            const gb = t.games_back.conference ?? '-';
            const clinch = t.clinched.conference ? ' z' : t.clinched.division ? ' y' : t.clinched.playoffs ? ' x' : '';
            return `${pad(name + clinch, 18)} ${pad(t.record.league, 7)} ${pad(pct, 5, 'right')} ${pad(gb, 5, 'right')}`;
        });

        embed.addFields({
            name: conf,
            value: '```\n' + header + '\n' + lines.join('\n') + '\n```',
            inline: false,
        });
    }

    return embed;
}
