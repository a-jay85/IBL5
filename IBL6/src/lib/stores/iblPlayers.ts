import { writable } from 'svelte/store';
import { type IblPlayer } from '$lib/models/IblPlayer';

export const iblPlayers = writable<IblPlayer[]>([]);
