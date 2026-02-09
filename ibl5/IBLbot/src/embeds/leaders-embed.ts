import type { Leader } from '../api/types.js';
import { createBaseEmbed, IBL_BLUE, playerUrl, teamYearUrl } from './common.js';

const CATEGORY_LABELS: Record<string, string> = {
    ppg: 'Points',
    rpg: 'Rebounds',
    apg: 'Assists',
    spg: 'Steals',
    bpg: 'Blocks',
    fgp: 'Field Goal %',
    ftp: 'Free Throw %',
    tgp: '3-Point %',
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
        .setColor(IBL_BLUE)
        .setTitle(`Top 10 Season Averages - ${label}`);

    if (leaders.length === 0) {
        embed.setDescription('No leaders data available.');
        return embed;
    }

    const lines = leaders.map((l, i) => {
        const value = getStatValue(l, category);
        const num = typeof value === 'string' ? parseFloat(value) : value;
        const stat = isPercentage ? `${(num * 100).toFixed(1)}%` : num.toFixed(1);
        const yr = String(l.season).slice(-2);
        return `${i + 1}. **[${l.player.name}](${playerUrl(l.player.pid)})** ([\'${yr} ${l.team.name}](${teamYearUrl(l.team.team_id, l.season)})) — **${stat}**`;
    });

    embed.setDescription(lines.join('\n'));
    return embed;
}
