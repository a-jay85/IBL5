<script lang='ts'>
    import '../app.css';
    import type { PageData } from './$types';

    export let data: PageData;
    $: games = data.games;
</script>
<div>
    <h1 class="flex justify-center text-3xl font-bold underline">Welcome to AYE-BEE-EL!</h1>
</div>
<div class="flex-col flex justify-center p-4 gap-4">
    {#each games as game}
        <a href="/{game.id}/boxscore" class="btn btn-outline mb-2">
            {game.awayScore} {game.awayTeam} @ {game.homeTeam} {game.homeScore}
        </a>
    {/each}
</div>
