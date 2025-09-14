import type { PageServerLoad } from './$types';
import { prisma } from '$lib/database/prisma';
import { error } from '@sveltejs/kit';
import { serializePrismaData } from '$lib/utils/utils';

export const load: PageServerLoad = async () => {
	try {
		// Load games with proper error handling
		console.log('🏀 Fetching recent games...');
		const games = await prisma.boxGame.findMany({
			include: {
				awayTeam: {
					select: {
						teamid: true,
						city: true,
						name: true,
						color1: true,
						color2: true
					}
				},
				homeTeam: {
					select: {
						teamid: true,
						city: true,
						name: true,
						color1: true,
						color2: true
					}
				}
			},
			orderBy: {
				date: 'desc'
			},
			take: 25
		});
		console.log(`📊 Found ${games.length} recent games`);

		// Load teams
		console.log('👥 Fetching teams...');
		const teams = await prisma.team.findMany({
			select: {
				teamid: true,
				city: true,
				name: true,
				color1: true,
				color2: true
			},
			orderBy: {
				name: 'asc'
			}
		});
		console.log(`🏆 Found ${teams.length} teams`);

		// Load players with pagination (limit to avoid large payload)
		console.log('🏃 Fetching players...');
		const ibl_players = await prisma.iblPlayer.findMany({
			select: {
				pid: true,
				name: true,
				teamId: true,
				pos: true
			},
			take: 100, // Limit to avoid large payload
			orderBy: {
				name: 'asc'
			}
		});
		console.log(`👨‍💼 Found ${ibl_players.length} players`);

		// Serialize data to handle BigInt and Date objects
		return {
			games: serializePrismaData(games),
			teams: serializePrismaData(teams),
			ibl_players: serializePrismaData(ibl_players)
		};
	} catch (err: any) {
		console.error('❌ Error fetching homepage data:', err);
		console.error('❌ Error details:', err.message);

		// Throw proper SvelteKit error instead of silently returning empty arrays
		throw error(500, `Failed to load homepage data: ${err.message}`);
	}
};
