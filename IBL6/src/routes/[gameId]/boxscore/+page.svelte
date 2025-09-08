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
    const homeTeamLogo = $derived(teamsLoaded ? ($teamsStore.get(game.homeTeamId)?.logoUrl || '') : '');
    const awayTeamLogo = $derived(teamsLoaded ? ($teamsStore.get(game.awayTeamId)?.logoUrl || '') : '');

    // State for players
    let playerData = $state<IblPlayer[]>([]);
    let playersLoading = $state(true);
    
    // Selected team state
    let selectedTeamName = $state('');

    // Filter players using team's player array
    const filteredPlayers = $derived.by(() => {
        console.log('Filtering players for selected team:', selectedTeamName);
        if (!selectedTeamName || !teamsLoaded) return [];
        
        // Get the selected team ID
        const selectedTeamId = selectedTeamName === homeTeamName ? game.homeTeamId : game.awayTeamId;
        console.log('Filtering players for team ID:', selectedTeamId, 'Team Name:', selectedTeamName);
        
        // Get the team from the store
        const selectedTeam = $teamsStore.get(selectedTeamId);
        if (!selectedTeam || !selectedTeam.players) {
            console.log('No team found or no players array for team:', selectedTeamId);
            return [];
        }

        console.log('Team players array:', selectedTeam.players);

        // Filter playerData to only include players whose DOCUMENT IDs are in the team's players array
        return playerData.filter(player => {
            const isInTeam = selectedTeam.players.includes(player.docId || player.id || '');
            console.log('Checking player:', player.name, 'Document ID:', player.docId || player.id, 'In team:', isInTeam);
            return isInTeam;
        });
    });

    // Form state
    let formData = $state({
        name: '',
        position: '',
        minutes: 0
    });

    async function fetchPlayers(): Promise<IblPlayer[]> {
        return await getAllIblPlayers();
    }

    function handleTeamSelection(teamName: string) {
        selectedTeamName = teamName;
        console.log('Selected team:', selectedTeamName);
    }
    
    function resetForm() {
        formData = {
            name: '',
            position: '',
            minutes: 0
        };
    }

    function handleFormSubmit(event: Event) {
        event.preventDefault();
        console.log('Form submitted:', formData);
        //TODO Add submit logic
        
        resetForm();
    }

    onMount(async () => {
        try {
            const [players] = await Promise.all([
                fetchPlayers(),
                loadAllTeams()
            ]);
            playerData = players;
            
            // Set initial selected team to home team (wait for teams to load)
            if (homeTeamName) {
                selectedTeamName = homeTeamName;
            }
        } catch (error) {
            console.error('Error loading data:', error);
        } finally {
            playersLoading = false;
        }
    });

    // Set initial team selection when teams are loaded
    $effect(() => {
        if (teamsLoaded && homeTeamName && !selectedTeamName) {
            selectedTeamName = homeTeamName;
            console.log('Setting initial team to:', homeTeamName);
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
                        {#if !awayTeamLogo}
                            <span class="text-2xl font-bold text-gray-800">{awayTeamName[0] || '?'}</span>
                        {:else}
                            <img src={awayTeamLogo} alt="{awayTeamName} Logo" class="w-12 h-12 object-contain" />
                        {/if}
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
                        {#if !homeTeamLogo}
                            <span class="text-2xl font-bold text-gray-800">{homeTeamName[0] || '?'}</span>
                        {:else}
                            <img src={homeTeamLogo} alt="{homeTeamName} Logo" class="w-12 h-12 object-contain" />
                        {/if}
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
            selected={selectedTeamName || homeTeamName}
            onSelectionChange={handleTeamSelection}
        />
    </div>

    <!-- Stats Table -->
    {#if !selectedTeamName}
        <div class="flex flex-col items-center justify-center p-12 text-center">
            <div class="text-6xl mb-4">👆</div>
            <h3 class="text-lg font-semibold mb-2">Select a Team</h3>
            <p class="text-base-content/60">Choose a team above to view player statistics.</p>
        </div>
    {:else if playersLoading}
        <div class="flex justify-center p-4">
            <div class="flex justify-center items-center btn">
                <span class="loading loading-spinner"></span>
                Loading players...
            </div>
        </div>
    {:else if filteredPlayers.length === 0}
        <div class="flex flex-col items-center justify-center p-12 text-center">
            <div class="text-6xl mb-4">🏀</div>
            <h3 class="text-lg font-semibold mb-2">No Players Found</h3>
            <p class="text-base-content/60">
                No players found for {selectedTeamName}.
            </p>
        </div>
    {:else}
        <div class="mb-4">
            <h2 class="text-xl font-bold text-center">
                {selectedTeamName} Players ({filteredPlayers.length})
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-zebra table-pin-rows table-xs min-w-full">
                <thead>
                    <StatsHorizontal {headers} />
                </thead>
                <tbody>
                    {#each filteredPlayers as player, rowIndex (player.id || rowIndex)}
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
