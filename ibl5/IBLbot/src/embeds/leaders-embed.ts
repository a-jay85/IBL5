import type { Leader } from '../api/types.js';
import { createBaseEmbed, pad } from './common.js';

const CATEGORY_LABELS: Record<string, string> = {
    ppg: 'Points Per Game',
    rpg: 'Rebounds Per Game',
    apg: 'Assists Per Game',
    spg: 'Steals Per Game',
    bpg: 'Blocks Per Game',
    fgp: 'Field Goal %',
    ftp: 'Free Throw %',
    tgp: '3-Point %',
    qa: 'QA Rating',
};

function getStatValue(leader: Leader, category: string): number | string {
    const s = leader.stats;
    switch (category) {
        case 'ppg': return s.points_per_game;
        case 'rpg': return s.rebounds_per_game;
        case 'apg': return s.assists_per_game;
        case 'spg': return s.steals_per_game;
        case 'bpg': return s.blocks_per_game;
        case 'fgp': return s.fg_percentage;
        case 'ftp': return s.ft_percentage;
        case 'tgp': return s.three_pt_percentage;
        default: return s.points_per_game;
    }
}

export function leadersEmbed(leaders: Leader[], category: string) {
    const label = CATEGORY_LABELS[category] ?? category.toUpperCase();
    const isPercentage = ['fgp', 'ftp', 'tgp'].includes(category);

    const embed = createBaseEmbed()
        .setColor(0x1E90FF)
        .setTitle(`League Leaders - ${label}`);

    if (leaders.length === 0) {
        embed.setDescription('No leaders data available.');
        return embed;
    }

    const lines = leaders.map((l, i) => {
        const rank = `${i + 1}.`.padEnd(4);
        const name = pad(l.player.name, 20);
        const team = pad(l.team.name, 14);
        const value = getStatValue(l, category);
        const num = typeof value === 'string' ? parseFloat(value) : value;
        const stat = isPercentage ? `${(num * 100).toFixed(1)}%` : num.toFixed(1);
        return `${rank}${name} ${team} ${stat}`;
    });

    embed.setDescription('```\n' + lines.join('\n') + '\n```');
    return embed;
}
