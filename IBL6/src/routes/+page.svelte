<script lang='ts'>
    import '../app.css';
    import type { PageData } from './$types';
    import { onMount } from 'svelte';
    import { teamsStore, loadAllTeams, getTeamName } from '$lib/stores/teamsStore';

    let { data }: { data: PageData } = $props();
    let { games } = data;
    
    // Use derived to check if teams are loaded
    let teamsLoaded = $derived($teamsStore.size > 0);
    console.log('games', data.games);

    onMount(async () => {
        await loadAllTeams();
    });
</script>

<div>
    <h1 class="flex justify-center text-3xl font-bold underline">Welcome to AYE-BEE-EL!</h1>
</div>

{#if !teamsLoaded}
<div class="flex justify-center p-2">
    <span class="loading loading-xs"></span>
    <span class="text-xs ml-2">Loading team names...</span>
</div>
{:else}
<div class="flex-col flex justify-center p-4 gap-4">
    {#each games as game}
        <a href="/{game.id}/boxscore" class="btn btn-outline mb-2">
            {game.awayScore} {getTeamName(game.awayTeamId, $teamsStore)} @ {getTeamName(game.homeTeamId, $teamsStore)} {game.homeScore}
        </a>
    {/each}
</div>
{/if}
