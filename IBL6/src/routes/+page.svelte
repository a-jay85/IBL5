<script>
	import '../app.css';
    import { collection, addDoc, getDocs, doc, updateDoc, deleteDoc } from 'firebase/firestore';
    import { getAuth, createUserWithEmailAndPassword, signInWithEmailAndPassword, signOut } from 'firebase/auth';
    import { db } from '../lib/firebase/firebase';

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
	let items = [
		{
			id: 1,
			pos: 'PG',
			name: 'Stephon Marbury',
			min: 38,
			fgm: 11,
			fga: 22,
			ftm: 5,
			fta: 7,
			'3pm': 2,
			'3pa': 6,
			pts: 29,
			orb: 1,
			reb: 0,
			ast: 8,
			stl: 1,
			blk: 0,
			tov: 3,
			pf: 2
		},
		{
			id: 2,
			pos: 'SG',
			name: 'Joe Smith',
			min: 36,
			fgm: 9,
			fga: 18,
			ftm: 4,
			fta: 5,
			'3pm': 1,
			'3pa': 4,
			pts: 23,
			oreb: 2,
			dreb: 5,
			ast: 4,
			stl: 2,
			blk: 1,
			tov: 2,
			pf: 3
		},
		{
			id: 3,
			pos: 'SF',
			name: 'Kenyon Martin',
			min: 34,
			fgm: 8,
			fga: 15,
			ftm: 6,
			fta: 8,
			'3pm': 0,
			'3pa': 1,
			pts: 22,
			oreb: 3,
			dreb: 7,
			ast: 2,
			stl: 1,
			blk: 2,
			tov: 1,
			pf: 4
		},
		{
			id: 4,
			pos: 'PF',
			name: 'Kris Humphries',
			min: 32,
			fgm: 7,
			fga: 14,
			ftm: 3,
			fta: 4,
			'3pm': 0,
			'3pa': 0,
			pts: 17,
			oreb: 4,
			dreb: 6,
			ast: 1,
			stl: 0,
			blk: 1,
			tov: 2,
			pf: 5
		},
		{
			id: 5,
			pos: 'C',
			name: 'Zaza Pachulia',
			min: 30,
			fgm: 6,
			fga: 12,
			ftm: 2,
			fta: 3,
			'3pm': 0,
			'3pa': 0,
			pts: 14,
			oreb: 5,
			dreb: 8,
			ast: 1,
			stl: 0,
			blk: 3,
			tov: 1,
			pf: 4
		}

		// Add more items as needed
	];
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
					<span class="indicator-item badge badge-xs badge-primary"></span>
				</div>
			</button>
		</div>
	</div>
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
			{#each items as item, i}
				<tr>
					<th>{item.id}</th>
					<td>{item.pos}</td>
					<td>{item.name}</td>
					<td>{item.min}</td>
					<td>{item.fgm}</td>
					<td>{item.fga}</td>
					<td>{item.ftm}</td>
					<td>{item.fta}</td>
					<td>{item['3pm']}</td>
					<td>{item['3pa']}</td>
					<td>{item.pts}</td>
					<td>{item.oreb}</td>
					<td>{item.dreb}</td>
					<td>{item.ast}</td>
					<td>{item.stl}</td>
					<td>{item.blk}</td>
					<td>{item.tov}</td>
					<td>{item.pf}</td>
				</tr>
			{/each}
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
