<script lang='ts'>
    import type { PageData } from './$types';
    import { getAllIblPlayers, type IblPlayer } from '$lib/models/IblPlayer';
    import { onMount } from 'svelte';
    import PlayerCard from '$lib/components/PlayerCard.svelte';
    import SlideButtonSelector from '$lib/components/SlideButtonSelector.svelte';
    import StatsHorizontal from '$lib/components/StatsHorizontal.svelte';
    import { teamsStore, loadAllTeams, getTeamName } from '$lib/stores/teamsStore';

    let headers = [
        'Pos', 'Name', 'min', 'fgm', 'fga', 'ftm', 'fta', 
        '3pm', '3pa', 'pts', 'orb', 'reb', 'ast', 'stl', 'blk', 'tov', 'pf'
    ];

    export let data: PageData;

    $: game = data.game;
    $: teamsLoaded = $teamsStore.size > 0;
    
    // Only show team names after teams are loaded
    $: homeTeamName = teamsLoaded ? getTeamName(game.homeTeamId, $teamsStore) : '';
    $: awayTeamName = teamsLoaded ? getTeamName(game.awayTeamId, $teamsStore) : '';
    $: homeTeamScore = game.homeScore;
    $: awayTeamScore = game.awayScore;

    let playerData: IblPlayer[] = [];

    async function fetchPlayers() {
        return await getAllIblPlayers();
    }

    onMount(async () => {
        const [players] = await Promise.all([
            fetchPlayers(),
            loadAllTeams()
        ]);
        playerData = players;
    });
</script>

<!-- Show loading state until teams are loaded -->
{#if !teamsLoaded}
    <div class="flex justify-center items-center p-12">
        <div class="text-center">
            <span class="loading loading-spinner loading-lg mb-4"></span>
            <p class="text-lg">Loading game data...</p>
        </div>
    </div>
{:else}
    <!-- Main content only shows when teams are loaded -->
    <div class="bg-gradient-to-r from-red-600 to-purple-600 text-white p-6 rounded-lg shadow-lg mb-6">
        <div class="flex justify-around items-center">
            <!-- Away Team -->
            <div class="flex items-center space-x-4">
                <div class="text-center">
                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-2">
                        <span class="text-2xl font-bold text-gray-800">{awayTeamName[0] || '?'}</span>
                    </div>
                    <div class="text-sm opacity-90">{awayTeamName || 'Loading...'}</div>
                </div>
                <div class="text-4xl font-bold">{awayTeamScore}</div>
            </div>
            
            <!-- VS and Game Info -->
            <div class="text-center">
                <div class="text-sm opacity-75 mb-1">FINAL</div>
                <div class="text-2xl font-bold">VS</div>
            </div>
            
            <!-- Home Team -->
            <div class="flex items-center space-x-4">
                <div class="text-4xl font-bold">{homeTeamScore}</div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-2">
                        <span class="text-2xl font-bold text-gray-800">{homeTeamName[0] || '?'}</span>
                    </div>
                    <div class="text-sm opacity-90">{homeTeamName || 'Loading...'}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-center p-4 carousel">
        <PlayerCard />
        <PlayerCard />
        <PlayerCard />
        <PlayerCard />
        <PlayerCard />
    </div>

    <div class="flex justify-center p-4">
        <SlideButtonSelector 
            options={[homeTeamName, awayTeamName]} 
            selected={homeTeamName} 
        />
    </div>

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
                                <td class="{label === 'Name' ? 'sticky z-20 left-0 bg-base-100' : ''} min-w-10 px-4 py-3 whitespace-nowrap">
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

    <!-- Form section -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-header p-6">
            <h2 class="card-title">Add New Player</h2>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Player Name</span>
                    </label>
                    <input type="text" class="input input-bordered" />
                </div>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Position</span>
                    </label>
                    <select class="select select-bordered">
                        <option>PG</option>
                        <option>SG</option>
                        <option>SF</option>
                        <option>PF</option>
                        <option>C</option>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Minutes</span>
                    </label>
                    <input type="number" class="input input-bordered" min="0" max="48" />
                </div>
            </div>
            
            <div class="card-actions justify-end mt-6">
                <button class="btn btn-outline">Cancel</button>
                <button class="btn btn-primary">Add Player</button>
            </div>
        </div>
    </div>
{/if}
