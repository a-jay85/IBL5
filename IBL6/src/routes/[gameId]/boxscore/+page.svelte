<script lang='ts'>
    import type { PageData } from './$types';
    import { getAllIblPlayers, type IblPlayer } from '$lib/models/IblPlayer';
    import { onMount } from 'svelte';
    import PlayerCard from '$lib/components/PlayerCard.svelte';
    import LeaderCard from '$lib/components/LeaderCard.svelte';
    import SlideButtonSelector from '$lib/components/SlideButtonSelector.svelte';
    import StatsHorizontal from '$lib/components/StatsHorizontal.svelte';
    import { teamsStore, loadAllTeams, getTeamName } from '$lib/stores/teamsStore';

    let headers = [
        'Pos',
        'Name',
        'min',
        'fgm',
        'fga',
        'ftm',
        'fta',
        '3pm',
        '3pa',
        'pts',
        'orb',
        'reb',
        'ast',
        'stl',
        'blk',
        'tov',
        'pf'
    ];

    export let data: PageData;

    $: game = data.game;
    $: homeTeamName = getTeamName(game.homeTeamId, $teamsStore);
    $: awayTeamName = getTeamName(game.awayTeamId, $teamsStore);

    let playerData: IblPlayer[] = [];
    let teamsLoaded = false;

    async function fetchPlayers() {
        return await getAllIblPlayers();
    }

    onMount(async () => {
        // Load teams and players in parallel
        const [players] = await Promise.all([
            fetchPlayers(),
            loadAllTeams()
        ]);
        playerData = players;
        teamsLoaded = true;
    });
</script>

<div class="flex justify-center p-4">
    <h1>Boxscore: {awayTeamName} @ {homeTeamName}</h1>
</div>

<div class="flex justify-center p-4 carousel">
    <PlayerCard />
    <!-- <LeaderCard /> -->
</div>

<div class="flex justify-center p-4">
    <SlideButtonSelector 
        options={[homeTeamName, awayTeamName]} 
        selected={homeTeamName} 
    />
</div>

{#if !teamsLoaded}
    <div class="flex justify-center p-2">
        <span class="loading loading-xs"></span>
        <span class="text-xs ml-2">Loading team names...</span>
    </div>
{/if}

{#if playerData.length === 0}
<div class="flex justify-center p-4">
    <button class="flex justify-center items-center btn">
        <span class="loading loading-spinner"></span>
        loading players
    </button>
</div>
{:else}
<div class="overflow-x-auto">
    <table class="table table-zebra table-pin-rows table-xs min-w-full">
        <thead>
            <StatsHorizontal {headers} />
        </thead>
        <tbody>
            {#each playerData as player, rowIndex}
                <tr class="transition-colors">
                    {#each headers as label}
                        <td
                            class="
                            {label === 'Name'? 'sticky z-20 left-0 bg-base-100' : ''}
                            {rowIndex === 0 ? 'left-0 shadow-[2px_0_5px_rgba(0,0,0,0.08)]' : ''}
                            min-w-10 px-4 py-3 whitespace-nowrap
                            "
                        >
                            {player[label.toLowerCase() as keyof IblPlayer]}
                        </td>
                    {/each}
                </tr>
            {/each}
        </tbody>
        <tfoot>
            <StatsHorizontal {headers} />
        </tfoot>
    </table>
</div>
{/if}

<div class="flex justify-center flex-col gap-2 p-4">
    <label for="name" class="floating-label">Name:</label>
    <input id="name" type="text" class="input input-xs"/>

    <label for="pos">Position:</label>
    <input id="pos" type="text" class="input input-xs"/>

    <label for="id">ID:</label>
    <input id="id" type="number" class="input input-xs"/>

    <label for="min">Minutes:</label>
    <input id="min" type="number" class="input input-xs"/>

    <label for="fgm">Field Goals Made:</label>
    <input id="fgm" type="number" class="input input-xs"/>

    <label for="fga">Field Goals Attempted:</label>
    <input id="fga" type="number" class="input input-xs"/>

    <button type="submit" class="btn">Add Player</button>
</div>

<div>
    <h2>Create A Player</h2>
    <button id='' class="btn">Create Player</button>
</div>

<style>

</style>
