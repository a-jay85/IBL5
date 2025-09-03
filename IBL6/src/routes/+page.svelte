<script lang='ts'>
	import '../app.css';
	import { collection, getDocs } from 'firebase/firestore';
	import { db } from '$lib/firebase/firebase';
	import { getAllIblPlayers, type IblPlayer } from '$lib/models/IblPlayer';
	import { onMount } from 'svelte';
	import PlayerCard from '../components/PlayerCard.svelte';
    import LeaderCard from '../components/LeaderCard.svelte';

	let fields = [
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

	let playerData: IblPlayer[] = [];

	async function fetchPlayers() {
        return await getAllIblPlayers();
	}

	onMount( async () => {
		playerData = await fetchPlayers();
	});
</script>

<div>
	<div class="navbar bg-base-100 shadow-sm">
		<div class="navbar-start">
			<div class="dropdown">
				<div tabindex="0" role="button" class="btn btn-circle btn-ghost">
					<svg
						xmlns="http://www.w3.org/2000/svg"
						class="h-5 w-5"
						fill="none"
						viewBox="0 0 24 24"
						stroke="currentColor"
					>
						<path
							stroke-linecap="round"
							stroke-linejoin="round"
							stroke-width="2"
							d="M4 6h16M4 12h16M4 18h7"
						/>
					</svg>
				</div>
				<ul
					tabindex="0"
					class="dropdown-content menu z-1 mt-3 w-52 menu-sm rounded-box bg-base-100 p-2 shadow"
				>
					<li><a href="www.iblhoops.net">Homepage</a></li>
					<li><a>Portfolio</a></li>
					<li><a>About</a></li>
				</ul>
			</div>
		</div>
		<div class="navbar-center">
			<a class="btn text-xl btn-ghost">AYE-BEE-EL</a>
		</div>
		<div class="navbar-end">
			<button class="btn btn-circle btn-ghost">
				<svg
					xmlns="http://www.w3.org/2000/svg"
					class="h-5 w-5"
					fill="none"
					viewBox="0 0 24 24"
					stroke="currentColor"
				>
					<path
						stroke-linecap="round"
						stroke-linejoin="round"
						stroke-width="2"
						d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
					/>
				</svg>
			</button>
			<button class="btn btn-circle btn-ghost">
				<div class="indicator">
					<svg
						xmlns="http://www.w3.org/2000/svg"
						class="h-5 w-5"
						fill="none"
						viewBox="0 0 24 24"
						stroke="currentColor"
					>
						<path
							stroke-linecap="round"
							stroke-linejoin="round"
							stroke-width="2"
							d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
						/>
					</svg>
					<span class="indicator-player badge badge-xs badge-primary"></span>
				</div>
			</button>
		</div>
	</div>
</div>
<div class="flex justify-center p-4 carousel">
    <!-- <PlayerCard /> -->
    <LeaderCard />
</div>
<div class="overflow-x-auto">
	<table class="table-pin-rows table-pin-cols table table-xs">
		<thead>
			<tr>
				<th></th>
				{#each fields as field}
					<th>{field}</th>
				{/each}
				<th></th>
			</tr>
		</thead>
		<tbody>
			{#if playerData.length === 0}
				<tr>
					<td colspan="{fields.length + 2}">Loading player data...</td>
				</tr>
			{:else}
				{#each playerData as player, i}
					<tr>
						<th>{player.id}</th>
						<td>{player.pos}</td>
						<td>{player.name}</td>
						<td>{player.min}</td>
						<td>{player.fgm}</td>
						<td>{player.fga}</td>
						<td>{player.ftm}</td>
						<td>{player.fta}</td>
						<td>{player['3pm']}</td>
						<td>{player['3pa']}</td>
						<td>{player.pts}</td>
						<td>{player.orb}</td>
						<td>{player.reb}</td>
						<td>{player.ast}</td>
						<td>{player.stl}</td>
						<td>{player.blk}</td>
						<td>{player.tov}</td>
						<td>{player.pf}</td>
					</tr>
				{/each}
			{/if}
		</tbody>
		<tfoot>
			<tr>
				<th></th>
				{#each fields as field}
					<th>{field}</th>
				{/each}
				<th></th>
			</tr>
		</tfoot>
	</table>
</div>
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
	/* Freeze the first column */
	.table-container th:nth-child(2),
	.table-container td:nth-child(2) {
		position: sticky;
		left: 0;
		z-index: 1; /* Ensure it stays above other content */
	}

	/* Freeze the second column (example) */
	.table-container th:nth-child(3),
	.table-container th:nth-child(4),
	.table-container th:nth-child(5),
	.table-container td:nth-child(6) {
		position: sticky;
		left: 100px; /* Adjust based on the width of the first column */
		z-index: 2; /* Lower z-index than the first frozen column */
	}
</style>
