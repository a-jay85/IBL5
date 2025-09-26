<script lang='ts'>
	import type { IblPlayer } from '$lib/models/IblPlayer';

    let leaderStat = 'Points Per Game';
    let player = [{
        id: '1',
        name: 'Micheal Jordan',
        team: 'Chicago Bulls',
        pos: 'SG',
        pts: 25.3,
        reb: 5.1,
        ast: 7.4,
        img_url: 'https://placecats.com/150/225'
    },
    {
        id: '2',
        name: 'LeBron James',
        team: 'Los Angeles Lakers',
        pos: 'SF',
        pts: 27.0,
        reb: 7.4,
        ast: 7.4,
        img_url: 'https://placecats.com/150/225'
    },
    {
        id: '3',
        name: 'Kobe Bryant',
        team: 'Los Angeles Lakers',
        pos: 'SG',
        pts: 25.0,
        reb: 5.2,
        ast: 4.7,
        img_url: 'https://placecats.com/150/225'
    },
    {
        id: '4',
        name: 'Tim Duncan',
        team: 'San Antonio Spurs',
        pos: 'PF',
        pts: 26.0,
        reb: 11.0,
        ast: 4.0,
        img_url: 'https://placecats.com/150/225'
    },
    {
        id: '5',
        name: 'Shaquille O\'Neal',
        team: 'Los Angeles Lakers',
        pos: 'C',
        pts: 23.7,
        reb: 10.9,
        ast: 2.5,
        img_url: 'https://placecats.com/150/225'
    }
];
    let team = {
        id: '1',
        name: 'Team A',
        logo: 'https://placecats.com/150/225'
    }
</script>
<div class="card bg-base-100 w-96 border-accent shadow-sm border-2 p-5 carousel-item">
    <figure class="join rounded-box">
        <img class="join-item" src={player[0].img_url} alt="{player[0].name}" />
        <img class="join-item" src={team.logo} alt="{team.name}" />
    </figure>
    <div class="card-body ">
        <h2 class="card-title">{leaderStat}</h2>
        <div>
            {#each player as p (p.id)}
                <p>{p.name} - {p.pts} PPG</p>
                <p>{p.team}</p>
            {/each}
        </div>
    </div>
</div>
