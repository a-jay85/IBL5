import { db } from '$lib/firebase/firebase';
import { doc, getDoc } from 'firebase/firestore';
import type { PageServerLoad } from './$types';
import { error } from '@sveltejs/kit';

export const load: PageServerLoad = async ({ params }) => {
	try {
		const gameDoc = await getDoc(doc(db, 'games', params.gameId));

		if (!gameDoc.exists()) {
			throw error(404, 'Game not found');
		}

		return {
			game: {
				id: gameDoc.id,
				...gameDoc.data()
			}
		};
	} catch (err) {
		console.error('Error loading game:', err);
		throw error(500, 'Error loading game data');
	}
};
