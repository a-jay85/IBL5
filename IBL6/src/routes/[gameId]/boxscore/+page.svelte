<script lang='ts'>
    import type { PageData } from './$types';
    import { getAllIblPlayers, type IblPlayer } from '$lib/models/IblPlayer';
    import { onMount } from 'svelte';
    import PlayerCard from '$lib/components/PlayerCard.svelte';
    import SlideButtonSelector from '$lib/components/SlideButtonSelector.svelte';
    import StatsHorizontal from '$lib/components/StatsHorizontal.svelte';
    import { teamsStore, loadAllTeams, getTeamName } from '$lib/stores/teamsStore';

    const headers = [
        'Pos', 'Name', 'min', 'fgm', 'fga', 'ftm', 'fta', 
        '3pm', '3pa', 'pts', 'orb', 'reb', 'ast', 'stl', 'blk', 'tov', 'pf'
    ];

    let { data }: { data: PageData } = $props();

    // Reactive state using runes
    const game = $derived(data.game);
    const teamsLoaded = $derived($teamsStore.size > 0);
    const homeTeamName = $derived(teamsLoaded ? getTeamName(game.homeTeamId, $teamsStore) : '');
    const awayTeamName = $derived(teamsLoaded ? getTeamName(game.awayTeamId, $teamsStore) : '');
    const homeTeamScore = $derived(game.homeScore);
    const awayTeamScore = $derived(game.awayScore);

    // State for players
    let playerData = $state<IblPlayer[]>([]);
    let playersLoading = $state(true);

    // Form state
    let formData = $state({
        name: '',
        position: '',
        minutes: 0
    });

    async function fetchPlayers(): Promise<IblPlayer[]> {
        return await getAllIblPlayers();
    }

    function handleFormSubmit() {
        console.log('Form submitted:', formData);
        // Add your submit logic here
        
        // Reset form
        formData = {
            name: '',
            position: '',
            minutes: 0
        };
    }

    function resetForm() {
        formData = {
            name: '',
            position: '',
            minutes: 0
        };
    }

    onMount(async () => {
        try {
            const [players] = await Promise.all([
                fetchPlayers(),
                loadAllTeams()
            ]);
            playerData = players;
        } catch (error) {
            console.error('Error loading data:', error);
        } finally {
            playersLoading = false;
        }
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

    <!-- Player Cards Carousel -->
    <div class="flex justify-center p-4 carousel">
        {#each Array(5) as _, i}
            <PlayerCard />
        {/each}
    </div>

    <!-- Team Selector -->
    <div class="flex justify-center p-4">
        <SlideButtonSelector 
            options={[homeTeamName, awayTeamName]} 
            selected={homeTeamName} 
        />
    </div>

    <!-- Stats Table -->
    {#if playersLoading}
        <div class="flex justify-center p-4">
            <div class="flex justify-center items-center btn">
                <span class="loading loading-spinner"></span>
                Loading players...
            </div>
        </div>
    {:else if playerData.length === 0}
        <div class="flex flex-col items-center justify-center p-12 text-center">
            <div class="text-6xl mb-4">🏀</div>
            <h3 class="text-lg font-semibold mb-2">No Player Stats Available</h3>
            <p class="text-base-content/60">Player statistics will appear here once available.</p>
        </div>
    {:else}
        <div class="overflow-x-auto">
            <table class="table table-zebra table-pin-rows table-xs min-w-full">
                <thead>
                    <StatsHorizontal {headers} />
                </thead>
                <tbody>
                    {#each playerData as player, rowIndex (player.id || rowIndex)}
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

    <!-- Form section with proper form handling -->
    <div class="card bg-base-100 shadow-xl mt-8">
        <div class="card-header p-6">
            <h2 class="card-title">Add New Player</h2>
        </div>
        <form class="card-body" onsubmit={handleFormSubmit}>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-control">
                    <label class="label" for="player-name">
                        <span class="label-text">Player Name</span>
                    </label>
                    <input 
                        id="player-name"
                        type="text" 
                        class="input input-bordered" 
                        bind:value={formData.name}
                        required
                    />
                </div>
                
                <div class="form-control">
                    <label class="label" for="player-position">
                        <span class="label-text">Position</span>
                    </label>
                    <select 
                        id="player-position"
                        class="select select-bordered"
                        bind:value={formData.position}
                        required
                    >
                        <option value="" disabled>Select position</option>
                        <option value="PG">PG</option>
                        <option value="SG">SG</option>
                        <option value="SF">SF</option>
                        <option value="PF">PF</option>
                        <option value="C">C</option>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label" for="player-minutes">
                        <span class="label-text">Minutes</span>
                    </label>
                    <input 
                        id="player-minutes"
                        type="number" 
                        class="input input-bordered" 
                        bind:value={formData.minutes}
                        min="0" 
                        max="48" 
                    />
                </div>
            </div>
            
            <div class="card-actions justify-end mt-6">
                <button type="button" class="btn btn-outline" onclick={resetForm}>
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    Add Player
                </button>
            </div>
        </form>
    </div>
{/if}
