<script>
    let player = {
        id: '1',
        name: 'Michael Jordan',
        pos: 'SG',
        pts: 25.3,
        reb: 5.1,
        ast: 7.4,
        img_url: '/playerimg/1230.jpg'
    };
    let team = {
        id: '1',
        name: 'Bulls',
        logo: '/teamlogo/new7.png'
    }
</script>
<div class="card bg-base-100 w-96 p-5 border-2 border-rose-300 shadow-sm carousel-item">
    <figure class="join rounded-box w-full min-h-50">
        <img class="join-item w-40 h-40" src={player.img_url} alt="{player.name}" />
        <img class="join-item w-40 h-40" src={team.logo} alt="{team.name}" />
    </figure>
    <div class="card-body ">
        <h2 class="card-title">{player.name}</h2>
        <p>POS: {player.pos}</p>
        <p>PTS: {player.pts}</p>
        <p>REB: {player.reb}</p>
        <p>AST: {player.ast}</p>
    </div>
</div>
