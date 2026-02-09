import { EmbedBuilder } from 'discord.js';

// IBL team colors for embed accents
const TEAM_COLORS: Record<string, number> = {
    'Atlanta': 0xC8102E,
    'Boston': 0x007A33,
    'Brooklyn': 0x000000,
    'Charlotte': 0x1D1160,
    'Chicago': 0xCE1141,
    'Cleveland': 0x860038,
    'Dallas': 0x00538C,
    'Denver': 0x0E2240,
    'Detroit': 0xC8102E,
    'Golden State': 0x1D428A,
    'Houston': 0xCE1141,
    'Indiana': 0x002D62,
    'LA Clippers': 0xC8102E,
    'LA Lakers': 0x552583,
    'Memphis': 0x5D76A9,
    'Miami': 0x98002E,
    'Milwaukee': 0x00471B,
    'Minnesota': 0x0C2340,
    'New Orleans': 0x0C2340,
    'New York': 0x006BB6,
    'Oklahoma City': 0x007AC1,
    'Orlando': 0x0077C0,
    'Philadelphia': 0x006BB6,
    'Phoenix': 0x1D1160,
    'Portland': 0xE03A3E,
    'Sacramento': 0x5A2D81,
    'San Antonio': 0xC4CED4,
    'Toronto': 0xCE1141,
    'Utah': 0x002B5C,
    'Washington': 0x002B5C,
};

const IBL_BLUE = 0x1E90FF;

export function getTeamColor(cityOrName: string): number {
    return TEAM_COLORS[cityOrName] ?? IBL_BLUE;
}

export function createBaseEmbed(): EmbedBuilder {
    return new EmbedBuilder()
        .setFooter({ text: 'IBL - iblhoops.net' })
        .setTimestamp();
}

export function formatStat(value: number | string | null, suffix = ''): string {
    if (value === null) return '-';
    return `${value}${suffix}`;
}

export function formatPercentage(value: number | string | null): string {
    if (value === null) return '-';
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return `${(num * 100).toFixed(1)}%`;
}

export function formatRecord(record: string | null): string {
    return record ?? '-';
}

export function errorEmbed(message: string): EmbedBuilder {
    return createBaseEmbed()
        .setColor(0xFF0000)
        .setTitle('Error')
        .setDescription(message);
}

/**
 * Pad a string to a fixed width for monospace table formatting
 */
export function pad(str: string, width: number, align: 'left' | 'right' = 'left'): string {
    const truncated = str.length > width ? str.substring(0, width) : str;
    if (align === 'right') {
        return truncated.padStart(width);
    }
    return truncated.padEnd(width);
}
