<?php

if (!defined('BLOCK_FILE')) {
    Header("Location: ../index.php");
    die();
}

global $leagueContext;

$currentLeague = $leagueContext->getCurrentLeague();

if ($currentLeague === 'ibl') {
    $content = '
<strong>Management</strong>
<ul>
<li><a href="modules.php?name=NextSim" style="color: red">Next Sim (NEW!)</a> </li>

<li><a href="modules.php?name=DepthChartEntry">Depth Chart Form</a> </li>

<li><a href="modules.php?name=ActivityTracker">Depth Chart Tracker</a> </li>

<li><a href="modules.php?name=Trading&op=reviewtrade">Offer Trade</a> </li>

<li><a href="modules.php?name=Waivers&action=add">Waiver Wire</a> </li>

<li><a href="modules.php?name=Waivers&action=drop">Waive Player</a> </li>

<li><a href="modules.php?name=Voting">ASG/Award Voting</a></li>
</ul>
<strong>Offseason</strong>
<ul>
<li><a href="modules.php?name=Draft">Draft Scout/Select</a></li>

<li><a href="modules.php?name=FreeAgency">Free Agency</a></li>

<li><a href="modules.php?name=PlayerMovement">Player Movement</a></li>
</ul>
';
} else if ($currentLeague === 'olympics') {
    $content = '
<strong>Management</strong>
<ul>
<li><a href="modules.php?name=DepthChartEntry">Depth Chart Form</a> </li>

<li><a href="modules.php?name=ActivityTracker">Depth Chart Tracker</a> </li>
</ul>
';
}
