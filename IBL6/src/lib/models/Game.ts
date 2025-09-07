import { db } from '$lib/firebase/firebase';
import {
	collection,
	doc,
	addDoc,
	getDoc,
	getDocs,
	query,
	orderBy,
	limit
} from 'firebase/firestore';

export interface Game {
	id: string;
	homeTeamId: string;
	awayTeamId: string;
	homeScore: number;
	awayScore: number;
	cd: number; // Unix epoch timestamp (creation date)
	status?: 'scheduled' | 'live' | 'completed';
	season?: string;
}

export async function addGame(data: Omit<Game, 'id' | 'cd'>): Promise<string> {
	const gameData = {
		...data,
		cd: Math.floor(Date.now() / 1000) // Current Unix timestamp
	};
	const docRef = await addDoc(collection(db, 'games'), gameData);
	return docRef.id;
}

export async function getGameById(id: string): Promise<Game | null> {
	const docRef = doc(db, 'games', id);
	const docSnap = await getDoc(docRef);

	if (docSnap.exists()) {
		const data = docSnap.data();
		return {
			id: docSnap.id,
			homeTeamId: data.homeTeamId || '',
			awayTeamId: data.awayTeamId || '',
			homeScore: data.homeScore || 0,
			awayScore: data.awayScore || 0,
			...data
		} as Game;
	}

	return null;
}

export async function getGamesByTeam(teamId: string): Promise<Game[]> {
	const querySnapshot = await getDocs(collection(db, 'games'));
	const games: Game[] = [];

	querySnapshot.forEach((doc) => {
		const data = doc.data();
		if (data.homeTeamId === teamId || data.awayTeamId === teamId) {
			games.push({
				id: doc.id,
				...data
			} as Game);
		}
	});

	return games;
}

//TODO: Implement this function
export async function getGamesByTeamName(teamName: string): Promise<Game[]> {
	// This would require you to first get the team ID from the team name
	// then call getGamesByTeam(teamId)
}

export async function getGamesBySeason(season: string): Promise<Game[]> {
	const querySnapshot = await getDocs(collection(db, 'games'));
	const games: Game[] = [];

	querySnapshot.forEach((doc) => {
		const data = doc.data();
		if (data.season === season) {
			games.push({
				id: doc.id,
				...data
			} as Game);
		}
	});

	return games;
}

export async function getAllGames(): Promise<Game[]> {
	try {
		const querySnapshot = await getDocs(collection(db, 'games'));
		const games: Game[] = [];

		querySnapshot.forEach((doc) => {
			games.push({
				id: doc.id,
				...doc.data()
			} as Game);
		});

		return games;
	} catch (error) {
		console.error('Error fetching games:', error);
		return []; // Return empty array on error
	}
}

export async function getRecentGames(limitCount: number = 10): Promise<Game[]> {
	try {
		const q = query(
			collection(db, 'games'),
			orderBy('cd', 'desc'), // Order by creation date (newest first)
			limit(limitCount)
		);
		const querySnapshot = await getDocs(q);
		const games: Game[] = [];

		querySnapshot.forEach((doc) => {
			games.push({
				id: doc.id,
				...doc.data()
			} as Game);
		});

		return games;
	} catch (error) {
		console.error('Error fetching recent games:', error);
		return [];
	}
}
