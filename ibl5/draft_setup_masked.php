<?php

require 'config.php';
$dbname = "iblhoops_draft";
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");


$querya="truncate table chat";
$resulta=$db->sql_query($querya);

$queryb="truncate table chat_room";
$resultb=$db->sql_query($queryb);

$queryc="truncate table selection";
$resultc=$db->sql_query($queryc);

$queryd="update team set team_autopick = 0";
$resultd=$db->sql_query($queryd);

$querye="update team set team_clock_adj = 1.00";
$resulte=$db->sql_query($querye);

$queryf="update team set team_autopick_wait = 0";
$resultf=$db->sql_query($queryf);

$queryg="update team set team_multipos = 0";
$resultg=$db->sql_query($queryg);

$queryh="update pick set player_id = null";
$resulth=$db->sql_query($queryh);

$queryi="UPDATE pick SET pick_time = null";
$resulti=$db->sql_query($queryi);

$queryj="update pick set pick_start = null";
$resultj=$db->sql_query($queryj);

$queryk="alter table nuke_scout_rookieratings drop column blah";
$resultk=$db->sql_query($queryk);

$queryl="alter table nuke_scout_rookieratings drop column sta";
$resultl=$db->sql_query($queryl);

$querym="alter table nuke_scout_rookieratings drop column invite";
$resultm=$db->sql_query($querym);

$queryn="alter table nuke_scout_rookieratings drop column ranking";
$resultn=$db->sql_query($queryn);

$queryo="alter table nuke_scout_rookieratings drop column team";
$resulto=$db->sql_query($queryo);

$queryp="alter table nuke_scout_rookieratings drop column drafted";
$resultp=$db->sql_query($queryp);

$queryq="alter table nuke_scout_rookieratings ADD intan int(11)";
$resultq=$db->sql_query($queryq);

$queryr="update nuke_scout_rookieratings set intan = `int`";
$resultr=$db->sql_query($queryr);

$querys="alter table nuke_scout_rookieratings drop `int`";
$results=$db->sql_query($querys);

$queryt="alter table nuke_scout_rookieratings ADD player_id  int(2)";
$resultt=$db->sql_query($queryt);

$queryu="alter table nuke_scout_rookieratings ADD player_name  varchar(50)";
$resultu=$db->sql_query($queryu);

$queryv="update nuke_scout_rookieratings set player_name = name";
$resultv=$db->sql_query($queryv);

$queryw="alter table nuke_scout_rookieratings drop name";
$resultw=$db->sql_query($queryw);

$queryx="alter table nuke_scout_rookieratings ADD player_age  int(2)";
$resultx=$db->sql_query($queryx);

$queryy="update nuke_scout_rookieratings set player_age = age";
$resulty=$db->sql_query($queryy);

$queryz="alter table nuke_scout_rookieratings drop age";
$resultz=$db->sql_query($queryz);

$queryaa="alter table nuke_scout_rookieratings ADD position_id  varchar(2)";
$resultaa=$db->sql_query($queryaa);

$querybb="update nuke_scout_rookieratings set position_id = pos";
$resultbb=$db->sql_query($querybb);

$querycc="alter table nuke_scout_rookieratings drop pos";
$resultcc=$db->sql_query($querycc);

$querydd="alter table nuke_scout_rookieratings ADD player_fgp  varchar(2)";
$resultdd=$db->sql_query($querydd);

$queryee="update nuke_scout_rookieratings set player_fgp = fgp";
$resultee=$db->sql_query($queryee);

$queryff="alter table nuke_scout_rookieratings drop fgp";
$resultff=$db->sql_query($queryff);

$querygg="alter table nuke_scout_rookieratings ADD player_fga  varchar(2)";
$resultgg=$db->sql_query($querygg);

$queryhh="update nuke_scout_rookieratings set player_fga = fga";
$resulthh=$db->sql_query($queryhh);

$queryii="alter table nuke_scout_rookieratings drop fga";
$resultii=$db->sql_query($queryii);

$queryjj="alter table nuke_scout_rookieratings ADD player_ftp  varchar(2)";
$resultjj=$db->sql_query($queryjj);

$querykk="update nuke_scout_rookieratings set player_ftp = ftp";
$resultkk=$db->sql_query($querykk);

$queryll="alter table nuke_scout_rookieratings drop ftp";
$resultll=$db->sql_query($queryll);

$querymm="alter table nuke_scout_rookieratings ADD player_fta  varchar(2)";
$resultmm=$db->sql_query($querymm);

$querynn="update nuke_scout_rookieratings set player_fta = fta";
$resultnn=$db->sql_query($querynn);

$queryoo="alter table nuke_scout_rookieratings drop fta";
$resultoo=$db->sql_query($queryoo);

$querypp="alter table nuke_scout_rookieratings ADD player_tgp  varchar(2)";
$resultpp=$db->sql_query($querypp);

$queryqq="update nuke_scout_rookieratings set player_tgp = tgp";
$resultqq=$db->sql_query($queryqq);

$queryrr="alter table nuke_scout_rookieratings drop tgp";
$resultrr=$db->sql_query($queryrr);

$queryss="alter table nuke_scout_rookieratings ADD player_tga  varchar(2)";
$resultss=$db->sql_query($queryss);

$querytt="update nuke_scout_rookieratings set player_tga = tga";
$resulttt=$db->sql_query($querytt);

$queryuu="alter table nuke_scout_rookieratings drop tga";
$resultuu=$db->sql_query($queryuu);

$queryvv="alter table nuke_scout_rookieratings ADD player_orb  varchar(2)";
$resultvv=$db->sql_query($queryvv);

$queryww="update nuke_scout_rookieratings set player_orb = orb";
$resultww=$db->sql_query($queryww);

$queryxx="alter table nuke_scout_rookieratings drop orb";
$resultxx=$db->sql_query($queryxx);

$queryyy="alter table nuke_scout_rookieratings ADD player_drb  varchar(2)";
$resultyy=$db->sql_query($queryyy);

$queryzz="update nuke_scout_rookieratings set player_drb = drb";
$resultzz=$db->sql_query($queryzz);

$queryaaa="alter table nuke_scout_rookieratings drop drb";
$resultaaa=$db->sql_query($queryaaa);

$querybbb="alter table nuke_scout_rookieratings ADD player_ast  varchar(2)";
$resultbbb=$db->sql_query($querybbb);

$queryccc="update nuke_scout_rookieratings set player_ast = ast";
$resultccc=$db->sql_query($queryccc);

$queryddd="alter table nuke_scout_rookieratings drop ast";
$resultddd=$db->sql_query($queryddd);

$queryeee="alter table nuke_scout_rookieratings ADD player_stl  varchar(2)";
$resulteee=$db->sql_query($queryeee);

$queryfff="update nuke_scout_rookieratings set player_stl = stl";
$resultfff=$db->sql_query($queryfff);

$queryggg="alter table nuke_scout_rookieratings drop stl";
$resultggg=$db->sql_query($queryggg);

$queryhhh="alter table nuke_scout_rookieratings ADD player_to  varchar(2)";
$resulthhh=$db->sql_query($queryhhh);

$queryiii="update nuke_scout_rookieratings set player_to = tvr";
$resultiii=$db->sql_query($queryiii);

$queryjjj="alter table nuke_scout_rookieratings drop tvr";
$resultjjj=$db->sql_query($queryjjj);

$querykkk="alter table nuke_scout_rookieratings ADD player_blk  varchar(2)";
$resultkkk=$db->sql_query($querykkk);

$querylll="update nuke_scout_rookieratings set player_blk = blk";
$resultlll=$db->sql_query($querylll);

$querymmm="alter table nuke_scout_rookieratings drop blk";
$resultmmm=$db->sql_query($querymmm);

$querynnn="alter table nuke_scout_rookieratings ADD player_off  varchar(2)";
$resultnnn=$db->sql_query($querynnn);

$queryooo="alter table nuke_scout_rookieratings ADD player_def  varchar(2)";
$resultooo=$db->sql_query($queryooo);

$queryppp="alter table nuke_scout_rookieratings ADD player_tsi  varchar(2)";
$resultppp=$db->sql_query($queryppp);

$queryrrr="update nuke_scout_rookieratings set player_off = offo+offd+offp+offt";
$resultrrr=$db->sql_query($queryrrr);

$querysss="update nuke_scout_rookieratings set player_def = defo+defd+defp+deft";
$resultsss=$db->sql_query($querysss);

$queryttt="update nuke_scout_rookieratings set player_tsi = tal+skl+intan";
$resultttt=$db->sql_query($queryttt);

$queryuuu="alter table nuke_scout_rookieratings drop offo";
$resultuuu=$db->sql_query($queryuuu);

$queryvvv="alter table nuke_scout_rookieratings drop offd";
$resultvvv=$db->sql_query($queryvvv);

$querywww="alter table nuke_scout_rookieratings drop offp";
$resultwww=$db->sql_query($querywww);

$queryxxx="alter table nuke_scout_rookieratings drop offt";
$resultxxx=$db->sql_query($queryxxx);

$queryyyy="alter table nuke_scout_rookieratings drop defo";
$resultyyy=$db->sql_query($queryyyy);

$queryzzz="alter table nuke_scout_rookieratings drop defd";
$resultzzz=$db->sql_query($queryzzz);

$query1="alter table nuke_scout_rookieratings drop defp";
$result1=$db->sql_query($query1);

$query2="alter table nuke_scout_rookieratings drop deft";
$result2=$db->sql_query($query2);

$query3="alter table nuke_scout_rookieratings drop tal";
$result3=$db->sql_query($query3);

$query4="alter table nuke_scout_rookieratings drop skl";
$result4=$db->sql_query($query4);

$query5="alter table nuke_scout_rookieratings drop intan";
$result5=$db->sql_query($query5);

$query6="update nuke_scout_rookieratings set position_id = '5' where position_id = 'C'";
$result6=$db->sql_query($query6);

$query7="update nuke_scout_rookieratings set position_id = '4' where position_id = 'PF'";
$result7=$db->sql_query($query7);

$query8="update nuke_scout_rookieratings set position_id = '3' where position_id = 'SF'";
$result8=$db->sql_query($query8);

$query9="update nuke_scout_rookieratings set position_id = '2' where position_id = 'SG'";
$result9=$db->sql_query($query9);

$query10="update nuke_scout_rookieratings set position_id = '1' where position_id = 'PG'";
$result10=$db->sql_query($query10);

$query511="update nuke_scout_rookieratings set player_id = id";
$result511=$db->sql_query($query511);

$query11="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '0'";
$result11=$db->sql_query($query11);

$query12="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '1'";
$result12=$db->sql_query($query12);

$query13="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '2'";
$result13=$db->sql_query($query13);

$query14="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '3'";
$result14=$db->sql_query($query14);

$query15="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '4'";
$result15=$db->sql_query($query15);

$query16="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '5'";
$result16=$db->sql_query($query16);

$query17="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '6'";
$result17=$db->sql_query($query17);

$query18="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '7'";
$result18=$db->sql_query($query18);

$query19="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '8'";
$result19=$db->sql_query($query19);

$query20="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '9'";
$result20=$db->sql_query($query20);

$query21="update nuke_scout_rookieratings set player_fga = 'F' where player_fga between '10' and '19'";
$result21=$db->sql_query($query21);

$query22="update nuke_scout_rookieratings set player_fga = 'D' where player_fga between '20' and '39'";
$result22=$db->sql_query($query22);

$query23="update nuke_scout_rookieratings set player_fga = 'C' where player_fga between '40' and '59'";
$result23=$db->sql_query($query23);

$query24="update nuke_scout_rookieratings set player_fga = 'B' where player_fga between '60' and '79'";
$result24=$db->sql_query($query24);

$query25="update nuke_scout_rookieratings set player_fga = 'A' where player_fga between '80' and '99'";
$result25=$db->sql_query($query25);

$query26="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '0'";
$result26=$db->sql_query($query26);

$query27="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '1'";
$result27=$db->sql_query($query27);

$query28="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '2'";
$result28=$db->sql_query($query28);

$query29="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '3'";
$result29=$db->sql_query($query29);

$query30="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '4'";
$resulta30=$db->sql_query($query30);

$query31="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '5'";
$result31=$db->sql_query($query31);

$query32="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '6'";
$result32=$db->sql_query($query32);

$query33="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '7'";
$result33=$db->sql_query($query33);

$query34="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '8'";
$result34=$db->sql_query($query34);

$query35="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '9'";
$result35=$db->sql_query($query35);

$query36="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp between '10' and '40'";
$result36=$db->sql_query($query36);

$query37="update nuke_scout_rookieratings set player_fgp = 'D' where player_fgp between '41' and '43'";
$result37=$db->sql_query($query37);

$query38="update nuke_scout_rookieratings set player_fgp = 'C' where player_fgp between '44' and '46'";
$result38=$db->sql_query($query38);

$query39="update nuke_scout_rookieratings set player_fgp = 'B' where player_fgp between '47' and '49'";
$result39=$db->sql_query($query39);

$query40="update nuke_scout_rookieratings set player_fgp = 'A' where player_fgp between '50' and '99'";
$result40=$db->sql_query($query40);

$query41="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '0'";
$result41=$db->sql_query($query41);

$query42="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '1'";
$result42=$db->sql_query($query42);

$query43="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '2'";
$result43=$db->sql_query($query43);

$query44="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '3'";
$result44=$db->sql_query($query44);

$query45="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '4'";
$result45=$db->sql_query($query45);

$query46="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '5'";
$result46=$db->sql_query($query46);

$query47="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '6'";
$result47=$db->sql_query($query47);

$query48="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '7'";
$result48=$db->sql_query($query48);

$query49="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '8'";
$result49=$db->sql_query($query49);

$query50="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '9'";
$result50=$db->sql_query($query50);

$query51="update nuke_scout_rookieratings set player_fta = 'F' where player_fta between '10' and '19'";
$result51=$db->sql_query($query51);

$query52="update nuke_scout_rookieratings set player_fta = 'D' where player_fta between '20' and '39'";
$result52=$db->sql_query($query52);

$query53="update nuke_scout_rookieratings set player_fta = 'C' where player_fta between '40' and '59'";
$result53=$db->sql_query($query53);

$query54="update nuke_scout_rookieratings set player_fta = 'B' where player_fta between '60' and '79'";
$result54=$db->sql_query($query54);

$query55="update nuke_scout_rookieratings set player_fta = 'A' where player_fta between '80' and '99'";
$result55=$db->sql_query($query55);

$query56="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '0'";
$result56=$db->sql_query($query56);

$query57="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '1'";
$result57=$db->sql_query($query57);

$query58="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '2'";
$result58=$db->sql_query($query58);

$query59="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '3'";
$result59=$db->sql_query($query59);

$query60="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '4'";
$result60=$db->sql_query($query60);

$query61="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '5'";
$result61=$db->sql_query($query61);

$query62="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '6'";
$result62=$db->sql_query($query62);

$query63="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '7'";
$result63=$db->sql_query($query63);

$query64="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '8'";
$result64=$db->sql_query($query64);

$query65="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '9'";
$result65=$db->sql_query($query65);

$query66="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp between '10' and '68'";
$result66=$db->sql_query($query66);

$query67="update nuke_scout_rookieratings set player_ftp = 'D' where player_ftp between '69' and '72'";
$result67=$db->sql_query($query67);

$query68="update nuke_scout_rookieratings set player_ftp = 'C' where player_ftp between '71' and '76'";
$result68=$db->sql_query($query68);

$query69="update nuke_scout_rookieratings set player_ftp = 'B' where player_ftp between '77' and '80'";
$result69=$db->sql_query($query69);

$query70="update nuke_scout_rookieratings set player_ftp = 'A' where player_ftp between '81' and '99'";
$result70=$db->sql_query($query70);

$query71="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '0'";
$result71=$db->sql_query($query71);

$query72="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '1'";
$result72=$db->sql_query($query72);

$query73="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '2'";
$result73=$db->sql_query($query73);

$query74="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '3'";
$result74=$db->sql_query($query74);

$query75="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '4'";
$result75=$db->sql_query($query75);

$query76="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '5'";
$result76=$db->sql_query($query76);

$query77="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '6'";
$result77=$db->sql_query($query77);

$query78="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '7'";
$result78=$db->sql_query($query78);

$query79="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '8'";
$result79=$db->sql_query($query79);

$query80="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '9'";
$result80=$db->sql_query($query80);

$query81="update nuke_scout_rookieratings set player_tga = 'F' where player_tga between '10' and '19'";
$result81=$db->sql_query($query81);

$query82="update nuke_scout_rookieratings set player_tga = 'D' where player_tga between '20' and '39'";
$result82=$db->sql_query($query82);

$query83="update nuke_scout_rookieratings set player_tga = 'C' where player_tga between '40' and '59'";
$result83=$db->sql_query($query83);

$query84="update nuke_scout_rookieratings set player_tga = 'B' where player_tga between '60' and '79'";
$result84=$db->sql_query($query84);

$query85="update nuke_scout_rookieratings set player_tga = 'A' where player_tga between '80' and '99'";
$result85=$db->sql_query($query85);

$query86="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '0'";
$result86=$db->sql_query($query86);

$query87="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '1'";
$result87=$db->sql_query($query87);

$query88="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '2'";
$result88=$db->sql_query($query88);

$query89="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '3'";
$result89=$db->sql_query($query89);

$query90="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '4'";
$result90=$db->sql_query($query90);

$query91="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '5'";
$result91=$db->sql_query($query91);

$query92="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '6'";
$result92=$db->sql_query($query92);

$query93="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '7'";
$result93=$db->sql_query($query93);

$query94="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '8'";
$result94=$db->sql_query($query94);

$query95="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '9'";
$result95=$db->sql_query($query95);

$query96="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp between '10' and '27'";
$result96=$db->sql_query($query96);

$query97="update nuke_scout_rookieratings set player_tgp = 'D' where player_tgp between '28' and '31'";
$result97=$db->sql_query($query97);

$query98="update nuke_scout_rookieratings set player_tgp = 'C' where player_tgp between '32' and '35'";
$result98=$db->sql_query($query98);

$query99="update nuke_scout_rookieratings set player_tgp = 'B' where player_tgp between '36' and '39'";
$result99=$db->sql_query($query99);

$query100="update nuke_scout_rookieratings set player_tgp = 'A' where player_tgp between '40' and '99'";
$result100=$db->sql_query($query100);

$query101="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '0'";
$result101=$db->sql_query($query101);

$query102="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '1'";
$result102=$db->sql_query($query102);

$query103="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '2'";
$result103=$db->sql_query($query103);

$query104="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '3'";
$result104=$db->sql_query($query104);

$query105="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '4'";
$result105=$db->sql_query($query105);

$query106="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '5'";
$result106=$db->sql_query($query106);

$query107="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '6'";
$result107=$db->sql_query($query107);

$query108="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '7'";
$result108=$db->sql_query($query108);

$query109="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '8'";
$result109=$db->sql_query($query109);

$query110="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '9'";
$result110=$db->sql_query($query110);

$query111="update nuke_scout_rookieratings set player_orb = 'F' where player_orb between '10' and '19'";
$result111=$db->sql_query($query111);

$query112="update nuke_scout_rookieratings set player_orb = 'D' where player_orb between '20' and '39'";
$result112=$db->sql_query($query112);

$query113="update nuke_scout_rookieratings set player_orb = 'C' where player_orb between '40' and '59'";
$result113=$db->sql_query($query113);

$query114="update nuke_scout_rookieratings set player_orb = 'B' where player_orb between '60' and '79'";
$result114=$db->sql_query($query114);

$query115="update nuke_scout_rookieratings set player_orb = 'A' where player_orb between '80' and '99'";
$result115=$db->sql_query($query115);

$query116="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '0'";
$result116=$db->sql_query($query116);

$query117="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '1'";
$result117=$db->sql_query($query117);

$query118="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '2'";
$result118=$db->sql_query($query118);

$query119="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '3'";
$result119=$db->sql_query($query119);

$query120="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '4'";
$result120=$db->sql_query($query120);

$query121="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '5'";
$result121=$db->sql_query($query121);

$query122="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '6'";
$result122=$db->sql_query($query122);

$query123="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '7'";
$result123=$db->sql_query($query123);

$query124="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '8'";
$result124=$db->sql_query($query124);

$query125="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '9'";
$result125=$db->sql_query($query125);

$query126="update nuke_scout_rookieratings set player_drb = 'F' where player_drb between '10' and '19'";
$result126=$db->sql_query($query126);

$query127="update nuke_scout_rookieratings set player_drb = 'D' where player_drb between '20' and '39'";
$result127=$db->sql_query($query127);

$query128="update nuke_scout_rookieratings set player_drb = 'C' where player_drb between '40' and '59'";
$result128=$db->sql_query($query128);

$query129="update nuke_scout_rookieratings set player_drb = 'B' where player_drb between '60' and '79'";
$result129=$db->sql_query($query129);

$query130="update nuke_scout_rookieratings set player_drb = 'A' where player_drb between '80' and '99'";
$result130=$db->sql_query($query130);

$query131="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '0'";
$result131=$db->sql_query($query131);

$query132="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '1'";
$result132=$db->sql_query($query132);

$query133="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '2'";
$result133=$db->sql_query($query133);

$query134="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '3'";
$result134=$db->sql_query($query134);

$query135="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '4'";
$result135=$db->sql_query($query135);

$query136="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '5'";
$result136=$db->sql_query($query136);

$query137="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '6'";
$result137=$db->sql_query($query137);

$query138="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '7'";
$result138=$db->sql_query($query138);

$query139="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '8'";
$result139=$db->sql_query($query139);

$query140="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '9'";
$result140=$db->sql_query($query140);

$query141="update nuke_scout_rookieratings set player_ast = 'F' where player_ast between '10' and '19'";
$result141=$db->sql_query($query141);

$query142="update nuke_scout_rookieratings set player_ast = 'D' where player_ast between '20' and '39'";
$result142=$db->sql_query($query142);

$query143="update nuke_scout_rookieratings set player_ast = 'C' where player_ast between '40' and '59'";
$result143=$db->sql_query($query143);

$query144="update nuke_scout_rookieratings set player_ast = 'B' where player_ast between '60' and '79'";
$result144=$db->sql_query($query144);

$query145="update nuke_scout_rookieratings set player_ast = 'A' where player_ast between '80' and '99'";
$result145=$db->sql_query($query145);

$query146="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '0'";
$result146=$db->sql_query($query146);

$query147="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '1'";
$resul147=$db->sql_query($query147);

$query148="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '2'";
$result148=$db->sql_query($query148);

$query149="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '3'";
$result149=$db->sql_query($query149);

$query150="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '4'";
$result150=$db->sql_query($query150);

$query151="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '5'";
$result151=$db->sql_query($query151);

$query152="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '6'";
$result152=$db->sql_query($query152);

$query153="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '7'";
$result153=$db->sql_query($query153);

$query154="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '8'";
$result154=$db->sql_query($query154);

$query155="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '9'";
$result155=$db->sql_query($query155);

$query156="update nuke_scout_rookieratings set player_stl = 'F' where player_stl between '10' and '19'";
$result156=$db->sql_query($query156);

$query157="update nuke_scout_rookieratings set player_stl = 'D' where player_stl between '20' and '39'";
$result157=$db->sql_query($query157);

$query158="update nuke_scout_rookieratings set player_stl = 'C' where player_stl between '40' and '59'";
$result158=$db->sql_query($query158);

$query159="update nuke_scout_rookieratings set player_stl = 'B' where player_stl between '60' and '79'";
$result159=$db->sql_query($query159);

$query160="update nuke_scout_rookieratings set player_stl = 'A' where player_stl between '80' and '99'";
$result160=$db->sql_query($query160);

$query161="update nuke_scout_rookieratings set player_to = 'F' where player_to = '0'";
$result161=$db->sql_query($query161);

$query162="update nuke_scout_rookieratings set player_to = 'F' where player_to = '1'";
$result162=$db->sql_query($query162);

$query163="update nuke_scout_rookieratings set player_to = 'F' where player_to = '2'";
$result163=$db->sql_query($query163);

$query164="update nuke_scout_rookieratings set player_to = 'F' where player_to = '3'";
$result164=$db->sql_query($query164);

$query165="update nuke_scout_rookieratings set player_to = 'F' where player_to = '4'";
$result165=$db->sql_query($query165);

$query166="update nuke_scout_rookieratings set player_to = 'F' where player_to = '5'";
$result166=$db->sql_query($query166);

$query167="update nuke_scout_rookieratings set player_to = 'F' where player_to = '6'";
$result167=$db->sql_query($query167);

$query168="update nuke_scout_rookieratings set player_to = 'F' where player_to = '7'";
$result168=$db->sql_query($query168);

$query169="update nuke_scout_rookieratings set player_to = 'F' where player_to = '8'";
$result169=$db->sql_query($query169);

$query170="update nuke_scout_rookieratings set player_to = 'F' where player_to = '9'";
$result170=$db->sql_query($query170);

$query171="update nuke_scout_rookieratings set player_to = 'F' where player_to between '10' and '19'";
$result171=$db->sql_query($query171);

$query172="update nuke_scout_rookieratings set player_to = 'D' where player_to between '20' and '39'";
$result172=$db->sql_query($query172);

$query173="update nuke_scout_rookieratings set player_to = 'C' where player_to between '40' and '59'";
$result173=$db->sql_query($query173);

$query174="update nuke_scout_rookieratings set player_to = 'B' where player_to between '60' and '79'";
$result174=$db->sql_query($query174);

$query175="update nuke_scout_rookieratings set player_to = 'A' where player_to between '80' and '99'";
$result175=$db->sql_query($query175);

$query176="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '0'";
$result176=$db->sql_query($query176);

$query177="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '1'";
$result177=$db->sql_query($query177);

$query178="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '2'";
$result178=$db->sql_query($query178);

$query179="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '3'";
$result179=$db->sql_query($query179);

$query180="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '4'";
$result180=$db->sql_query($query180);

$query181="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '5'";
$result181=$db->sql_query($query181);

$query182="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '6'";
$result182=$db->sql_query($query182);

$query183="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '7'";
$result183=$db->sql_query($query183);

$query184="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '8'";
$result184=$db->sql_query($query184);

$query185="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '9'";
$result185=$db->sql_query($query185);

$query186="update nuke_scout_rookieratings set player_blk = 'F' where player_blk between '10' and '19'";
$result186=$db->sql_query($query186);

$query187="update nuke_scout_rookieratings set player_blk = 'D' where player_blk between '20' and '39'";
$result187=$db->sql_query($query187);

$query188="update nuke_scout_rookieratings set player_blk = 'C' where player_blk between '40' and '59'";
$result188=$db->sql_query($query188);

$query189="update nuke_scout_rookieratings set player_blk = 'B' where player_blk between '60' and '79'";
$result189=$db->sql_query($query189);

$query190="update nuke_scout_rookieratings set player_blk = 'A' where player_blk between '80' and '99'";
$result190=$db->sql_query($query190);

$query191="update nuke_scout_rookieratings set player_off = 'F' where player_off = '0'";
$result191=$db->sql_query($query191);

$query192="update nuke_scout_rookieratings set player_off = 'F' where player_off = '1'";
$result192=$db->sql_query($query192);

$query193="update nuke_scout_rookieratings set player_off = 'F' where player_off = '2'";
$result193=$db->sql_query($query193);

$query194="update nuke_scout_rookieratings set player_off = 'F' where player_off = '3'";
$result194=$db->sql_query($query194);

$query195="update nuke_scout_rookieratings set player_off = 'F' where player_off = '4'";
$resulta195=$db->sql_query($query195);

$query196="update nuke_scout_rookieratings set player_off = 'F' where player_off = '5'";
$result196=$db->sql_query($query196);

$query197="update nuke_scout_rookieratings set player_off = 'F' where player_off = '6'";
$result197=$db->sql_query($query197);

$query198="update nuke_scout_rookieratings set player_off = 'F' where player_off = '7'";
$result198=$db->sql_query($query198);

$query199="update nuke_scout_rookieratings set player_off = 'F' where player_off = '8'";
$result199=$db->sql_query($query199);

$query200="update nuke_scout_rookieratings set player_off = 'F' where player_off = '9'";
$result200=$db->sql_query($query200);

$query201="update nuke_scout_rookieratings set player_off = 'D' where player_off between '10' and '16'";
$result201=$db->sql_query($query201);

$query202="update nuke_scout_rookieratings set player_off = 'C' where player_off between '17' and '23'";
$result202=$db->sql_query($query202);

$query203="update nuke_scout_rookieratings set player_off = 'B' where player_off between '24' and '29'";
$result203=$db->sql_query($query203);

$query204="update nuke_scout_rookieratings set player_off = 'A' where player_off between '30' and '99'";
$result204=$db->sql_query($query204);

$query205="update nuke_scout_rookieratings set player_def = 'F' where player_def = '0'";
$result205=$db->sql_query($query205);

$query206="update nuke_scout_rookieratings set player_def = 'F' where player_def = '1'";
$result206=$db->sql_query($query206);

$query207="update nuke_scout_rookieratings set player_def = 'F' where player_def = '2'";
$result207=$db->sql_query($query207);

$query208="update nuke_scout_rookieratings set player_def = 'F' where player_def = '3'";
$result208=$db->sql_query($query208);

$query209="update nuke_scout_rookieratings set player_def = 'F' where player_def = '4'";
$result209=$db->sql_query($query209);

$query210="update nuke_scout_rookieratings set player_def = 'F' where player_def = '5'";
$result210=$db->sql_query($query210);

$query211="update nuke_scout_rookieratings set player_def = 'F' where player_def = '6'";
$result211=$db->sql_query($query211);

$query212="update nuke_scout_rookieratings set player_def = 'F' where player_def = '7'";
$result212=$db->sql_query($query212);

$query213="update nuke_scout_rookieratings set player_def = 'F' where player_def = '8'";
$result213=$db->sql_query($query213);

$query214="update nuke_scout_rookieratings set player_def = 'F' where player_def = '9'";
$result214=$db->sql_query($query214);

$query215="update nuke_scout_rookieratings set player_def = 'D' where player_def between '10' and '16'";
$result215=$db->sql_query($query215);

$query216="update nuke_scout_rookieratings set player_def = 'C' where player_def between '17' and '23'";
$result216=$db->sql_query($query216);

$query217="update nuke_scout_rookieratings set player_def = 'B' where player_def between '24' and '29'";
$result217=$db->sql_query($query217);

$query218="update nuke_scout_rookieratings set player_def = 'A' where player_def between '30' and '99'";
$result218=$db->sql_query($query218);

$query219="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '0'";
$result219=$db->sql_query($query219);

$query220="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '1'";
$result220=$db->sql_query($query220);

$query221="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '2'";
$result221=$db->sql_query($query221);

$query222="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '3'";
$result222=$db->sql_query($query222);

$query223="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '4'";
$result223=$db->sql_query($query223);

$query224="update nuke_scout_rookieratings set player_tsi = 'D' where player_tsi = '5'";
$result224=$db->sql_query($query224);

$query225="update nuke_scout_rookieratings set player_tsi = 'D' where player_tsi = '6'";
$result225=$db->sql_query($query225);

$query226="update nuke_scout_rookieratings set player_tsi = 'D' where player_tsi = '7'";
$result226=$db->sql_query($query226);

$query227="update nuke_scout_rookieratings set player_tsi = 'C' where player_tsi = '8'";
$result227=$db->sql_query($query227);

$query228="update nuke_scout_rookieratings set player_tsi = 'C' where player_tsi = '9'";
$result228=$db->sql_query($query228);

$query234="update nuke_scout_rookieratings set player_tsi = 'C' where player_tsi = '10'";
$result234=$db->sql_query($query234);

$query229="update nuke_scout_rookieratings set player_tsi = 'B' where player_tsi between '11' and '13'";
$result229=$db->sql_query($query229);

$query230="update nuke_scout_rookieratings set player_tsi = 'A' where player_tsi between '14' and '99'";
$result230=$db->sql_query($query230);

$query231="drop table player";
$result231=$db->sql_query($query231);

$query232="alter table nuke_scout_rookieratings rename to player";
$result232=$db->sql_query($query232);

$query233="update settings set setting_value = '0' where setting_id = '2'";
$result233=$db->sql_query($query233);


echo "Draft-o-Matic setup is complete. Do NOT contact Joe. Seriously. Don't do it."


?>
