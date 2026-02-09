import { type EmbedBuilder } from 'discord.js';
import type { StandingsEntry } from '../api/types.js';
import { addMonospaceField, createBaseEmbed, formatWinPct, IBL_BLUE, pad } from './common.js';

const HEADER = `${pad('Team', 13)} ${pad('W-L', 5)} ${pad('Pct', 5, 'right')} ${pad('GB', 4, 'right')}`;

function formatLine(t: StandingsEntry, gbKey: 'conference' | 'division'): string {
    const name = t.team.name.length > 16
        ? t.team.name.substring(0, 16)
        : t.team.name;
    const pct = formatWinPct(t.win_percentage, '  -  ');
    const gb = t.games_back[gbKey] ?? '-';
    const clinch = t.clinched.conference ? ' z' : t.clinched.division ? ' y' : t.clinched.playoffs ? ' x' : '';
    return `${pad(name + clinch, 13)} ${pad(t.record.league, 5)} ${pad(pct, 5, 'right')} ${pad(gb, 4, 'right')}`;
}

function addGroup(embed: EmbedBuilder, name: string, teams: StandingsEntry[], gbKey: 'conference' | 'division'): void {
    const lines = teams.map(t => formatLine(t, gbKey));
    addMonospaceField(embed, name, HEADER, lines);
}

const DIVISIONS = ['Atlantic', 'Central', 'Midwest', 'Pacific'];

export function standingsEmbed(entries: StandingsEntry[], view: string) {
    const isAllDivisions = view === 'All Divisions';
    const isSingleDivision = DIVISIONS.includes(view);

    const title = isAllDivisions
        ? 'IBL Division Standings'
        : isSingleDivision
            ? `${view} Division Standings`
            : view === 'Eastern' || view === 'Western'
                ? `${view} Conference Standings`
                : 'IBL Standings';

    const embed = createBaseEmbed()
        .setColor(IBL_BLUE)
        .setTitle(title);

    if (isSingleDivision) {
        const divTeams = entries.filter(e => e.division === view);
        addGroup(embed, view, divTeams, 'division');
    } else if (isAllDivisions) {
        const divisions = new Map<string, StandingsEntry[]>();
        for (const entry of entries) {
            if (!divisions.has(entry.division)) {
                divisions.set(entry.division, []);
            }
            divisions.get(entry.division)!.push(entry);
        }

        for (const [div, teams] of divisions) {
            addGroup(embed, div, teams, 'division');
        }
    } else {
        const conferences = new Map<string, StandingsEntry[]>();
        for (const entry of entries) {
            if (!conferences.has(entry.conference)) {
                conferences.set(entry.conference, []);
            }
            conferences.get(entry.conference)!.push(entry);
        }

        for (const [conf, teams] of conferences) {
            addGroup(embed, conf, teams, 'conference');
        }
    }

    return embed;
}
