<?php

require 'mainfile.php';

$query="SELECT * FROM nuke_iblplyr WHERE retired = 0 ORDER BY pid ASC";
$result=$db->sql_query($query);
$num=$db->sql_numrows($result);

$max_chunk_query="SELECT MAX(chunk) as maxchunk FROM nuke_iblplyr_chunk WHERE active = 1";
$max_chunk_result=$db->sql_query($max_chunk_query);
$row = $db->sql_fetch_assoc($max_chunk_result);
$new_chunk=$row[maxchunk]+1;

$i=0;

while ($i < $num)
{
	$pid=$db->sql_result($result,$i,"pid");
	$name=$db->sql_result($result,$i,"name");
	$tid=$db->sql_result($result,$i,"tid");
	$teamname=$db->sql_result($result,$i,"teamname");
	$games_start_total=$db->sql_result($result,$i,"stats_gs");
	$games_total=$db->sql_result($result,$i,"stats_gm");
	$minutes_total=$db->sql_result($result,$i,"stats_min");
	$stats_fgm_total=$db->sql_result($result,$i,"stats_fgm");
	$stats_fga_total=$db->sql_result($result,$i,"stats_fga");
	$stats_ftm_total=$db->sql_result($result,$i,"stats_ftm");
	$stats_fta_total=$db->sql_result($result,$i,"stats_fta");
	$stats_3gm_total=$db->sql_result($result,$i,"stats_3gm");
	$stats_3ga_total=$db->sql_result($result,$i,"stats_3ga");
	$stats_orb_total=$db->sql_result($result,$i,"stats_orb");
	$stats_drb_total=$db->sql_result($result,$i,"stats_drb");
	$stats_ast_total=$db->sql_result($result,$i,"stats_ast");
	$stats_stl_total=$db->sql_result($result,$i,"stats_stl");
	$stats_to_total=$db->sql_result($result,$i,"stats_to");
	$stats_blk_total=$db->sql_result($result,$i,"stats_blk");
	$stats_pf_total=$db->sql_result($result,$i,"stats_pf");


	$games_start_chunk=sum_chunk_stats("stats_gs", $pid, $games_start_total);
	$games_chunk=sum_chunk_stats("stats_gm", $pid, $games_total);
	$minutes_chunk=sum_chunk_stats("stats_min", $pid, $minutes_total);
	$stats_fgm_chunk=sum_chunk_stats("stats_fgm", $pid, $stats_fgm_total);
	$stats_fga_chunk=sum_chunk_stats("stats_fga", $pid, $stats_fga_total);
	$stats_ftm_chunk=sum_chunk_stats("stats_ftm", $pid, $stats_ftm_total);
	$stats_fta_chunk=sum_chunk_stats("stats_fta", $pid, $stats_fta_total);
	$stats_3gm_chunk=sum_chunk_stats("stats_3gm", $pid, $stats_3gm_total);
	$stats_3ga_chunk=sum_chunk_stats("stats_3ga", $pid, $stats_3ga_total);
	$stats_orb_chunk=sum_chunk_stats("stats_orb", $pid, $stats_orb_total);
	$stats_drb_chunk=sum_chunk_stats("stats_drb", $pid, $stats_drb_total);
	$stats_ast_chunk=sum_chunk_stats("stats_ast", $pid, $stats_ast_total);
	$stats_stl_chunk=sum_chunk_stats("stats_stl", $pid, $stats_stl_total);
	$stats_to_chunk=sum_chunk_stats("stats_to", $pid, $stats_to_total);
	$stats_blk_chunk=sum_chunk_stats("stats_blk", $pid, $stats_blk_total);
	$stats_pf_chunk=sum_chunk_stats("stats_pf", $pid, $stats_pf_total);

	$stats_pts=2*$stats_fgm_chunk+$stats_ftm_chunk+$stats_3gm_chunk;

	if ($games_chunk > 0) {
		$qa=(($stats_pts+$stats_orb_chunk+$stats_drb_chunk+(2*$stats_ast_chunk)+(2*$stats_stl_chunk)+(2*$stats_blk_chunk))-(($stats_fga_chunk-$stats_fgm_chunk)+($stats_fta_chunk-$stats_ftm_chunk)+$stats_to_chunk+$stats_pf_chunk))/$games_chunk;

		$qa=round($qa, 2);
	}else{
		$qa=0;
	}

	//$minutes_diff=$minutes_new-$minutes_chunk;
	//$games_diff=$games_new-$games_chunk;
	//echo "<tr><td>$i. $name</td><td>Chunk $games_chunk</td><td>$minutes_chunk</td></tr>";
	//echo "<tr><td></td><td>Total $games_total</td><td>$minutes_total</td></tr>";

	$query3="UPDATE nuke_iblplyr_chunk SET active = 1, tid = $tid, teamname = '$teamname', stats_gs = $games_start_chunk, stats_gm = $games_chunk, stats_min = $minutes_chunk, stats_fgm = $stats_fgm_chunk, stats_fga = $stats_fga_chunk, stats_ftm = $stats_ftm_chunk, stats_fta = $stats_fta_chunk, stats_3gm = $stats_3gm_chunk, stats_3ga = $stats_3ga_chunk, stats_orb = $stats_orb_chunk, stats_drb = $stats_drb_chunk, stats_ast = $stats_ast_chunk, stats_stl = $stats_stl_chunk, stats_to = $stats_to_chunk, stats_blk = $stats_blk_chunk, stats_pf = $stats_pf_chunk, qa = $qa WHERE pid = $pid AND chunk = $new_chunk";
	echo "Updating $name's records... $qa<br>";
	$result3=$db->sql_query($query3);

	$i++;
}

function sum_chunk_stats ($stat, $player_id, $stats_total)
{
	global $db;

	$query2="SELECT SUM($stat) AS total_games FROM nuke_iblplyr_chunk WHERE pid='$player_id' AND active = 1";
	$result2=$db->sql_query($query2);
	$row_sum = $db->sql_fetch_assoc($result2);
	$new_chunk_stat = $stats_total - $row_sum[total_games];
	return $new_chunk_stat;
}

?>
