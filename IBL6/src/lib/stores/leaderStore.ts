import { writable } from 'svelte/store';
import { type IblPlayer } from '$lib/models/IblPlayer';

function createLeaderStore() {
	const { subscribe, set, update } = writable<IblPlayer[]>([]);

	return {
		subscribe,
		set,
		update
	};
}

export const leaderStore = createLeaderStore;
