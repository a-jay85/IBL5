import type { PageServerLoad } from './$types';
import { getAllGames, type Game } from '$lib/models/Game';

export const load: PageServerLoad = async () => {
	try {
		const games: Game[] = await getAllGames();
		return {
			games
		};
	} catch (error) {
		console.error('Error fetching games:', error);
		return {
			games: []
		};
	}
};
