import type { PageServerLoad } from './$types';
import { query } from '$lib/database/connection';
import { serializeData } from '$lib/utils/utils';
import { error } from '@sveltejs/kit';

export const load: PageServerLoad = async ({ params }) => {
	try {
		// Parse game slug
		const parts = params.gameSlug.split('-');
		const year = parseInt(parts[0]);
		const month = parseInt(parts[1]);
		const day = parseInt(parts[2]);
		const gameNumber = parseInt(parts[4]) || 1;

		const gameDate = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

		// Get game info
		const gameInfo = await query(
			`SELECT DISTINCT
				game.game_date,
				game.visitor_teamid as awayTeamId,
				game.home_teamid as homeTeamId,
				game.game_of_that_day,
				(game.visitor_q1_points + game.visitor_q2_points + game.visitor_q3_points + game.visitor_q4_points + COALESCE(game.visitor_ot_points, 0)) as awayScore,
				(game.home_q1_points + game.home_q2_points + game.home_q3_points + game.home_q4_points + COALESCE(game.home_ot_points, 0)) as homeScore,
				away.teamid as away_teamid,
				away.team_city as away_city,
				away.team_name as away_name,
				away.color1 as away_color1,
				away.color2 as away_color2,
				home.teamid as home_teamid,
				home.team_city as home_city,
				home.team_name as home_name,
				home.color1 as home_color1,
				home.color2 as home_color2
			FROM ibl_box_scores_teams game
			LEFT JOIN ibl_team_info home ON game.home_teamid = home.teamid
			LEFT JOIN ibl_team_info away ON game.visitor_teamid = away.teamid
			WHERE DATE(game.game_date) = ?
			AND game.game_of_that_day = ?
			LIMIT 1`,
			[gameDate, gameNumber]
		);

		if (!gameInfo || gameInfo.length === 0) {
			throw error(404, `Game not found`);
		}

		const gameData = gameInfo[0];

		// Get players using the recorded teamID (not the player's current team)
		const players = await query(
			`SELECT
				bp.game_date,
				COALESCE(plr.name, bp.name) as name,
				bp.pos,
				bp.pid,
				bp.teamid as playerTeamId,
				CASE
					WHEN bp.teamid = ? THEN 1
					ELSE 0
				END as isAwayPlayer,
				bp.game_min as min,
				bp.calc_fg_made as fgm,
				(bp.game_2ga + bp.game_3ga) as fga,
				bp.game_ftm as ftm,
				bp.game_fta as fta,
				bp.game_3gm as tpm,
				bp.game_3ga as tpa,
				bp.game_orb as orb,
				bp.game_drb as drb,
				bp.game_ast as ast,
				bp.game_stl as stl,
				bp.game_tov as tov,
				bp.game_blk as blk,
				bp.game_pf as pf,
				bp.calc_rebounds as reb,
				bp.calc_points as pts
			FROM ibl_box_scores bp
			LEFT JOIN ibl_plr plr ON bp.pid = plr.pid
			WHERE DATE(bp.game_date) = ?
			AND bp.game_of_that_day = ?
			AND bp.teamid IN (?, ?)
			ORDER BY bp.teamid = ? DESC, bp.game_min DESC`,
			[
				gameData.awayTeamId,
				gameDate,
				gameNumber,
				gameData.awayTeamId,
				gameData.homeTeamId,
				gameData.awayTeamId
			]
		);

		// Separate players by team
		const awayPlayers = players.filter((p) => Number(p.isAwayPlayer) === 1);
		const homePlayers = players.filter((p) => Number(p.isAwayPlayer) === 0);

		// Transform game data
		const formattedGame = {
			date: gameData.game_date,
			gameOfThatDay: gameData.game_of_that_day,
			awayScore: Number(gameData.awayScore) || 0,
			homeScore: Number(gameData.homeScore) || 0,
			awayTeamId: gameData.awayTeamId,
			homeTeamId: gameData.homeTeamId,
			awayTeam: gameData.away_teamid
				? {
						teamid: gameData.away_teamid,
						city: gameData.away_city,
						name: gameData.away_name,
						abbreviation: null,
						color1: gameData.away_color1,
						color2: gameData.away_color2
					}
				: null,
			homeTeam: gameData.home_teamid
				? {
						teamid: gameData.home_teamid,
						city: gameData.home_city,
						name: gameData.home_name,
						abbreviation: null,
						color1: gameData.home_color1,
						color2: gameData.home_color2
					}
				: null
		};

		// Transform player data
		const formatPlayers = (playerList: typeof players) => {
			return playerList.map((player) => ({
				id: player.pid,
				pos: player.pos || 'N/A',
				name: player.name || 'Unknown',
				teamId: Number(player.playerTeamId),
				min: Number(player.min) || 0,
				fgm: Number(player.fgm) || 0,
				fga: Number(player.fga) || 0,
				ftm: Number(player.ftm) || 0,
				fta: Number(player.fta) || 0,
				'3pm': Number(player.tpm) || 0,
				'3pa': Number(player.tpa) || 0,
				pts: Number(player.pts) || 0,
				orb: Number(player.orb) || 0,
				reb: Number(player.reb) || 0,
				ast: Number(player.ast) || 0,
				stl: Number(player.stl) || 0,
				blk: Number(player.blk) || 0,
				tov: Number(player.tov) || 0,
				pf: Number(player.pf) || 0
			}));
		};

		return {
			game: serializeData(formattedGame),
			awayPlayers: serializeData(formatPlayers(awayPlayers)),
			homePlayers: serializeData(formatPlayers(homePlayers))
		};
	} catch (err: unknown) {
		if (err && typeof err === 'object' && 'status' in err) {
			throw err;
		}
		const message = err instanceof Error ? err.message : String(err);
		console.error('Error loading game:', message);
		throw error(500, 'Error loading game data');
	}
};
