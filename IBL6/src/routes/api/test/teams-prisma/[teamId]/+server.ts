// src/routes/api/test/teams-prisma/[teamId]/+server.ts
import { json, error } from '@sveltejs/kit';
import { PrismaClient } from '@prisma/client';
import { serializeBigInt } from '$lib/utils/utils';

const prisma = new PrismaClient();

export async function GET({ params }) {
	try {
		const teamId = parseInt(params.teamId);

		if (!teamId || isNaN(teamId)) {
			throw error(400, 'Invalid team ID');
		}

		console.log(`🔍 Fetching team ${teamId} using Prisma`);

		// Get single team using Prisma
		const team = await prisma.team.findUnique({
			where: {
				teamId: teamId
			},
			select: {
				teamId: true,
				city: true,
				name: true,
				color1: true,
				color2: true,
				arena: true,
				owner: true,
				ownerEmail: true,
				discord: true,
				fka: true,
				contractWins: true,
				contractLosses: true,
				contractCoach: true,
				chart: true
			}
		});

		if (!team) {
			throw error(404, 'Team not found');
		}

		console.log(`✅ Found team: ${team.city} ${team.name}`);

		// Serialize BigInt values before returning
		const serializedTeam = serializeBigInt(team);

		return json({
			success: true,
			team: serializedTeam,
			timestamp: new Date().toISOString()
		});
	} catch (err) {
		console.error('❌ Failed to fetch team with Prisma:', err);

		// Handle Prisma-specific errors
		if (err.code === 'P2025') {
			throw error(404, 'Team not found');
		}

		return json(
			{
				success: false,
				error: err.message,
				prismaError: err.code
			},
			{ status: err.status || 500 }
		);
	} finally {
		await prisma.$disconnect();
	}
}
