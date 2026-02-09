import type { Game, SeasonInfo } from '../api/types.js';
import { createBaseEmbed, siteBase, IBL_BLUE, getTeamColor, boxScoreUrl, scheduleUrl } from './common.js';

const DESCRIPTION_LIMIT = 4096;

const TEAM_ABBREVIATIONS: Record<string, string> = {
    'Timberwolves': 'T-Wolves',
    'Trailblazers': 'Blazers',
    'Mavericks': 'Mavs',
};

function shortName(name: string): string {
    return TEAM_ABBREVIATIONS[name] ?? name;
}

/** Join game lines, truncating with a link to the full schedule if over Discord's limit. */
function joinLines(lines: string[], lastGameId: number): string {
    const moreLink = `[See full schedule](${siteBase}/modules.php?name=Schedule#game-${lastGameId})`;
    const reservedSuffix = `\n*…and 00 more — ${moreLink}*`;

    let result = '';
    let included = 0;
    for (const line of lines) {
        const next = included === 0 ? line : `${result}\n${line}`;
        if (next.length + reservedSuffix.length > DESCRIPTION_LIMIT) break;
        result = next;
        included++;
    }
    if (included < lines.length) {
        result += `\n*…and ${lines.length - included} more — ${moreLink}*`;
    }
    return result;
}

/**
 * Format a game line for league-wide views: "visitor @ home".
 * Played games show scores with the winner bolded and the date linked to the box score.
 * Scheduled games show team names linked to their team pages.
 */
function formatLeagueLine(g: Game): string {
    const shortDate = g.date.slice(5); // "MM-DD" from "YYYY-MM-DD"

    if (g.status === 'played' || g.status === 'completed') {
        const boxLink = `[${shortDate}](${boxScoreUrl(g.box_score_id)})`;
        const winner = g.visitor.score > g.home.score ? 'visitor' : 'home';
        const vBold = winner === 'visitor' ? '**' : '';
        const hBold = winner === 'home' ? '**' : '';
        return `${boxLink} | ${vBold}${shortName(g.visitor.name)} ${g.visitor.score}${vBold} @ ${hBold}${shortName(g.home.name)} ${g.home.score}${hBold}`;
    }

    return `${shortDate} | ${shortName(g.visitor.name)} @ ${shortName(g.home.name)}`;
}

/**
 * Format a game line for team-specific views: "vs/@ opponent".
 * Played games show scores with a W/L indicator and the date linked to the box score.
 * Scheduled games show the opponent name linked to their team page.
 */
function formatTeamLine(g: Game, teamFullName: string): string {
    const shortDate = g.date.slice(5);
    const isHome = g.home.full_name === teamFullName;
    const opponent = isHome ? g.visitor : g.home;
    const prefix = isHome ? 'vs' : '@';

    if (g.status === 'played' || g.status === 'completed') {
        const boxLink = `[${shortDate}](${boxScoreUrl(g.box_score_id)})`;
        const teamScore = isHome ? g.home.score : g.visitor.score;
        const oppScore = isHome ? g.visitor.score : g.home.score;
        const result = teamScore > oppScore ? 'W' : 'L';
        return `${boxLink} | ${prefix} ${shortName(opponent.name)} — **${result}** ${teamScore}-${oppScore}`;
    }

    return `${shortDate} | ${prefix} ${shortName(opponent.name)}`;
}

// --- Last Sim embeds ---

export function lastsimLeagueEmbed(games: Game[], season: SeasonInfo) {
    const embed = createBaseEmbed()
        .setColor(IBL_BLUE)
        .setTitle(`Last Sim — ${season.phase} Sim #${season.last_sim.phase_sim_number}`);

    if (games.length === 0) {
        embed.setDescription('No games found for the last sim.');
        return embed;
    }

    embed.setDescription(joinLines(games.map(g => formatLeagueLine(g)), games[games.length - 1].box_score_id));
    return embed;
}

export function lastsimTeamEmbed(games: Game[], season: SeasonInfo, teamName: string, teamId: number) {
    const embed = createBaseEmbed()
        .setColor(getTeamColor(teamName))
        .setTitle(`${teamName} — Last Sim`)
        .setURL(scheduleUrl(teamId));

    if (games.length === 0) {
        embed.setDescription('No games found for the last sim.');
        return embed;
    }

    embed.setDescription(joinLines(games.map(g => formatTeamLine(g, teamName)), games[games.length - 1].box_score_id));
    return embed;
}

// --- Next Sim embeds ---

export function nextsimLeagueEmbed(games: Game[], season: SeasonInfo) {
    const embed = createBaseEmbed()
        .setColor(IBL_BLUE)
        .setTitle(`Next Sim Schedule — ${season.phase}`);

    if (games.length === 0) {
        embed.setDescription('No games projected for the next sim.');
        return embed;
    }

    embed.setDescription(joinLines(games.map(g => formatLeagueLine(g)), games[games.length - 1].box_score_id));
    return embed;
}

export function nextsimTeamEmbed(games: Game[], teamName: string, teamId: number) {
    const embed = createBaseEmbed()
        .setColor(getTeamColor(teamName))
        .setTitle(`${teamName} — Next Sim Schedule`)
        .setURL(scheduleUrl(teamId));

    if (games.length === 0) {
        embed.setDescription('No games projected for the next sim.');
        return embed;
    }

    embed.setDescription(joinLines(games.map(g => formatTeamLine(g, teamName)), games[games.length - 1].box_score_id));
    return embed;
}
