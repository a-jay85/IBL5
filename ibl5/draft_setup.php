<?php

require 'config.php';
$dbname = "iblhoops_draft";
mysql_connect($dbhost, $dbuname, $dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$querya = "truncate table chat";
$resulta = $db->sql_query($querya);

$queryb = "truncate table chat_room";
$resultb = $db->sql_query($queryb);

$queryc = "truncate table selection";
$resultc = $db->sql_query($queryc);

$queryd = "update team set team_autopick = 0";
$resultd = $db->sql_query($queryd);

$querye = "update team set team_clock_adj = 1.00";
$resulte = $db->sql_query($querye);

$queryf = "update team set team_autopick_wait = 0";
$resultf = $db->sql_query($queryf);

$queryg = "update team set team_multipos = 0";
$resultg = $db->sql_query($queryg);

$queryh = "update pick set player_id = null";
$resulth = $db->sql_query($queryh);

$queryi = "UPDATE pick SET pick_time = null";
$resulti = $db->sql_query($queryi);

$queryj = "update pick set pick_start = null";
$resultj = $db->sql_query($queryj);

$queryk = "alter table nuke_scout_rookieratings drop column blah";
$resultk = $db->sql_query($queryk);

$queryl = "alter table nuke_scout_rookieratings drop column sta";
$resultl = $db->sql_query($queryl);

$querym = "alter table nuke_scout_rookieratings drop column invite";
$resultm = $db->sql_query($querym);

$queryn = "alter table nuke_scout_rookieratings drop column ranking";
$resultn = $db->sql_query($queryn);

$queryo = "alter table nuke_scout_rookieratings drop column team";
$resulto = $db->sql_query($queryo);

$queryp = "alter table nuke_scout_rookieratings drop column drafted";
$resultp = $db->sql_query($queryp);

$querys1 = "alter table nuke_scout_rookieratings ADD player_oo int(11)";
$results1 = $db->sql_query($querys1);

$querys2 = "update nuke_scout_rookieratings set player_oo = `offo`";
$results2 = $db->sql_query($querys2);

$querys3 = "alter table nuke_scout_rookieratings drop `offo`";
$results3 = $db->sql_query($querys3);

$querys4 = "alter table nuke_scout_rookieratings ADD player_do int(11)";
$results4 = $db->sql_query($querys4);

$querys5 = "update nuke_scout_rookieratings set player_do = `offd`";
$results5 = $db->sql_query($querys5);

$querys6 = "alter table nuke_scout_rookieratings drop `offd`";
$results6 = $db->sql_query($querys6);

$querys7 = "alter table nuke_scout_rookieratings ADD player_po int(11)";
$results7 = $db->sql_query($querys7);

$querys8 = "update nuke_scout_rookieratings set player_po = `offp`";
$results8 = $db->sql_query($querys8);

$querys9 = "alter table nuke_scout_rookieratings drop `offp`";
$results9 = $db->sql_query($querys9);

$querys10 = "alter table nuke_scout_rookieratings ADD player_to int(11)";
$results10 = $db->sql_query($querys10);

$querys11 = "update nuke_scout_rookieratings set player_to = `offt`";
$results11 = $db->sql_query($querys11);

$querys12 = "alter table nuke_scout_rookieratings drop `offt`";
$results12 = $db->sql_query($querys12);

$querys13 = "alter table nuke_scout_rookieratings ADD player_od int(11)";
$results13 = $db->sql_query($querys13);

$querys14 = "update nuke_scout_rookieratings set player_od = `defo`";
$results14 = $db->sql_query($querys14);

$querys15 = "alter table nuke_scout_rookieratings drop `defo`";
$results15 = $db->sql_query($querys15);

$querys16 = "alter table nuke_scout_rookieratings ADD player_dd int(11)";
$results16 = $db->sql_query($querys16);

$querys17 = "update nuke_scout_rookieratings set player_dd = `defd`";
$results17 = $db->sql_query($querys17);

$querys18 = "alter table nuke_scout_rookieratings drop `defd`";
$results18 = $db->sql_query($querys18);

$querys19 = "alter table nuke_scout_rookieratings ADD player_pd int(11)";
$results19 = $db->sql_query($querys19);

$querys20 = "update nuke_scout_rookieratings set player_pd = `defp`";
$results20 = $db->sql_query($querys20);

$querys21 = "alter table nuke_scout_rookieratings drop `defp`";
$results21 = $db->sql_query($querys21);

$querys22 = "alter table nuke_scout_rookieratings ADD player_td int(11)";
$results22 = $db->sql_query($querys22);

$querys23 = "update nuke_scout_rookieratings set player_td = `deft`";
$results23 = $db->sql_query($querys23);

$querys24 = "alter table nuke_scout_rookieratings drop `deft`";
$results24 = $db->sql_query($querys24);

$querys25 = "alter table nuke_scout_rookieratings ADD player_tal int(11)";
$results25 = $db->sql_query($querys25);

$querys26 = "update nuke_scout_rookieratings set player_tal = `tal`";
$results26 = $db->sql_query($querys26);

$querys27 = "alter table nuke_scout_rookieratings drop `tal`";
$results27 = $db->sql_query($querys27);

$querys28 = "alter table nuke_scout_rookieratings ADD player_skl int(11)";
$results28 = $db->sql_query($querys28);

$querys29 = "update nuke_scout_rookieratings set player_skl = `skl`";
$results29 = $db->sql_query($querys29);

$querys30 = "alter table nuke_scout_rookieratings drop `skl`";
$results30 = $db->sql_query($querys30);

$querys31 = "alter table nuke_scout_rookieratings ADD player_intan int(11)";
$results31 = $db->sql_query($querys31);

$querys32 = "update nuke_scout_rookieratings set player_intan = `int`";
$results32 = $db->sql_query($querys32);

$querys33 = "alter table nuke_scout_rookieratings drop `int`";
$results33 = $db->sql_query($querys33);

$queryt = "alter table nuke_scout_rookieratings ADD player_id int(2)";
$resultt = $db->sql_query($queryt);

$queryu = "alter table nuke_scout_rookieratings ADD player_name  varchar(50)";
$resultu = $db->sql_query($queryu);

$queryv = "update nuke_scout_rookieratings set player_name = name";
$resultv = $db->sql_query($queryv);

$queryw = "alter table nuke_scout_rookieratings drop name";
$resultw = $db->sql_query($queryw);

$queryx = "alter table nuke_scout_rookieratings ADD player_age  int(2)";
$resultx = $db->sql_query($queryx);

$queryy = "update nuke_scout_rookieratings set player_age = age";
$resulty = $db->sql_query($queryy);

$queryz = "alter table nuke_scout_rookieratings drop age";
$resultz = $db->sql_query($queryz);

$queryaa = "alter table nuke_scout_rookieratings ADD position_id  varchar(2)";
$resultaa = $db->sql_query($queryaa);

$querybb = "update nuke_scout_rookieratings set position_id = pos";
$resultbb = $db->sql_query($querybb);

$querycc = "alter table nuke_scout_rookieratings drop pos";
$resultcc = $db->sql_query($querycc);

$querydd = "alter table nuke_scout_rookieratings ADD player_fgp  varchar(2)";
$resultdd = $db->sql_query($querydd);

$queryee = "update nuke_scout_rookieratings set player_fgp = fgp";
$resultee = $db->sql_query($queryee);

$queryff = "alter table nuke_scout_rookieratings drop fgp";
$resultff = $db->sql_query($queryff);

$querygg = "alter table nuke_scout_rookieratings ADD player_fga  varchar(2)";
$resultgg = $db->sql_query($querygg);

$queryhh = "update nuke_scout_rookieratings set player_fga = fga";
$resulthh = $db->sql_query($queryhh);

$queryii = "alter table nuke_scout_rookieratings drop fga";
$resultii = $db->sql_query($queryii);

$queryjj = "alter table nuke_scout_rookieratings ADD player_ftp  varchar(2)";
$resultjj = $db->sql_query($queryjj);

$querykk = "update nuke_scout_rookieratings set player_ftp = ftp";
$resultkk = $db->sql_query($querykk);

$queryll = "alter table nuke_scout_rookieratings drop ftp";
$resultll = $db->sql_query($queryll);

$querymm = "alter table nuke_scout_rookieratings ADD player_fta  varchar(2)";
$resultmm = $db->sql_query($querymm);

$querynn = "update nuke_scout_rookieratings set player_fta = fta";
$resultnn = $db->sql_query($querynn);

$queryoo = "alter table nuke_scout_rookieratings drop fta";
$resultoo = $db->sql_query($queryoo);

$querypp = "alter table nuke_scout_rookieratings ADD player_tgp  varchar(2)";
$resultpp = $db->sql_query($querypp);

$queryqq = "update nuke_scout_rookieratings set player_tgp = tgp";
$resultqq = $db->sql_query($queryqq);

$queryrr = "alter table nuke_scout_rookieratings drop tgp";
$resultrr = $db->sql_query($queryrr);

$queryss = "alter table nuke_scout_rookieratings ADD player_tga  varchar(2)";
$resultss = $db->sql_query($queryss);

$querytt = "update nuke_scout_rookieratings set player_tga = tga";
$resulttt = $db->sql_query($querytt);

$queryuu = "alter table nuke_scout_rookieratings drop tga";
$resultuu = $db->sql_query($queryuu);

$queryvv = "alter table nuke_scout_rookieratings ADD player_orb  varchar(2)";
$resultvv = $db->sql_query($queryvv);

$queryww = "update nuke_scout_rookieratings set player_orb = orb";
$resultww = $db->sql_query($queryww);

$queryxx = "alter table nuke_scout_rookieratings drop orb";
$resultxx = $db->sql_query($queryxx);

$queryyy = "alter table nuke_scout_rookieratings ADD player_drb  varchar(2)";
$resultyy = $db->sql_query($queryyy);

$queryzz = "update nuke_scout_rookieratings set player_drb = drb";
$resultzz = $db->sql_query($queryzz);

$queryaaa = "alter table nuke_scout_rookieratings drop drb";
$resultaaa = $db->sql_query($queryaaa);

$querybbb = "alter table nuke_scout_rookieratings ADD player_ast  varchar(2)";
$resultbbb = $db->sql_query($querybbb);

$queryccc = "update nuke_scout_rookieratings set player_ast = ast";
$resultccc = $db->sql_query($queryccc);

$queryddd = "alter table nuke_scout_rookieratings drop ast";
$resultddd = $db->sql_query($queryddd);

$queryeee = "alter table nuke_scout_rookieratings ADD player_stl  varchar(2)";
$resulteee = $db->sql_query($queryeee);

$queryfff = "update nuke_scout_rookieratings set player_stl = stl";
$resultfff = $db->sql_query($queryfff);

$queryggg = "alter table nuke_scout_rookieratings drop stl";
$resultggg = $db->sql_query($queryggg);

$queryhhh = "alter table nuke_scout_rookieratings ADD player_tvr  varchar(2)";
$resulthhh = $db->sql_query($queryhhh);

$queryiii = "update nuke_scout_rookieratings set player_tvr = tvr";
$resultiii = $db->sql_query($queryiii);

$queryjjj = "alter table nuke_scout_rookieratings drop tvr";
$resultjjj = $db->sql_query($queryjjj);

$querykkk = "alter table nuke_scout_rookieratings ADD player_blk  varchar(2)";
$resultkkk = $db->sql_query($querykkk);

$querylll = "update nuke_scout_rookieratings set player_blk = blk";
$resultlll = $db->sql_query($querylll);

$querymmm = "alter table nuke_scout_rookieratings drop blk";
$resultmmm = $db->sql_query($querymmm);

$query6 = "update nuke_scout_rookieratings set position_id = '5' where position_id = 'C'";
$result6 = $db->sql_query($query6);

$query7 = "update nuke_scout_rookieratings set position_id = '4' where position_id = 'PF'";
$result7 = $db->sql_query($query7);

$query8 = "update nuke_scout_rookieratings set position_id = '3' where position_id = 'SF'";
$result8 = $db->sql_query($query8);

$query9 = "update nuke_scout_rookieratings set position_id = '2' where position_id = 'SG'";
$result9 = $db->sql_query($query9);

$query10 = "update nuke_scout_rookieratings set position_id = '1' where position_id = 'PG'";
$result10 = $db->sql_query($query10);

$query11 = "update nuke_scout_rookieratings set player_id = id";
$result11 = $db->sql_query($query11);

$query231 = "drop table player";
$result231 = $db->sql_query($query231);

$query232 = "alter table nuke_scout_rookieratings rename to player";
$result232 = $db->sql_query($query232);

$query233 = "update settings set setting_value = '0' where setting_id = '2'";
$result233 = $db->sql_query($query233);

echo "Draft-o-Matic setup is complete. Do NOT contact Joe. Seriously. Don't do it."

?>
