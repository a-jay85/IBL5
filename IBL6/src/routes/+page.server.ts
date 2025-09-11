import type { PageServerLoad } from './$types';
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

export const load: PageServerLoad = async () => {
	try {
		const games = await prisma.boxGame.findMany({
			include: {
				awayTeam: true,
				homeTeam: true
			},
			orderBy: {
				date: 'desc'
			},
			take: 10
		});

		const teams = await prisma.team.findMany();
		const ibl_players = await prisma.iblPlayer.findMany();

		return {
			games,
			teams,
			ibl_players
		};
	} catch (error) {
		console.error('Error fetching games:', error);
		return {
			games: [],
			teams: [],
			ibl_players: []
		};
	}
};
