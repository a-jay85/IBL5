import { db } from '$lib/firebase/firebase';
import { doc, setDoc } from 'firebase/firestore';
import { json, error } from '@sveltejs/kit';
import { dev } from '$app/environment';
import { getAllIblPlayers } from '$lib/models/IblPlayer';

export async function POST({ request }) {
	if (!dev) {
		throw error(403, 'This endpoint is only available in development');
	}

	// Check for API key authentication
	const apiKey = request.headers.get('x-api-key');
	if (apiKey !== process.env.SEED_API_KEY) {
		throw error(401, 'Unauthorized - Invalid API key');
	}

	try {
		if (!db) {
			throw error(500, 'Database connection not available');
		}

		const teams = [
			{
				id: 'lakers',
				name: 'Lakers',
				city: 'Los Angeles',
				abbreviation: 'LAL',
				players: ['player-001', 'player-002', 'player-003', 'player-004', 'player-005']
			},
			{
				id: 'bulls',
				name: 'Bulls',
				city: 'Chicago',
				abbreviation: 'CHI',
				players: ['player-006', 'player-007', 'player-008', 'player-009', 'player-010']
			},
			{
				id: 'warriors',
				name: 'Warriors',
				city: 'Golden State',
				abbreviation: 'GSW',
				players: ['player-011', 'player-012', 'player-013', 'player-014', 'player-015']
			},
			{
				id: 'celtics',
				name: 'Celtics',
				city: 'Boston',
				abbreviation: 'BOS',
				players: ['player-016', 'player-017', 'player-018', 'player-019', 'player-020']
			}
		];

		// Alternative: Distribute existing players among teams
		const players = await getAllIblPlayers();
		const playersPerTeam = Math.ceil(players.length / teams.length);

		teams.forEach((team, index) => {
			const startIndex = index * playersPerTeam;
			const endIndex = startIndex + playersPerTeam;
			team.players = players
				.slice(startIndex, endIndex)
				.map((p) => p.id)
				.filter((id): id is string => id !== undefined);
		});

		for (const team of teams) {
			await setDoc(doc(db, 'teams', team.id), {
				name: team.name,
				city: team.city,
				abbreviation: team.abbreviation,
				players: team.players
			});
		}

		return json({
			success: true,
			message: `Seeded ${teams.length} teams successfully`,
			teams: teams.length
		});
	} catch (err) {
		console.error('Error seeding teams:', err);
		throw error(500, 'Failed to seed teams');
	}
}
