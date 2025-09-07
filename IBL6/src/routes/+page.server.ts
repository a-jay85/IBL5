import { db } from '$lib/firebase/firebase';
import { collection, getDocs } from 'firebase/firestore';
import type { PageServerLoad } from './$types';

export interface Game {
	id: string;
	homeTeam: string;
	awayTeam: string;
	homeScore: number;
	awayScore: number;
}

export const load: PageServerLoad = async () => {
	try {
		const gamesSnapshot = await getDocs(collection(db, 'games'));
		const games: Game[] = gamesSnapshot.docs.map((doc) => {
			const data = doc.data();
			return {
				id: doc.id,
				homeTeam: data.homeTeam,
				awayTeam: data.awayTeam,
				homeScore: data.homeScore,
				awayScore: data.awayScore
			};
		});

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
