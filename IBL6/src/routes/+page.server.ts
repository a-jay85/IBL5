import type { PageServerLoad } from './$types';
import { query } from '$lib/database/connection';
import { error } from '@sveltejs/kit';
import { serializeData } from '$lib/utils/utils';

export const load: PageServerLoad = async () => {
	try {
		const games = await query(`
			SELECT g.game_date as date, g.game_of_that_day,
				away.team_city AS away_city, away.team_name AS away_name,
				home.team_city AS home_city, home.team_name AS home_name
			FROM ibl_box_scores_teams g
			LEFT JOIN ibl_team_info away ON g.visitor_teamid = away.teamid
			LEFT JOIN ibl_team_info home ON g.home_teamid = home.teamid
			ORDER BY g.game_date DESC
			LIMIT 25
		`);

		const formattedGames = games.map((g) => ({
			date: g.date,
			gameOfThatDay: g.game_of_that_day,
			awayTeam: { city: g.away_city, name: g.away_name },
			homeTeam: { city: g.home_city, name: g.home_name }
		}));

		return {
			games: serializeData(formattedGames)
		};
	} catch (err: unknown) {
		const message = err instanceof Error ? err.message : String(err);
		console.error('Error fetching homepage data:', message);
		throw error(500, `Failed to load homepage data: ${message}`);
	}
};
