import { db } from '$lib/firebase/firebase';
import { collection, doc, addDoc, getDoc, getDocs } from 'firebase/firestore';

export interface Team {
	id: string;
	name: string;
	city: string;
	abbreviation: string;
	players: string[];
	conference?: string;
	division?: string;
	logoUrl?: string;
}

export async function addTeam(data: Omit<Team, 'id'>): Promise<string> {
	if (!db) throw new Error('Database not initialized');
	const docRef = await addDoc(collection(db, 'teams'), data);
	return docRef.id;
}
export async function getTeamById(id: string): Promise<Team | null> {
	if (!db) throw new Error('Database not initialized');
	const docRef = doc(db, 'teams', id);
	const docSnap = await getDoc(docRef);

	if (docSnap.exists()) {
		return {
			id: docSnap.id,
			...docSnap.data()
		} as Team;
	}

	return null;
}
export async function getTeamByName(name: string): Promise<Team[]> {
	if (!db) throw new Error('Database not initialized');
	const querySnapshot = await getDocs(collection(db, 'teams'));
	const teams: Team[] = [];

	querySnapshot.forEach((doc) => {
		const data = doc.data();
		if (data.name.toLowerCase().includes(name.toLowerCase())) {
			teams.push({
				id: doc.id,
				...data
			} as Team);
		}
	});

	return teams;
}
export async function getAllTeams(): Promise<Team[]> {
	if (!db) throw new Error('Database not initialized');
	const querySnapshot = await getDocs(collection(db, 'teams'));
	const teams: Team[] = [];

	querySnapshot.forEach((doc) => {
		teams.push({
			id: doc.id,
			...doc.data()
		} as Team);
	});

	return teams;
}
