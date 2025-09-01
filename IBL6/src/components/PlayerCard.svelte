<script>
    let player = {
        id: '1',
        name: 'Micheal Jordan',
        pos: 'SG',
        pts: 25.3,
        reb: 5.1,
        ast: 7.4,
        img_url: 'https://placecats.com/150/225'
    };
    let team = {
        id: '1',
        name: 'Team A',
        logo: 'https://placecats.com/150/225'
    }
</script>
<div class="card bg-base-100 w-96 shadow-sm">
    <figure class="join rounded-box">
        <img class="join-item" src={player.img_url} alt="{player.name}" />
        <img class="join-item" src={team.logo} alt="{team.name}" />
    </figure>
    <div class="card-body ">
        <h2 class="card-title">{player.name}</h2>
        <p>POS: {player.pos}</p>
        <p>PTS: {player.pts}</p>
        <p>REB: {player.reb}</p>
        <p>AST: {player.ast}</p>
    </div>
</div>
