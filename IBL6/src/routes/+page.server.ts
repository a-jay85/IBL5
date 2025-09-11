import type { PageServerLoad } from './$types';
import { PrismaClient } from '@prisma/client';
import { error } from '@sveltejs/kit';

const prisma = new PrismaClient();

// ✅ Add serialization function
function serializePrismaData<T>(data: T): T {
	if (data === null || data === undefined) {
		return data;
	}

	if (typeof data === 'bigint') {
		return Number(data) as T;
	}

	if (data instanceof Date) {
		return data.toISOString() as T;
	}

	if (Array.isArray(data)) {
		return data.map((item) => serializePrismaData(item)) as T;
	}

	if (typeof data === 'object') {
		const serialized: any = {};
		for (const [key, value] of Object.entries(data)) {
			serialized[key] = serializePrismaData(value);
		}
		return serialized as T;
	}

	return data;
}

export const load: PageServerLoad = async () => {
	try {
		console.log('🔍 Environment check:');
		console.log('DATABASE_URL exists:', !!process.env.DATABASE_URL);
		console.log('DATABASE_URL starts with:', process.env.DATABASE_URL?.substring(0, 20));

		// ✅ Test database connection
		const connectionTest = await prisma.$queryRaw`SELECT 1 as test`;
		console.log('✅ Database connection successful');

		// ✅ Load games with proper error handling
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
			take: 10
		});
		console.log(`📊 Found ${games.length} recent games`);

		// ✅ Load teams
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

		// ✅ Load players with pagination (limit to avoid large payload)
		console.log('🏃 Fetching players...');
		const ibl_players = await prisma.iblPlayer.findMany({
			select: {
				pid: true,
				name: true,
				teamId: true,
				pos: true
			},
			take: 100, // ✅ Limit to avoid large payload
			orderBy: {
				name: 'asc'
			}
		});
		console.log(`👨‍💼 Found ${ibl_players.length} players`);

		// ✅ Serialize data to handle BigInt and Date objects
		return {
			games: serializePrismaData(games),
			teams: serializePrismaData(teams),
			ibl_players: serializePrismaData(ibl_players)
		};
	} catch (err: any) {
		console.error('❌ Error fetching homepage data:', err);
		console.error('❌ Error details:', err.message);

		// ✅ Throw proper SvelteKit error instead of silently returning empty arrays
		throw error(500, `Failed to load homepage data: ${err.message}`);
	} finally {
		// ✅ Always disconnect Prisma client
		await prisma.$disconnect().catch(console.error);
	}
};
