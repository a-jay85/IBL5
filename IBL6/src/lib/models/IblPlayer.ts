import { addDoc, collection, getDocs } from 'firebase/firestore';
import { db } from '../firebase/firebase';

export interface IblPlayer {
	id: string;
	cd: number;
	pos: string;
	name: string;
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
	return addDoc(collection(db, 'iblPlayers'), data);
}

export async function getIblPlayerById(id: string): Promise<IblPlayer> {
	const querySnapshot = await getDocs(collection(db, 'iblPlayers'));
	const player = querySnapshot.docs
		.map((doc) => doc.data() as IblPlayer)
		.find((player) => player.id === id);
	if (!player) throw new Error('Player not found');
	return player;
}

export async function getPlayerByName(name: string): Promise<IblPlayer[]> {
	const querySnapshot = await getDocs(collection(db, 'iblPlayers'));
	return querySnapshot.docs
		.map((doc) => doc.data() as IblPlayer)
		.filter((player) => player.name.toLowerCase().includes(name.toLowerCase()));
}

export async function getIblPlayersByTeamId(teamId: string): Promise<IblPlayer[]> {
	const querySnapshot = await getDocs(collection(db, 'iblPlayers'));
	return querySnapshot.docs
		.map((doc) => doc.data() as IblPlayer)
		.filter((player) => player.id.startsWith(teamId));
}

export async function getAllIblPlayers(): Promise<IblPlayer[]> {
	const querySnapshot = await getDocs(collection(db, 'iblPlayers'));
	return querySnapshot.docs.map((doc) => doc.data() as IblPlayer);
}
