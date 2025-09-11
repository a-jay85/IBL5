<script lang='ts'>
    import '../app.css';
    import type { PageData } from './$types';
    import { createGameUrl } from '$lib/utils/utils';
    import logo from '$lib/assets/logo.jpg';
	import { onMount } from 'svelte';

    let { data }: { data: PageData } = $props();
    let { games } = data;

    onMount(() => {
        console.log('Games data:', games);
    });
</script>

<div class="container mx-auto px-4">
    <img src={logo} alt="IBL Logo" class="mx-auto my-4 max-w-xs sm:max-w-sm" />
    
    <div>
        <h1 class="text-center text-2xl sm:text-3xl font-bold underline mb-6">
            Welcome to AYE-BEE-EL!
        </h1>
    </div>

    <!-- Responsive games list -->
    <div class="w-full max-w-4xl mx-auto">
        <div class="grid gap-3 sm:gap-4 px-2 sm:px-4">
            {#each games as game}
                <a href={createGameUrl(game.date, game.gameOfThatDay)} 
                   class="btn btn-outline 
                          text-sm sm:text-base lg:text-xl xl:text-2xl 
                          p-3 sm:p-4 
                          h-auto min-h-0 
                          flex flex-col justify-center 
                          hover:scale-[1.02] transition-transform duration-200 
                          break-words">
                    
                    <div class="w-full text-center">
                        <!-- Team matchup -->
                        <div class="font-bold leading-tight mb-1">
                            <span class="block sm:inline">
                                {game.awayTeam?.city || 'TBD'} {game.awayTeam?.name || ''}
                            </span>
                            <span class="text-xs sm:text-sm mx-2 opacity-60">@</span>
                            <span class="block sm:inline">
                                {game.homeTeam?.city || 'TBD'} {game.homeTeam?.name || ''}
                            </span>
                        </div>
                        
                        <!-- Game details -->
                        <div class="text-xs sm:text-sm opacity-75 mt-2">
                            <div class="flex flex-col sm:flex-row sm:justify-center sm:gap-4 items-center">
                                <span>{new Date(game.date).toLocaleDateString()}</span>
                                <span class="hidden sm:inline text-xs">•</span>
                                <span>Game {game.gameOfThatDay}</span>
                            </div>
                        </div>
                    </div>
                </a>
            {/each}

            <!-- Empty state -->
            {#if games.length === 0}
                <div class="text-center py-12">
                    <h2 class="text-xl font-bold mb-2">No games found</h2>
                    <p class="text-gray-600">Check back later for upcoming games!</p>
                </div>
            {/if}
        </div>
    </div>
</div>