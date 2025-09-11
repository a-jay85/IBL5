import type { PageServerLoad } from './$types';
import { PrismaClient } from '@prisma/client';
import { serializePrismaData } from '$lib/utils/utils';
import { error } from '@sveltejs/kit';

const prisma = new PrismaClient();

export const load: PageServerLoad = async ({ params }) => {
	try {
		console.log(`🔍 Loading game with slug: ${params.gameSlug}`);

		// Parse game slug
		const parts = params.gameSlug.split('-');
		const year = parseInt(parts[0]);
		const month = parseInt(parts[1]);
		const day = parseInt(parts[2]);
		const gameNumber = parseInt(parts[4]) || 1;

		const gameDate = new Date(year, month - 1, day);

		// ✅ Get game data
		const game = (await prisma.$queryRaw`
            SELECT 
                g.Date,
                g.gameOfThatDay,
                g.visitorTeamID as awayTeamId,
                g.homeTeamID as homeTeamId,
                (g.visitorQ1points + g.visitorQ2points + g.visitorQ3points + g.visitorQ4points + COALESCE(g.visitorOTpoints, 0)) as awayScore,
                (g.homeQ1points + g.homeQ2points + g.homeQ3points + g.homeQ4points + COALESCE(g.homeOTpoints, 0)) as homeScore,
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
            FROM ibl_box_scores_teams g
            LEFT JOIN ibl_team_info away ON g.visitorTeamID = away.teamid
            LEFT JOIN ibl_team_info home ON g.homeTeamID = home.teamid
            WHERE DATE(g.Date) = DATE(${gameDate})
            AND g.gameOfThatDay = ${gameNumber}
            LIMIT 1
        `) as any[];

		if (!game || game.length === 0) {
			throw error(404, `Game not found`);
		}

		const gameData = game[0];
		console.log('🏀 Game teams:', {
			away: `${gameData.away_city} ${gameData.away_name} (ID: ${gameData.awayTeamId})`,
			home: `${gameData.home_city} ${gameData.home_name} (ID: ${gameData.homeTeamId})`
		});

		// ✅ Get ALL players for this game with proper team identification
		const players = (await prisma.$queryRaw`
            SELECT 
                bp.Date,
                bp.name,
                bp.pos,
                bp.pid,
                bp.visitorTID as awayTeamId,
                bp.homeTID as homeTeamId,
                -- ✅ Determine which team this player belongs to
                CASE 
                    WHEN bp.visitorTID IS NOT NULL THEN bp.visitorTID
                    WHEN bp.homeTID IS NOT NULL THEN bp.homeTID
                    ELSE NULL 
                END as playerTeamId,
                -- ✅ Flag if this is an away team player
                CASE 
                    WHEN bp.visitorTID = ${gameData.awayTeamId} THEN 1
                    ELSE 0
                END as isAwayPlayer,
                bp.gameMIN as min,
                bp.gameFGM as fgm,
                bp.gameFGA as fga,
                bp.gameFTM as ftm,
                bp.gameFTA as fta,
                bp.game3GM as tpm,
                bp.game3GA as tpa,
                bp.gameORB as orb,
                bp.gameDRB as drb,
                bp.gameAST as ast,
                bp.gameSTL as stl,
                bp.gameTOV as tov,
                bp.gameBLK as blk,
                bp.gamePF as pf,
                -- Calculate derived stats
                (bp.gameORB + bp.gameDRB) as reb,
                (bp.gameFGM * 2 + bp.game3GM + bp.gameFTM) as pts
            FROM ibl_box_scores bp
            WHERE DATE(bp.Date) = DATE(${gameDate})
            AND (bp.visitorTID = ${gameData.awayTeamId} OR bp.homeTID = ${gameData.homeTeamId})
            ORDER BY bp.visitorTID DESC, bp.gameMIN DESC
        `) as any[];

		console.log(`✅ Found ${players.length} total players for this game`);

		// ✅ Separate players by team using the correct logic
		const awayPlayers = players.filter((p: any) => p.isAwayPlayer === 1);
		const homePlayers = players.filter((p: any) => p.isAwayPlayer === 0);

		console.log(`👥 Away players: ${awayPlayers.length}, Home players: ${homePlayers.length}`);

		// ✅ Debug first few players
		if (players.length > 0) {
			console.log(
				'🔍 First 3 players:',
				players.slice(0, 3).map((p) => ({
					name: p.name,
					awayTeamId: p.awayTeamId,
					homeTeamId: p.homeTeamId,
					playerTeamId: p.playerTeamId,
					isAwayPlayer: p.isAwayPlayer
				}))
			);
		}

		// Transform game data
		const formattedGame = {
			date: gameData.Date,
			gameOfThatDay: gameData.gameOfThatDay,
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

		// ✅ Transform player data
		const formatPlayers = (playerList: any[]) => {
			return playerList.map((player: any) => ({
				id: player.pid,
				pos: player.pos || 'N/A',
				name: player.name || 'Unknown',
				teamId: Number(player.playerTeamId), // ✅ Add team ID for debugging
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
			game: serializePrismaData(formattedGame),
			awayPlayers: serializePrismaData(formatPlayers(awayPlayers)),
			homePlayers: serializePrismaData(formatPlayers(homePlayers))
		};
	} catch (err: any) {
		console.error('Error loading game:', err);
		if (err.status) {
			throw err;
		}
		throw error(500, 'Error loading game data');
	} finally {
		await prisma.$disconnect().catch(console.error);
	}
};
