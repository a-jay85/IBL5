import { addDoc, collection, getDocs } from 'firebase/firestore';
import { db } from '$lib/firebase/firebase';

export interface IblPlayer {
	docId?: string; // Document ID from Firestore
	id?: string; // Player ID
	cd: number; // Player creation date
	name: string;
	pos: string;
	min: number;
	fgm: number;
	fga: number;
	ftm: number;
	fta: number;
	'3pm': number;
	'3pa': number;
	pts: number;
	orb: number;
	reb: number;
	ast: number;
	stl: number;
	blk: number;
	tov: number;
	pf: number;
}

export async function addIblPlayer(data: IblPlayer) {
	if (!db) throw new Error('Database not initialized');
	return addDoc(collection(db, 'iblPlayers'), data);
}
export async function getIblPlayerById(id: string): Promise<IblPlayer> {
	if (!db) throw new Error('Database not initialized');
	const querySnapshot = await getDocs(collection(db, 'iblPlayers'));
	const player = querySnapshot.docs
		.map((doc) => doc.data() as IblPlayer)
		.find((player) => player.id === id);
	if (!player) throw new Error('Player not found');
	return player;
}
export async function getPlayerByName(name: string): Promise<IblPlayer[]> {
	if (!db) throw new Error('Database not initialized');
	const querySnapshot = await getDocs(collection(db, 'iblPlayers'));
	return querySnapshot.docs
		.map((doc) => doc.data() as IblPlayer)
		.filter((player) => player.name.toLowerCase().includes(name.toLowerCase()));
}
export async function getIblPlayersByTeamId(teamId: string): Promise<IblPlayer[]> {
	if (!db) throw new Error('Database not initialized');
	const querySnapshot = await getDocs(collection(db, 'iblPlayers'));
	return querySnapshot.docs
		.map((doc) => doc.data() as IblPlayer)
		.filter((player) => player.docId && player.docId.startsWith(teamId));
}
export async function getAllIblPlayers(): Promise<IblPlayer[]> {
	if (!db) return []; // Handle case where Firebase is not initialized

	try {
		const querySnapshot = await getDocs(collection(db, 'iblPlayers'));
		const players: IblPlayer[] = [];

		querySnapshot.forEach((doc) => {
			players.push({
				docId: doc.id,
				...doc.data()
			} as IblPlayer);
		});

		return players;
	} catch (error) {
		console.error('Error fetching players:', error);
		return [];
	}
}
