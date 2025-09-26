import { writable } from 'svelte/store';
import { getAllTeams, type Team } from '$lib/models/Team';

export const teamsStore = writable<Map<string, Team>>(new Map());

let teamsLoaded = false;

export async function loadAllTeams(): Promise<void> {
	if (teamsLoaded) return;

	try {
		const teams = await getAllTeams();
		const teamsMap = new Map(teams.map((team) => [team.id, team]));
		teamsStore.set(teamsMap);
		teamsLoaded = true;
		console.log('Teams loaded:', teamsMap.size);
		console.log(teamsMap);
	} catch (error) {
		console.error('Error loading teams:', error);
	}
}

export function getTeamName(teamId: string, teamsMap: Map<string, Team>): string {
	const team = teamsMap.get(teamId);
	return team?.name || teamId; // Fallback to teamId if team not found
}
