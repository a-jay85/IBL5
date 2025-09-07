import type { PageServerLoad } from './$types';
import { getGameById } from '$lib/models/Game';
import { error } from '@sveltejs/kit';

export const load: PageServerLoad = async ({ params }) => {
	try {
		console.log('Loading game with ID:', params.gameId); // Debug log
		const game = await getGameById(params.gameId);

		if (!game) {
			console.log('Game not found:', params.gameId); // Debug log
			throw error(404, 'Game not found');
		}

		console.log('Game loaded successfully:', game); // Debug log
		return {
			game
		};
	} catch (err) {
		console.error('Error loading game:', err);
		throw error(500, 'Error loading game data');
	}
};
