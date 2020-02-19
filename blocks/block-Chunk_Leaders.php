<?php
/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2005 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if ( !defined('BLOCK_FILE') ) {
    Header("Location: ./index.php");
    die();
}

global $prefix, $multilingual, $currentlang, $db;
$content=$content."<center><a href=modules.php?name=Chunk_Stats&op=chunk>Sim Stats Search Engine</a></center><br>";

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$max_chunk_query="SELECT MAX(chunk) as maxchunk FROM nuke_iblplyr_chunk WHERE active = 1";
$max_chunk_result=mysql_query($max_chunk_query);
$row = mysql_fetch_assoc($max_chunk_result);

//$query2="SELECT * FROM nuke_iblplyr_chunk WHERE chunk = $row[maxchunk] ORDER BY pid ASC";
//$result2 = mysql_query($query2);
//$new_name=mysql_result($result2,0,"name");

$query="SELECT * FROM nuke_iblplyr_chunk WHERE chunk = $row[maxchunk] ORDER BY pid ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);
$i=0;
$name1 = "";
$name2 = "";
$name3 = "";
$name4 = "";
$name5 = "";

$ppg1 = 0;
$ppg2 = 0;
$ppg3 = 0;
$ppg4 = 0;
$ppg5 = 0;

while ($i < $num)
{

	$name=mysql_result($result,$i,"name");
	//$p_ord=mysql_result($result,$i,"ordinal");
	$pid=mysql_result($result,$i,"pid");
	$tid=mysql_result($result,$i,"tid");
	$teamname=mysql_result($result,$i,"teamname");

	$stats_gm=mysql_result($result,$i,"stats_gm");
	$stats_fgm=mysql_result($result,$i,"stats_fgm");
	$stats_fga=mysql_result($result,$i,"stats_fga");
	$stats_ftm=mysql_result($result,$i,"stats_ftm");
	$stats_fta=mysql_result($result,$i,"stats_fta");
	$stats_tgm=mysql_result($result,$i,"stats_3gm");
	$stats_tga=mysql_result($result,$i,"stats_3ga");

	$stats_orb=mysql_result($result,$i,"stats_orb");
	$stats_drb=mysql_result($result,$i,"stats_drb");

	$stats_ast=mysql_result($result,$i,"stats_ast");

	$stats_stl=mysql_result($result,$i,"stats_stl");

	$stats_blk=mysql_result($result,$i,"stats_blk");

	$stats_to=mysql_result($result,$i,"stats_to");

	$stats_pf=mysql_result($result,$i,"stats_pf");


	$stats_reb=$stats_orb+$stats_drb;
	$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;

	@$stats_ppg=($stats_pts/$stats_gm);
	@$stats_reb=($stats_reb/$stats_gm);
	@$stats_ast=($stats_ast/$stats_gm);
	@$stats_stl=($stats_stl/$stats_gm);
	@$stats_blk=($stats_blk/$stats_gm);
    @$stats_fgt=($stats_fgm/$stats_gm);
    @$stats_ftm=($stats_ftm/$stats_gm);
	@$stats_tpg=($stats_tgm/$stats_gm);
    @$stats_to=($stats_to/$stats_gm);
    @$stats_pf=($stats_pf/$stats_gm);

	$stats_ppg = round($stats_ppg, 2);
	$stats_reb = round($stats_reb, 2);
	$stats_ast = round($stats_ast, 2);
	$stats_stl = round($stats_stl, 2);
	$stats_blk = round($stats_blk, 2);
	$stats_ftm = round($stats_ftm, 2);
	$stats_tpg = round($stats_tpg, 2);

	if ($stats_ppg > $ppg1)
	{
		$ppg5 = $ppg4;
		$ppg4 = $ppg3;
		$ppg3 = $ppg2;
		$ppg2 = $ppg1;
		$ppg1 = $stats_ppg;
		$name5 = $name4;
		$name4 = $name3;
		$name3 = $name2;
		$name2 = $name1;
		$name1 = $name;
		$teamname5 = $teamname4;
		$teamname4 = $teamname3;
		$teamname3 = $teamname2;
		$teamname2 = $teamname1;
		$teamname1 = $teamname;
		$pid5 = $pid4;
		$pid4 = $pid3;
		$pid3 = $pid2;
		$pid2 = $pid1;
		$pid1 = $pid;
		$tid5 = $tid4;
		$tid4 = $tid3;
		$tid3 = $tid2;
		$tid2 = $tid1;
		$tid1 = $tid;
	}
	elseif ($stats_ppg > $ppg2)
	{
		$ppg5 = $ppg4;
		$ppg4 = $ppg3;
		$ppg3 = $ppg2;
		$ppg2 = $stats_ppg;
		$name5 = $name4;
		$name4 = $name3;
		$name3 = $name2;
		$name2 = $name;
		$teamname5 = $teamname4;
		$teamname4 = $teamname3;
		$teamname3 = $teamname2;
		$teamname2 = $teamname;
		$pid5 = $pid4;
		$pid4 = $pid3;
		$pid3 = $pid2;
		$pid2 = $pid;
		$tid5 = $tid4;
		$tid4 = $tid3;
		$tid3 = $tid2;
		$tid2 = $tid;

	}
	elseif ($stats_ppg > $ppg3)
	{
		$ppg5 = $ppg4;
		$ppg4 = $ppg3;
		$ppg3 = $stats_ppg;
		$name5 = $name4;
		$name4 = $name3;
		$name3 = $name;
		$teamname5 = $teamname4;
		$teamname4 = $teamname3;
		$teamname3 = $teamname;
		$pid5 = $pid4;
		$pid4 = $pid3;
		$pid3 = $pid;
		$tid5 = $tid4;
		$tid4 = $tid3;
		$tid3 = $tid;
	}
	elseif ($stats_ppg > $ppg4)
	{
		$ppg5 = $ppg4;
		$ppg4 = $stats_ppg;
		$name5 = $name4;
		$name4 = $name;
		$teamname5 = $teamname4;
		$teamname4 = $teamname;
		$pid5 = $pid4;
		$pid4 = $pid;
		$tid5 = $tid4;
		$tid4 = $tid;
	}
	elseif ($stats_ppg > $ppg5)
	{
		$ppg5 = $stats_ppg;
		$name5 = $name;
		$teamname5 = $teamname;
		$pid5 = $pid;
		$tid5 = $tid;
	}

//----REB---

	if ($stats_reb > $reb1)
	{
		$reb5 = $reb4;
		$reb4 = $reb3;
		$reb3 = $reb2;
		$reb2 = $reb1;
		$reb1 = $stats_reb;
		$name_reb5 = $name_reb4;
		$name_reb4 = $name_reb3;
		$name_reb3 = $name_reb2;
		$name_reb2 = $name_reb1;
		$name_reb1 = $name;
		$teamname_reb5 = $teamname_reb4;
		$teamname_reb4 = $teamname_reb3;
		$teamname_reb3 = $teamname_reb2;
		$teamname_reb2 = $teamname_reb1;
		$teamname_reb1 = $teamname;
		$pidreb5 = $pidreb4;
		$pidreb4 = $pidreb3;
		$pidreb3 = $pidreb2;
		$pidreb2 = $pidreb1;
		$pidreb1 = $pid;
		$tidreb5 = $tidreb4;
		$tidreb4 = $tidreb3;
		$tidreb3 = $tidreb2;
		$tidreb2 = $tidreb1;
		$tidreb1 = $tid;
	}
	elseif ($stats_reb > $reb2)
	{
		$reb5 = $reb4;
		$reb4 = $reb3;
		$reb3 = $reb2;
		$reb2 = $stats_reb;
		$name_reb5 = $name_reb4;
		$name_reb4 = $name_reb3;
		$name_reb3 = $name_reb2;
		$name_reb2 = $name;
		$teamname_reb5 = $teamname_reb4;
		$teamname_reb4 = $teamname_reb3;
		$teamname_reb3 = $teamname_reb2;
		$teamname_reb2 = $teamname;
		$pidreb5 = $pidreb4;
		$pidreb4 = $pidreb3;
		$pidreb3 = $pidreb2;
		$pidreb2 = $pid;
		$tidreb5 = $tidreb4;
		$tidreb4 = $tidreb3;
		$tidreb3 = $tidreb2;
		$tidreb2 = $tid;
	}
	elseif ($stats_reb > $reb3)
	{
		$reb5 = $reb4;
		$reb4 = $reb3;
		$reb3 = $stats_reb;
		$name_reb5 = $name_reb4;
		$name_reb4 = $name_reb3;
		$name_reb3 = $name;
		$teamname_reb5 = $teamname_reb4;
		$teamname_reb4 = $teamname_reb3;
		$teamname_reb3 = $teamname;
		$pidreb5 = $pidreb4;
		$pidreb4 = $pidreb3;
		$pidreb3 = $pid;
		$tidreb5 = $tidreb4;
		$tidreb4 = $tidreb3;
		$tidreb3 = $tid;
	}
	elseif ($stats_reb > $reb4)
	{
		$reb5 = $reb4;
		$reb4 = $stats_reb;
		$name_reb5 = $name_reb4;
		$name_reb4 = $name;
		$teamname_reb5 = $teamname_reb4;
		$teamname_reb4 = $teamname;
		$pidreb5 = $pidreb4;
		$pidreb4 = $pid;
		$tidreb5 = $tidreb4;
		$tidreb4 = $tid;
	}
	elseif ($stats_reb > $reb5)
	{
		$reb5 = $stats_reb;
		$name_reb5 = $name;
		$teamname_reb5 = $teamname;
		$pidreb5 = $pid;
		$tidreb5 = $tid;
	}

//----AST---

	if ($stats_ast > $ast1)
	{
		$ast5 = $ast4;
		$ast4 = $ast3;
		$ast3 = $ast2;
		$ast2 = $ast1;
		$ast1 = $stats_ast;
		$name_ast5 = $name_ast4;
		$name_ast4 = $name_ast3;
		$name_ast3 = $name_ast2;
		$name_ast2 = $name_ast1;
		$name_ast1 = $name;
		$teamname_ast5 = $teamname_ast4;
		$teamname_ast4 = $teamname_ast3;
		$teamname_ast3 = $teamname_ast2;
		$teamname_ast2 = $teamname_ast1;
		$teamname_ast1 = $teamname;
		$pidast5 = $pidast4;
		$pidast4 = $pidast3;
		$pidast3 = $pidast2;
		$pidast2 = $pidast1;
		$pidast1 = $pid;
		$tidast5 = $tidast4;
		$tidast4 = $tidast3;
		$tidast3 = $tidast2;
		$tidast2 = $tidast1;
		$tidast1 = $tid;
	}
	elseif ($stats_ast > $ast2)
	{
		$ast5 = $ast4;
		$ast4 = $ast3;
		$ast3 = $ast2;
		$ast2 = $stats_ast;
		$name_ast5 = $name_ast4;
		$name_ast4 = $name_ast3;
		$name_ast3 = $name_ast2;
		$name_ast2 = $name;
		$teamname_ast5 = $teamname_ast4;
		$teamname_ast4 = $teamname_ast3;
		$teamname_ast3 = $teamname_ast2;
		$teamname_ast2 = $teamname;
		$pidast5 = $pidast4;
		$pidast4 = $pidast3;
		$pidast3 = $pidast2;
		$pidast2 = $pid;
		$tidast5 = $tidast4;
		$tidast4 = $tidast3;
		$tidast3 = $tidast2;
		$tidast2 = $tid;
	}
	elseif ($stats_ast > $ast3)
	{
		$ast5 = $ast4;
		$ast4 = $ast3;
		$ast3 = $stats_ast;
		$name_ast5 = $name_ast4;
		$name_ast4 = $name_ast3;
		$name_ast3 = $name;
		$teamname_ast5 = $teamname_ast4;
		$teamname_ast4 = $teamname_ast3;
		$teamname_ast3 = $teamname;
		$pidast5 = $pidast4;
		$pidast4 = $pidast3;
		$pidast3 = $pid;
		$tidast5 = $tidast4;
		$tidast4 = $tidast3;
		$tidast3 = $tid;
	}
	elseif ($stats_ast > $ast4)
	{
		$ast5 = $ast4;
		$ast4 = $stats_ast;
		$name_ast5 = $name_ast4;
		$name_ast4 = $name;
		$teamname_ast5 = $teamname_ast4;
		$teamname_ast4 = $teamname;
		$pidast5 = $pidast4;
		$pidast4 = $pid;
		$tidast5 = $tidast4;
		$tidast4 = $tid;
	}
	elseif ($stats_ast > $ast5)
	{
		$ast5 = $stats_ast;
		$name_ast5 = $name;
		$teamname_ast5 = $teamname;
		$pidast5 = $pid;
		$tidast5 = $tid;
	}

//----STL---

	if ($stats_stl > $stl1)
	{
		$stl5 = $stl4;
		$stl4 = $stl3;
		$stl3 = $stl2;
		$stl2 = $stl1;
		$stl1 = $stats_stl;
		$name_stl5 = $name_stl4;
		$name_stl4 = $name_stl3;
		$name_stl3 = $name_stl2;
		$name_stl2 = $name_stl1;
		$name_stl1 = $name;
		$teamname_stl5 = $teamname_stl4;
		$teamname_stl4 = $teamname_stl3;
		$teamname_stl3 = $teamname_stl2;
		$teamname_stl2 = $teamname_stl1;
		$teamname_stl1 = $teamname;
		$pidstl5 = $pidstl4;
		$pidstl4 = $pidstl3;
		$pidstl3 = $pidstl2;
		$pidstl2 = $pidstl1;
		$pidstl1 = $pid;
		$tidstl5 = $tidstl4;
		$tidstl4 = $tidstl3;
		$tidstl3 = $tidstl2;
		$tidstl2 = $tidstl1;
		$tidstl1 = $tid;
	}
	elseif ($stats_stl > $stl2)
	{
		$stl5 = $stl4;
		$stl4 = $stl3;
		$stl3 = $stl2;
		$stl2 = $stats_stl;
		$name_stl5 = $name_stl4;
		$name_stl4 = $name_stl3;
		$name_stl3 = $name_stl2;
		$name_stl2 = $name;
		$teamname_stl5 = $teamname_stl4;
		$teamname_stl4 = $teamname_stl3;
		$teamname_stl3 = $teamname_stl2;
		$teamname_stl2 = $teamname;
		$pidstl5 = $pidstl4;
		$pidstl4 = $pidstl3;
		$pidstl3 = $pidstl2;
		$pidstl2 = $pid;
		$tidstl5 = $tidstl4;
		$tidstl4 = $tidstl3;
		$tidstl3 = $tidstl2;
		$tidstl2 = $tid;
	}
	elseif ($stats_stl > $stl3)
	{
		$stl5 = $stl4;
		$stl4 = $stl3;
		$stl3 = $stats_stl;
		$name_stl5 = $name_stl4;
		$name_stl4 = $name_stl3;
		$name_stl3 = $name;
		$teamname_stl5 = $teamname_stl4;
		$teamname_stl4 = $teamname_stl3;
		$teamname_stl3 = $teamname;
		$pidstl5 = $pidstl4;
		$pidstl4 = $pidstl3;
		$pidstl3 = $pid;
		$tidstl5 = $tidstl4;
		$tidstl4 = $tidstl3;
		$tidstl3 = $tid;
	}
	elseif ($stats_stl > $stl4)
	{
		$stl5 = $stl4;
		$stl4 = $stats_stl;
		$name_stl5 = $name_stl4;
		$name_stl4 = $name;
		$teamname_stl5 = $teamname_stl4;
		$teamname_stl4 = $teamname;
		$pidstl5 = $pidstl4;
		$pidstl4 = $pid;
		$tidstl5 = $tidstl4;
		$tidstl4 = $tid;
	}
	elseif ($stats_stl > $stl5)
	{
		$stl5 = $stats_stl;
		$name_stl5 = $name;
		$teamname_stl5 = $teamname;
		$pidstl5 = $pid;
		$tidstl5 = $tid;
	}

//----BLK---

	if ($stats_blk > $blk1)
	{
		$blk5 = $blk4;
		$blk4 = $blk3;
		$blk3 = $blk2;
		$blk2 = $blk1;
		$blk1 = $stats_blk;
		$name_blk5 = $name_blk4;
		$name_blk4 = $name_blk3;
		$name_blk3 = $name_blk2;
		$name_blk2 = $name_blk1;
		$name_blk1 = $name;
		$teamname_blk5 = $teamname_blk4;
		$teamname_blk4 = $teamname_blk3;
		$teamname_blk3 = $teamname_blk2;
		$teamname_blk2 = $teamname_blk1;
		$teamname_blk1 = $teamname;
		$pidblk5 = $pidblk4;
		$pidblk4 = $pidblk3;
		$pidblk3 = $pidblk2;
		$pidblk2 = $pidblk1;
		$pidblk1 = $pid;
		$tidblk5 = $tidblk4;
		$tidblk4 = $tidblk3;
		$tidblk3 = $tidblk2;
		$tidblk2 = $tidblk1;
		$tidblk1 = $tid;	}
	elseif ($stats_blk > $blk2)
	{
		$blk5 = $blk4;
		$blk4 = $blk3;
		$blk3 = $blk2;
		$blk2 = $stats_blk;
		$name_blk5 = $name_blk4;
		$name_blk4 = $name_blk3;
		$name_blk3 = $name_blk2;
		$name_blk2 = $name;
		$teamname_blk5 = $teamname_blk4;
		$teamname_blk4 = $teamname_blk3;
		$teamname_blk3 = $teamname_blk2;
		$teamname_blk2 = $teamname;
		$pidblk5 = $pidblk4;
		$pidblk4 = $pidblk3;
		$pidblk3 = $pidblk2;
		$pidblk2 = $pid;
		$tidblk5 = $tidblk4;
		$tidblk4 = $tidblk3;
		$tidblk3 = $tidblk2;
		$tidblk2 = $tid;
	}
	elseif ($stats_blk > $blk3)
	{
		$blk5 = $blk4;
		$blk4 = $blk3;
		$blk3 = $stats_blk;
		$name_blk5 = $name_blk4;
		$name_blk4 = $name_blk3;
		$name_blk3 = $name;
		$teamname_blk5 = $teamname_blk4;
		$teamname_blk4 = $teamname_blk3;
		$teamname_blk3 = $teamname;
		$pidblk5 = $pidblk4;
		$pidblk4 = $pidblk3;
		$pidblk3 = $pid;
		$tidblk5 = $tidblk4;
		$tidblk4 = $tidblk3;
		$tidblk3 = $tid;
	}
	elseif ($stats_blk > $blk4)
	{
		$blk5 = $blk4;
		$blk4 = $stats_blk;
		$name_blk5 = $name_blk4;
		$name_blk4 = $name;
		$teamname_blk5 = $teamname_blk4;
		$teamname_blk4 = $teamname;
		$pidblk5 = $pidblk4;
		$pidblk4 = $pid;
		$tidblk5 = $tidblk4;
		$tidblk4 = $tid;
	}
	elseif ($stats_blk > $blk5)
	{
		$blk5 = $stats_blk;
		$name_blk5 = $name;
		$teamname_blk5 = $teamname;
		$pidblk5 = $pid;
		$tidblk5 = $tid;
	}

					//----3G<---

						if ($stats_fgt > $fgt1)
						{
							$fgt5 = $fgt4;
							$fgt4 = $fgt3;
							$fgt3 = $fgt2;
							$fgt2 = $fgt1;
							$fgt1 = $stats_fgt;
							$name_fgt5 = $name_fgt4;
							$name_fgt4 = $name_fgt3;
							$name_fgt3 = $name_fgt2;
							$name_fgt2 = $name_fgt1;
							$name_fgt1 = $name;
							$teamname_fgt5 = $teamname_fgt4;
							$teamname_fgt4 = $teamname_fgt3;
							$teamname_fgt3 = $teamname_fgt2;
							$teamname_fgt2 = $teamname_fgt1;
							$teamname_fgt1 = $teamname;
							$pidfgt5 = $pidfgt4;
							$pidfgt4 = $pidfgt3;
							$pidfgt3 = $pidfgt2;
							$pidfgt2 = $pidfgt1;
							$pidfgt1 = $pid;
							$tidfgt5 = $tidfgt4;
							$tidfgt4 = $tidfgt3;
							$tidfgt3 = $tidfgt2;
							$tidfgt2 = $tidfgt1;
							$tidfgt1 = $tid;
						}
								elseif ($stats_fgt > $fgt2)
								{
									$fgt5 = $fgt4;
									$fgt4 = $fgt3;
									$fgt3 = $fgt2;
									$fgt2 = $stats_fgt;
									$name_fgt5 = $name_fgt4;
									$name_fgt4 = $name_fgt3;
									$name_fgt3 = $name_fgt2;
									$name_fgt2 = $name;
									$teamname_fgt5 = $teamname_fgt4;
									$teamname_fgt4 = $teamname_fgt3;
									$teamname_fgt3 = $teamname_fgt2;
									$teamname_fgt2 = $teamname;
									$pidfgt5 = $pidfgt4;
									$pidfgt4 = $pidfgt3;
									$pidfgt3 = $pidfgt2;
									$pidfgt2 = $pid;
									$tidfgt5 = $tidfgt4;
									$tidfgt4 = $tidfgt3;
									$tidfgt3 = $tidfgt2;
									$tidfgt2 = $tid;
								}
								elseif ($stats_fgt > $fgt3)
								{
									$fgt5 = $fgt4;
									$fgt4 = $fgt3;
									$fgt3 = $stats_fgt;
									$name_fgt5 = $name_fgt4;
									$name_fgt4 = $name_fgt3;
									$name_fgt3 = $name;
									$teamname_fgt5 = $teamname_fgt4;
									$teamname_fgt4 = $teamname_fgt3;
									$teamname_fgt3 = $teamname;
									$pidfgt5 = $pidfgt4;
									$pidfgt4 = $pidfgt3;
									$pidfgt3 = $pid;
									$tidfgt5 = $tidfgt4;
									$tidfgt4 = $tidfgt3;
									$tidfgt3 = $tid;
								}
								elseif ($stats_fgt > $fgt4)
								{
									$fgt5 = $fgt4;
									$fgt4 = $stats_fgt;
									$name_fgt5 = $name_fgt4;
									$name_fgt4 = $name;
									$teamname_fgt5 = $teamname_fgt4;
									$teamname_fgt4 = $teamname;
									$pidfgt5 = $pidfgt4;
									$pidfgt4 = $pid;
									$tidfgt5 = $tidfgt4;
									$tidfgt4 = $tid;
								}
								elseif ($stats_fgt > $fgt5)
								{
									$fgt5 = $stats_fgt;
									$name_fgt5 = $name;
									$teamname_fgt5 = $teamname;
									$pidfgt5 = $pid;
							$tidfgt5 = $tid;
			}

			//----FT<---

						if ($stats_ftm > $ftm1)
						{
							$ftm5 = $ftm4;
							$ftm4 = $ftm3;
							$ftm3 = $ftm2;
							$ftm2 = $ftm1;
							$ftm1 = $stats_ftm;
							$name_ftm5 = $name_ftm4;
							$name_ftm4 = $name_ftm3;
							$name_ftm3 = $name_ftm2;
							$name_ftm2 = $name_ftm1;
							$name_ftm1 = $name;
							$teamname_ftm5 = $teamname_ftm4;
							$teamname_ftm4 = $teamname_ftm3;
							$teamname_ftm3 = $teamname_ftm2;
							$teamname_ftm2 = $teamname_ftm1;
							$teamname_ftm1 = $teamname;
							$pidftm5 = $pidftm4;
							$pidftm4 = $pidftm3;
							$pidftm3 = $pidftm2;
							$pidftm2 = $pidftm1;
							$pidftm1 = $pid;
							$tidftm5 = $tidftm4;
							$tidftm4 = $tidftm3;
							$tidftm3 = $tidftm2;
							$tidftm2 = $tidftm1;
							$tidftm1 = $tid;
						}
								elseif ($stats_ftm > $ftm2)
								{
									$ftm5 = $ftm4;
									$ftm4 = $ftm3;
									$ftm3 = $ftm2;
									$ftm2 = $stats_ftm;
									$name_ftm5 = $name_ftm4;
									$name_ftm4 = $name_ftm3;
									$name_ftm3 = $name_ftm2;
									$name_ftm2 = $name;
									$teamname_ftm5 = $teamname_ftm4;
									$teamname_ftm4 = $teamname_ftm3;
									$teamname_ftm3 = $teamname_ftm2;
									$teamname_ftm2 = $teamname;
									$pidftm5 = $pidftm4;
									$pidftm4 = $pidftm3;
									$pidftm3 = $pidftm2;
									$pidftm2 = $pid;
									$tidftm5 = $tidftm4;
									$tidftm4 = $tidftm3;
									$tidftm3 = $tidftm2;
									$tidftm2 = $tid;
								}
								elseif ($stats_ftm > $ftm3)
								{
									$ftm5 = $ftm4;
									$ftm4 = $ftm3;
									$ftm3 = $stats_ftm;
									$name_ftm5 = $name_ftm4;
									$name_ftm4 = $name_ftm3;
									$name_ftm3 = $name;
									$teamname_ftm5 = $teamname_ftm4;
									$teamname_ftm4 = $teamname_ftm3;
									$teamname_ftm3 = $teamname;
									$pidftm5 = $pidftm4;
									$pidftm4 = $pidftm3;
									$pidftm3 = $pid;
									$tidftm5 = $tidftm4;
									$tidftm4 = $tidftm3;
									$tidftm3 = $tid;
								}
								elseif ($stats_ftm > $ftm4)
								{
									$ftm5 = $ftm4;
									$ftm4 = $stats_ftm;
									$name_ftm5 = $name_ftm4;
									$name_ftm4 = $name;
									$teamname_ftm5 = $teamname_ftm4;
									$teamname_ftm4 = $teamname;
									$pidftm5 = $pidftm4;
									$pidftm4 = $pid;
									$tidftm5 = $tidftm4;
									$tidftm4 = $tid;
								}
								elseif ($stats_ftm > $ftm5)
								{
									$ftm5 = $stats_ftm;
									$name_ftm5 = $name;
									$teamname_ftm5 = $teamname;
									$pidftm5 = $pid;
							$tidftm5 = $tid;
			}

			//----3G<---

				if ($stats_tpg > $tpg1)
				{
					$tpg5 = $tpg4;
					$tpg4 = $tpg3;
					$tpg3 = $tpg2;
					$tpg2 = $tpg1;
					$tpg1 = $stats_tpg;
					$name_tpg5 = $name_tpg4;
					$name_tpg4 = $name_tpg3;
					$name_tpg3 = $name_tpg2;
					$name_tpg2 = $name_tpg1;
					$name_tpg1 = $name;
					$teamname_tpg5 = $teamname_tpg4;
					$teamname_tpg4 = $teamname_tpg3;
					$teamname_tpg3 = $teamname_tpg2;
					$teamname_tpg2 = $teamname_tpg1;
					$teamname_tpg1 = $teamname;
					$pidtpg5 = $pidtpg4;
					$pidtpg4 = $pidtpg3;
					$pidtpg3 = $pidtpg2;
					$pidtpg2 = $pidtpg1;
					$pidtpg1 = $pid;
					$tidtpg5 = $tidtpg4;
					$tidtpg4 = $tidtpg3;
					$tidtpg3 = $tidtpg2;
					$tidtpg2 = $tidtpg1;
					$tidtpg1 = $tid;
				}
						elseif ($stats_tpg > $tpg2)
						{
							$tpg5 = $tpg4;
							$tpg4 = $tpg3;
							$tpg3 = $tpg2;
							$tpg2 = $stats_tpg;
							$name_tpg5 = $name_tpg4;
							$name_tpg4 = $name_tpg3;
							$name_tpg3 = $name_tpg2;
							$name_tpg2 = $name;
							$teamname_tpg5 = $teamname_tpg4;
							$teamname_tpg4 = $teamname_tpg3;
							$teamname_tpg3 = $teamname_tpg2;
							$teamname_tpg2 = $teamname;
							$pidtpg5 = $pidtpg4;
							$pidtpg4 = $pidtpg3;
							$pidtpg3 = $pidtpg2;
							$pidtpg2 = $pid;
							$tidtpg5 = $tidtpg4;
							$tidtpg4 = $tidtpg3;
							$tidtpg3 = $tidtpg2;
							$tidtpg2 = $tid;
						}
						elseif ($stats_tpg > $tpg3)
						{
							$tpg5 = $tpg4;
							$tpg4 = $tpg3;
							$tpg3 = $stats_tpg;
							$name_tpg5 = $name_tpg4;
							$name_tpg4 = $name_tpg3;
							$name_tpg3 = $name;
							$teamname_tpg5 = $teamname_tpg4;
							$teamname_tpg4 = $teamname_tpg3;
							$teamname_tpg3 = $teamname;
							$pidtpg5 = $pidtpg4;
							$pidtpg4 = $pidtpg3;
							$pidtpg3 = $pid;
							$tidtpg5 = $tidtpg4;
							$tidtpg4 = $tidtpg3;
							$tidtpg3 = $tid;
						}
						elseif ($stats_tpg > $tpg4)
						{
							$tpg5 = $tpg4;
							$tpg4 = $stats_tpg;
							$name_tpg5 = $name_tpg4;
							$name_tpg4 = $name;
							$teamname_tpg5 = $teamname_tpg4;
							$teamname_tpg4 = $teamname;
							$pidtpg5 = $pidtpg4;
							$pidtpg4 = $pid;
							$tidtpg5 = $tidtpg4;
							$tidtpg4 = $tid;
						}
						elseif ($stats_tpg > $tpg5)
						{
							$tpg5 = $stats_tpg;
							$name_tpg5 = $name;
							$teamname_tpg5 = $teamname;
							$pidtpg5 = $pid;
					$tidtpg5 = $tid;
			}

							//----TPG<---

								if ($stats_to > $to1)
								{
									$to5 = $to4;
									$to4 = $to3;
									$to3 = $to2;
									$to2 = $to1;
									$to1 = $stats_to;
									$name_to5 = $name_to4;
									$name_to4 = $name_to3;
									$name_to3 = $name_to2;
									$name_to2 = $name_to1;
									$name_to1 = $name;
									$teamname_to5 = $teamname_to4;
									$teamname_to4 = $teamname_to3;
									$teamname_to3 = $teamname_to2;
									$teamname_to2 = $teamname_to1;
									$teamname_to1 = $teamname;
									$pidto5 = $pidto4;
									$pidto4 = $pidto3;
									$pidto3 = $pidto2;
									$pidto2 = $pidto1;
									$pidto1 = $pid;
									$tidto5 = $tidto4;
									$tidto4 = $tidto3;
									$tidto3 = $tidto2;
									$tidto2 = $tidto1;
									$tidto1 = $tid;
								}
										elseif ($stats_to > $to2)
										{
											$to5 = $to4;
											$to4 = $to3;
											$to3 = $to2;
											$to2 = $stats_to;
											$name_to5 = $name_to4;
											$name_to4 = $name_to3;
											$name_to3 = $name_to2;
											$name_to2 = $name;
											$teamname_to5 = $teamname_to4;
											$teamname_to4 = $teamname_to3;
											$teamname_to3 = $teamname_to2;
											$teamname_to2 = $teamname;
											$pidto5 = $pidto4;
											$pidto4 = $pidto3;
											$pidto3 = $pidto2;
											$pidto2 = $pid;
											$tidto5 = $tidto4;
											$tidto4 = $tidto3;
											$tidto3 = $tidto2;
											$tidto2 = $tid;
										}
										elseif ($stats_to > $to3)
										{
											$to5 = $to4;
											$to4 = $to3;
											$to3 = $stats_to;
											$name_to5 = $name_to4;
											$name_to4 = $name_to3;
											$name_to3 = $name;
											$teamname_to5 = $teamname_to4;
											$teamname_to4 = $teamname_to3;
											$teamname_to3 = $teamname;
											$pidto5 = $pidto4;
											$pidto4 = $pidto3;
											$pidto3 = $pid;
											$tidto5 = $tidto4;
											$tidto4 = $tidto3;
											$tidto3 = $tid;
										}
										elseif ($stats_to > $to4)
										{
											$to5 = $to4;
											$to4 = $stats_to;
											$name_to5 = $name_to4;
											$name_to4 = $name;
											$teamname_to5 = $teamname_to4;
											$teamname_to4 = $teamname;
											$pidto5 = $pidto4;
											$pidto4 = $pid;
											$tidto5 = $tidto4;
											$tidto4 = $tid;
										}
										elseif ($stats_to > $to5)
										{
											$to5 = $stats_to;
											$name_to5 = $name;
											$teamname_to5 = $teamname;
											$pidto5 = $pid;
									$tidto5 = $tid;
			}

							//----FPG<---

								if ($stats_pf > $pf1)
								{
									$pf5 = $pf4;
									$pf4 = $pf3;
									$pf3 = $pf2;
									$pf2 = $pf1;
									$pf1 = $stats_pf;
									$name_pf5 = $name_pf4;
									$name_pf4 = $name_pf3;
									$name_pf3 = $name_pf2;
									$name_pf2 = $name_pf1;
									$name_pf1 = $name;
									$teamname_pf5 = $teamname_pf4;
									$teamname_pf4 = $teamname_pf3;
									$teamname_pf3 = $teamname_pf2;
									$teamname_pf2 = $teamname_pf1;
									$teamname_pf1 = $teamname;
									$pidpf5 = $pidpf4;
									$pidpf4 = $pidpf3;
									$pidpf3 = $pidpf2;
									$pidpf2 = $pidpf1;
									$pidpf1 = $pid;
									$tidpf5 = $tidpf4;
									$tidpf4 = $tidpf3;
									$tidpf3 = $tidpf2;
									$tidpf2 = $tidpf1;
									$tidpf1 = $tid;
								}
										elseif ($stats_pf > $pf2)
										{
											$pf5 = $pf4;
											$pf4 = $pf3;
											$pf3 = $pf2;
											$pf2 = $stats_pf;
											$name_pf5 = $name_pf4;
											$name_pf4 = $name_pf3;
											$name_pf3 = $name_pf2;
											$name_pf2 = $name;
											$teamname_pf5 = $teamname_pf4;
											$teamname_pf4 = $teamname_pf3;
											$teamname_pf3 = $teamname_pf2;
											$teamname_pf2 = $teamname;
											$pidpf5 = $pidpf4;
											$pidpf4 = $pidpf3;
											$pidpf3 = $pidpf2;
											$pidpf2 = $pid;
											$tidpf5 = $tidpf4;
											$tidpf4 = $tidpf3;
											$tidpf3 = $tidpf2;
											$tidpf2 = $tid;
										}
										elseif ($stats_pf > $pf3)
										{
											$pf5 = $pf4;
											$pf4 = $pf3;
											$pf3 = $stats_pf;
											$name_pf5 = $name_pf4;
											$name_pf4 = $name_pf3;
											$name_pf3 = $name;
											$teamname_pf5 = $teamname_pf4;
											$teamname_pf4 = $teamname_pf3;
											$teamname_pf3 = $teamname;
											$pidpf5 = $pidpf4;
											$pidpf4 = $pidpf3;
											$pidpf3 = $pid;
											$tidpf5 = $tidpf4;
											$tidpf4 = $tidpf3;
											$tidpf3 = $tid;
										}
										elseif ($stats_pf > $pf4)
										{
											$pf5 = $pf4;
											$pf4 = $stats_pf;
											$name_pf5 = $name_pf4;
											$name_pf4 = $name;
											$teamname_pf5 = $teamname_pf4;
											$teamname_pf4 = $teamname;
											$pidpf5 = $pidpf4;
											$pidpf4 = $pid;
											$tidpf5 = $tidpf4;
											$tidpf4 = $tid;
										}
										elseif ($stats_pf > $pf5)
										{
											$pf5 = $stats_pf;
											$name_pf5 = $name;
											$teamname_pf5 = $teamname;
											$pidpf5 = $pid;
									$tidpf5 = $tid;
		}
	$i++;
}

$ppg1 = sprintf('%04.1f', $ppg1);
$ppg2 = sprintf('%04.1f', $ppg2);
$ppg3 = sprintf('%04.1f', $ppg3);
$ppg4 = sprintf('%04.1f', $ppg4);
$ppg5 = sprintf('%04.1f', $ppg5);

$reb1 = sprintf('%04.1f', $reb1);
$reb2 = sprintf('%04.1f', $reb2);
$reb3 = sprintf('%04.1f', $reb3);
$reb4 = sprintf('%04.1f', $reb4);
$reb5 = sprintf('%04.1f', $reb5);

$ast1 = sprintf('%02.1f', $ast1);
$ast2 = sprintf('%02.1f', $ast2);
$ast3 = sprintf('%02.1f', $ast3);
$ast4 = sprintf('%02.1f', $ast4);
$ast5 = sprintf('%02.1f', $ast5);

$stl1 = sprintf('%02.1f', $stl1);
$stl2 = sprintf('%02.1f', $stl2);
$stl3 = sprintf('%02.1f', $stl3);
$stl4 = sprintf('%02.1f', $stl4);
$stl5 = sprintf('%02.1f', $stl5);

$blk1 = sprintf('%02.1f', $blk1);
$blk2 = sprintf('%02.1f', $blk2);
$blk3 = sprintf('%02.1f', $blk3);
$blk4 = sprintf('%02.1f', $blk4);
$blk5 = sprintf('%02.1f', $blk5);

	$fgt1 = sprintf('%4.1f', $fgt1);
	$fgt2 = sprintf('%4.1f', $fgt2);
	$fgt3 = sprintf('%4.1f', $fgt3);
	$fgt4 = sprintf('%4.1f', $fgt4);
	$fgt5 = sprintf('%4.1f', $fgt5);

	$ftm1 = sprintf('%4.1f', $ftm1);
	$ftm2 = sprintf('%4.1f', $ftm2);
	$ftm3 = sprintf('%4.1f', $ftm3);
	$ftm4 = sprintf('%4.1f', $ftm4);
	$ftm5 = sprintf('%4.1f', $ftm5);

	$tpg1 = sprintf('%4.1f', $tpg1);
	$tpg2 = sprintf('%4.1f', $tpg2);
	$tpg3 = sprintf('%4.1f', $tpg3);
	$tpg4 = sprintf('%4.1f', $tpg4);
	$tpg5 = sprintf('%4.1f', $tpg5);

	$to1 = sprintf('%4.1f', $to1);
	$to2 = sprintf('%4.1f', $to2);
	$to3 = sprintf('%4.1f', $to3);
	$to4 = sprintf('%4.1f', $to4);
	$to5 = sprintf('%4.1f', $to5);

	$pf1 = sprintf('%4.1f', $pf1);
	$pf2 = sprintf('%4.1f', $pf2);
	$pf3 = sprintf('%4.1f', $pf3);
	$pf4 = sprintf('%4.1f', $pf4);
	$pf5 = sprintf('%4.1f', $pf5);

$content = $content."<center><table border=1 bordercolor=#000066><tr><td><table><tr><td colspan=2><center><img src=\"./images/player/$pid1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tid1.png\"></center></td></tr><tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Points Per Game</td></tr><tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pid1><font color=#000066>$name1</font></a><br><font color=#000066>$teamname1</font></td><td valign=top>$ppg1</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pid2><font color=#000066>$name2</font></a><br><font color=#000066>$teamname2</font></td><td valign=top>$ppg2</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pid3><font color=#000066>$name3</font></a><br><font color=#000066>$teamname3</font></td><td valign=top>$ppg3</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pid4><font color=#000066>$name4</font></a><br><font color=#000066>$teamname4</font></td><td valign=top>$ppg4</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pid5><font color=#000066>$name5</font></a><br><font color=#000066>$teamname5</font></td><td valign=top>$ppg5</td></tr>";
	$content = $content."</table></td>";

	$content = $content."<td><table><tr><td colspan=2><center><img src=\"./images/player/$pidreb1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tidreb1.png\"></center></td></tr><tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Rebounds Per Game</td></tr><tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pidreb1><font color=#000066>$name_reb1</font></a><br><font color=#000066>$teamname_reb1</font></td><td valign=top>$reb1</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidreb2><font color=#000066>$name_reb2</font></a><br><font color=#000066>$teamname_reb2</font></td><td valign=top>$reb2</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidreb3><font color=#000066>$name_reb3</font></a><br><font color=#000066>$teamname_reb3</font></td><td valign=top>$reb3</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidreb4><font color=#000066>$name_reb4</font></a><br><font color=#000066>$teamname_reb4</font></td><td valign=top>$reb4</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidreb5><font color=#000066>$name_reb5</font></a><br><font color=#000066>$teamname_reb5</font></td><td valign=top>$reb5</td></tr>";
	$content = $content."</table></td>";

	$content = $content."<td><table><tr><td colspan=2><center><img src=\"./images/player/$pidast1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tidast1.png\"></center></td></tr><tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Assists Per Game</td></tr><tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pidast1><font color=#000066>$name_ast1</font></a><br><font color=#000066>$teamname_ast1</font></td><td valign=top>$ast1</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidast2><font color=#000066>$name_ast2</font></a><br><font color=#000066>$teamname_ast2</font></td><td valign=top>$ast2</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidast3><font color=#000066>$name_ast3</font></a><br><font color=#000066>$teamname_ast3</font></td><td valign=top>$ast3</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidast4><font color=#000066>$name_ast4</font></a><br><font color=#000066>$teamname_ast4</font></td><td valign=top>$ast4</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidast5><font color=#000066>$name_ast5</font></a><br><font color=#000066>$teamname_ast5</font></td><td valign=top>$ast5</td></tr>";
	$content = $content."</table></td>";

	$content = $content."<td><table><tr><td colspan=2><center><img src=\"./images/player/$pidstl1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tidstl1.png\"></center></td></tr><tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Steals Per Game</td></tr><tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pidstl1><font color=#000066>$name_stl1</font></a><br><font color=#000066>$teamname_stl1</font></td><td valign=top>$stl1</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidstl2><font color=#000066>$name_stl2</font></a><br><font color=#000066>$teamname_stl2</font></td><td valign=top>$stl2</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidstl3><font color=#000066>$name_stl3</font></a><br><font color=#000066>$teamname_stl3</font></td><td valign=top>$stl3</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidstl4><font color=#000066>$name_stl4</font></a><br><font color=#000066>$teamname_stl4</font></td><td valign=top>$stl4</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidstl5><font color=#000066>$name_stl5</font></a><br><font color=#000066>$teamname_stl5</font></td><td valign=top>$stl5</td></tr>";
	$content = $content."</table></td>";

	$content = $content."<td><table><tr><td colspan=2><center><img src=\"./images/player/$pidblk1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tidblk1.png\"></center></td></tr><tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Blocks Per Game</td></tr><tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pidblk1><font color=#000066>$name_blk1</font></a><br><font color=#000066>$teamname_blk1</font></td><td valign=top>$blk1</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidblk2><font color=#000066>$name_blk2</font></a><br><font color=#000066>$teamname_blk2</font></td><td valign=top>$blk2</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidblk3><font color=#000066>$name_blk3</font></a><br><font color=#000066>$teamname_blk3</font></td><td valign=top>$blk3</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidblk4><font color=#000066>$name_blk4</font></a><br><font color=#000066>$teamname_blk4</font></td><td valign=top>$blk4</td></tr>";
	$content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidblk5><font color=#000066>$name_blk5</font></a><br><font color=#000066>$teamname_blk5</font></td><td valign=top>$blk5</td></tr>";
	$content = $content."</table></td></tr></table>";


?>
