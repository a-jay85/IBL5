import type { Game, SeasonInfo } from '../api/types.js';
import { createBaseEmbed, IBL_BLUE, getTeamColor, boxScoreUrl, teamUrl, scheduleUrl } from './common.js';

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
        return `${boxLink} | ${vBold}${g.visitor.name} ${g.visitor.score}${vBold} @ ${hBold}${g.home.name} ${g.home.score}${hBold}`;
    }

    const visitorLink = `[${g.visitor.name}](${teamUrl(g.visitor.team_id)})`;
    const homeLink = `[${g.home.name}](${teamUrl(g.home.team_id)})`;
    return `${shortDate} | ${visitorLink} @ ${homeLink}`;
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
    const oppLink = `[${opponent.name}](${teamUrl(opponent.team_id)})`;

    if (g.status === 'played' || g.status === 'completed') {
        const boxLink = `[${shortDate}](${boxScoreUrl(g.box_score_id)})`;
        const teamScore = isHome ? g.home.score : g.visitor.score;
        const oppScore = isHome ? g.visitor.score : g.home.score;
        const result = teamScore > oppScore ? 'W' : 'L';
        return `${boxLink} | ${prefix} ${oppLink} — **${result}** ${teamScore}-${oppScore}`;
    }

    return `${shortDate} | ${prefix} ${oppLink}`;
}

// --- Last Sim embeds ---

export function lastsimLeagueEmbed(games: Game[], season: SeasonInfo) {
    const embed = createBaseEmbed()
        .setColor(IBL_BLUE)
        .setTitle(`Last Sim Scores — ${season.phase} Sim #${season.last_sim.phase_sim_number}`);

    if (games.length === 0) {
        embed.setDescription('No games found for the last sim.');
        return embed;
    }

    embed.setDescription(games.map(g => formatLeagueLine(g)).join('\n'));
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

    embed.setDescription(games.map(g => formatTeamLine(g, teamName)).join('\n'));
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

    embed.setDescription(games.map(g => formatLeagueLine(g)).join('\n'));
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

    embed.setDescription(games.map(g => formatTeamLine(g, teamName)).join('\n'));
    return embed;
}
