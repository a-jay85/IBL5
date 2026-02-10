import { EmbedBuilder, type ChatInputCommandInteraction } from 'discord.js';
import { config } from '../config.js';

export const siteBase = config.api.baseUrl.replace(/\/api\/v1$/, '');

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

export const IBL_BLUE = 0x1E90FF;

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

export function formatWinPct(value: number | string | null, fallback = '-'): string {
    if (value === null) return fallback;
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return num.toFixed(3);
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
 * Build a URL to a player's page on the site.
 */
export function playerUrl(pid: number): string {
    return `${siteBase}/modules.php?name=Player&pa=showpage&pid=${pid}`;
}

/**
 * Build a URL to a team's page on the site.
 */
export function teamUrl(teamId: number): string {
    return `${siteBase}/modules.php?name=Team&op=team&teamID=${teamId}`;
}

/**
 * Build a URL to a team's schedule page.
 */
export function scheduleUrl(teamId: number): string {
    return `${siteBase}/modules.php?name=Schedule&teamID=${teamId}`;
}

/**
 * Build a URL to a team's draft history page.
 */
export function draftHistoryUrl(teamId: number): string {
    return `${siteBase}/modules.php?name=DraftHistory&teamID=${teamId}`;
}

/**
 * Build a URL to a team's historical page for a specific season.
 */
export function teamYearUrl(teamId: number, year: number): string {
    return `${siteBase}/modules.php?name=Team&op=team&teamID=${teamId}&yr=${year}`;
}

/**
 * Build a URL to a box score page on the IBL6 SvelteKit site.
 */
export function boxScoreUrl(date: string, gameOfThatDay: number): string {
    if (gameOfThatDay <= 0) {
        return '';
    }
    return `${config.ibl6BaseUrl}/${date}-game-${gameOfThatDay}/boxscore`;
}

/**
 * Build a URL to a Discord user's profile.
 */
export function discordProfileUrl(discordId: number): string {
    return `https://discord.com/users/${discordId}`;
}

/**
 * Check if a value looks like a UUID (came from autocomplete).
 */
export function isUuid(value: string): boolean {
    return value.includes('-') && value.length > 20;
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

/**
 * Set embed description if content fits, otherwise split into two fields.
 * Discord description limit is 4096 chars; this splits at the midpoint of lines.
 */
export function setDescriptionOrSplit(embed: EmbedBuilder, lines: string[], fieldName: string): void {
    const content = lines.join('\n');

    if (content.length <= 4096) {
        embed.setDescription(content);
    } else {
        const mid = Math.ceil(lines.length / 2);
        embed.addFields(
            { name: fieldName, value: lines.slice(0, mid).join('\n') },
            { name: '\u200B', value: lines.slice(mid).join('\n') },
        );
    }
}

/**
 * Add a monospace code-block field, splitting into two fields if it exceeds
 * Discord's 1024-char field limit.
 */
export function addMonospaceField(embed: EmbedBuilder, fieldName: string, header: string, lines: string[]): void {
    const table = '```\n' + header + '\n' + lines.join('\n') + '\n```';

    if (table.length <= 1024) {
        embed.addFields({ name: fieldName, value: table });
    } else {
        const mid = Math.ceil(lines.length / 2);
        const part1 = '```\n' + header + '\n' + lines.slice(0, mid).join('\n') + '\n```';
        const part2 = '```\n' + lines.slice(mid).join('\n') + '\n```';
        embed.addFields(
            { name: fieldName, value: part1 },
            { name: '\u200B', value: part2 },
        );
    }
}

/**
 * Standard error handler for command catch blocks.
 */
export async function handleCommandError(interaction: ChatInputCommandInteraction, error: unknown): Promise<void> {
    const message = error instanceof Error ? error.message : 'Unknown error';
    await interaction.editReply({ embeds: [errorEmbed(message)] });
}

/**
 * Validate that a value is a UUID (from autocomplete). Replies with an error
 * and returns false if invalid.
 */
export async function requireUuid(interaction: ChatInputCommandInteraction, value: string, entityType: string): Promise<boolean> {
    if (isUuid(value)) {
        return true;
    }
    await interaction.editReply({ embeds: [errorEmbed(`Please use autocomplete to select a ${entityType}.`)] });
    return false;
}
