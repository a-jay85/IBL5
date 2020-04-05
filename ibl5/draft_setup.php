<?php

$username = "iblhoops";
$password = "Underthedome19!";
$database = "iblhoops_v5draft";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");


$querya="truncate table chat";
$resulta=mysql_query($querya);

$queryb="truncate table chat_room";
$resultb=mysql_query($queryb);

$queryc="truncate table selection";
$resultc=mysql_query($queryc);

$queryd="update team set team_autopick = 0";
$resultd=mysql_query($queryd);

$querye="update team set team_clock_adj = 1.00";
$resulte=mysql_query($querye);

$queryf="update team set team_autopick_wait = 0";
$resultf=mysql_query($queryf);

$queryg="update team set team_multipos = 0";
$resultg=mysql_query($queryg);

$queryh="update pick set player_id = null";
$resulth=mysql_query($queryh);

$queryi="UPDATE pick SET pick_time = null";
$resulti=mysql_query($queryi);

$queryj="update pick set pick_start = null";
$resultj=mysql_query($queryj);

$queryk="alter table nuke_scout_rookieratings drop column blah";
$resultk=mysql_query($queryk);

$queryl="alter table nuke_scout_rookieratings drop column sta";
$resultl=mysql_query($queryl);

$querym="alter table nuke_scout_rookieratings drop column invite";
$resultm=mysql_query($querym);

$queryn="alter table nuke_scout_rookieratings drop column ranking";
$resultn=mysql_query($queryn);

$queryo="alter table nuke_scout_rookieratings drop column team";
$resulto=mysql_query($queryo);

$queryp="alter table nuke_scout_rookieratings drop column drafted";
$resultp=mysql_query($queryp);

$queryq="alter table nuke_scout_rookieratings ADD intan int(11)";
$resultq=mysql_query($queryq);

$queryr="update nuke_scout_rookieratings set intan = `int`";
$resultr=mysql_query($queryr);

$querys="alter table nuke_scout_rookieratings drop `int`";
$results=mysql_query($querys);

$queryt="alter table nuke_scout_rookieratings ADD player_id int(2)";
$resultt=mysql_query($queryt);



$queryu="alter table nuke_scout_rookieratings ADD player_name  varchar(50)";
$resultu=mysql_query($queryu);

$queryv="update nuke_scout_rookieratings set player_name = name";
$resultv=mysql_query($queryv);

$queryw="alter table nuke_scout_rookieratings drop name";
$resultw=mysql_query($queryw);

$queryx="alter table nuke_scout_rookieratings ADD player_age  int(2)";
$resultx=mysql_query($queryx);

$queryy="update nuke_scout_rookieratings set player_age = age";
$resulty=mysql_query($queryy);

$queryz="alter table nuke_scout_rookieratings drop age";
$resultz=mysql_query($queryz);

$queryaa="alter table nuke_scout_rookieratings ADD position_id  varchar(2)";
$resultaa=mysql_query($queryaa);

$querybb="update nuke_scout_rookieratings set position_id = pos";
$resultbb=mysql_query($querybb);

$querycc="alter table nuke_scout_rookieratings drop pos";
$resultcc=mysql_query($querycc);

$querydd="alter table nuke_scout_rookieratings ADD player_fgp  varchar(2)";
$resultdd=mysql_query($querydd);

$queryee="update nuke_scout_rookieratings set player_fgp = fgp";
$resultee=mysql_query($queryee);

$queryff="alter table nuke_scout_rookieratings drop fgp";
$resultff=mysql_query($queryff);

$querygg="alter table nuke_scout_rookieratings ADD player_fga  varchar(2)";
$resultgg=mysql_query($querygg);

$queryhh="update nuke_scout_rookieratings set player_fga = fga";
$resulthh=mysql_query($queryhh);

$queryii="alter table nuke_scout_rookieratings drop fga";
$resultii=mysql_query($queryii);

$queryjj="alter table nuke_scout_rookieratings ADD player_ftp  varchar(2)";
$resultjj=mysql_query($queryjj);

$querykk="update nuke_scout_rookieratings set player_ftp = ftp";
$resultkk=mysql_query($querykk);

$queryll="alter table nuke_scout_rookieratings drop ftp";
$resultll=mysql_query($queryll);

$querymm="alter table nuke_scout_rookieratings ADD player_fta  varchar(2)";
$resultmm=mysql_query($querymm);

$querynn="update nuke_scout_rookieratings set player_fta = fta";
$resultnn=mysql_query($querynn);

$queryoo="alter table nuke_scout_rookieratings drop fta";
$resultoo=mysql_query($queryoo);

$querypp="alter table nuke_scout_rookieratings ADD player_tgp  varchar(2)";
$resultpp=mysql_query($querypp);

$queryqq="update nuke_scout_rookieratings set player_tgp = tgp";
$resultqq=mysql_query($queryqq);

$queryrr="alter table nuke_scout_rookieratings drop tgp";
$resultrr=mysql_query($queryrr);

$queryss="alter table nuke_scout_rookieratings ADD player_tga  varchar(2)";
$resultss=mysql_query($queryss);

$querytt="update nuke_scout_rookieratings set player_tga = tga";
$resulttt=mysql_query($querytt);

$queryuu="alter table nuke_scout_rookieratings drop tga";
$resultuu=mysql_query($queryuu);

$queryvv="alter table nuke_scout_rookieratings ADD player_orb  varchar(2)";
$resultvv=mysql_query($queryvv);

$queryww="update nuke_scout_rookieratings set player_orb = orb";
$resultww=mysql_query($queryww);

$queryxx="alter table nuke_scout_rookieratings drop orb";
$resultxx=mysql_query($queryxx);

$queryyy="alter table nuke_scout_rookieratings ADD player_drb  varchar(2)";
$resultyy=mysql_query($queryyy);

$queryzz="update nuke_scout_rookieratings set player_drb = drb";
$resultzz=mysql_query($queryzz);

$queryaaa="alter table nuke_scout_rookieratings drop drb";
$resultaaa=mysql_query($queryaaa);

$querybbb="alter table nuke_scout_rookieratings ADD player_ast  varchar(2)";
$resultbbb=mysql_query($querybbb);

$queryccc="update nuke_scout_rookieratings set player_ast = ast";
$resultccc=mysql_query($queryccc);

$queryddd="alter table nuke_scout_rookieratings drop ast";
$resultddd=mysql_query($queryddd);

$queryeee="alter table nuke_scout_rookieratings ADD player_stl  varchar(2)";
$resulteee=mysql_query($queryeee);

$queryfff="update nuke_scout_rookieratings set player_stl = stl";
$resultfff=mysql_query($queryfff);

$queryggg="alter table nuke_scout_rookieratings drop stl";
$resultggg=mysql_query($queryggg);

$queryhhh="alter table nuke_scout_rookieratings ADD player_to  varchar(2)";
$resulthhh=mysql_query($queryhhh);

$queryiii="update nuke_scout_rookieratings set player_to = tvr";
$resultiii=mysql_query($queryiii);

$queryjjj="alter table nuke_scout_rookieratings drop tvr";
$resultjjj=mysql_query($queryjjj);

$querykkk="alter table nuke_scout_rookieratings ADD player_blk  varchar(2)";
$resultkkk=mysql_query($querykkk);

$querylll="update nuke_scout_rookieratings set player_blk = blk";
$resultlll=mysql_query($querylll);

$querymmm="alter table nuke_scout_rookieratings drop blk";
$resultmmm=mysql_query($querymmm);

$querynnn="alter table nuke_scout_rookieratings ADD player_off  varchar(2)";
$resultnnn=mysql_query($querynnn);

$queryooo="alter table nuke_scout_rookieratings ADD player_def  varchar(2)";
$resultooo=mysql_query($queryooo);

$queryppp="alter table nuke_scout_rookieratings ADD player_tsi  varchar(2)";
$resultppp=mysql_query($queryppp);

$queryrrr="update nuke_scout_rookieratings set player_off = offo+offd+offp+offt";
$resultrrr=mysql_query($queryrrr);

$querysss="update nuke_scout_rookieratings set player_def = defo+defd+defp+deft";
$resultsss=mysql_query($querysss);

$queryttt="update nuke_scout_rookieratings set player_tsi = tal+skl+intan";
$resultttt=mysql_query($queryttt);

$queryuuu="alter table nuke_scout_rookieratings drop offo";
$resultuuu=mysql_query($queryuuu);

$queryvvv="alter table nuke_scout_rookieratings drop offd";
$resultvvv=mysql_query($queryvvv);

$querywww="alter table nuke_scout_rookieratings drop offp";
$resultwww=mysql_query($querywww);

$queryxxx="alter table nuke_scout_rookieratings drop offt";
$resultxxx=mysql_query($queryxxx);

$queryyyy="alter table nuke_scout_rookieratings drop defo";
$resultyyy=mysql_query($queryyyy);

$queryzzz="alter table nuke_scout_rookieratings drop defd";
$resultzzz=mysql_query($queryzzz);

$query1="alter table nuke_scout_rookieratings drop defp";
$result1=mysql_query($query1);

$query2="alter table nuke_scout_rookieratings drop deft";
$result2=mysql_query($query2);

$query3="alter table nuke_scout_rookieratings drop tal";
$result3=mysql_query($query3);

$query4="alter table nuke_scout_rookieratings drop skl";
$result4=mysql_query($query4);

$query5="alter table nuke_scout_rookieratings drop intan";
$result5=mysql_query($query5);

$query6="update nuke_scout_rookieratings set position_id = '5' where position_id = 'C'";
$result6=mysql_query($query6);

$query7="update nuke_scout_rookieratings set position_id = '4' where position_id = 'PF'";
$result7=mysql_query($query7);

$query8="update nuke_scout_rookieratings set position_id = '3' where position_id = 'SF'";
$result8=mysql_query($query8);

$query9="update nuke_scout_rookieratings set position_id = '2' where position_id = 'SG'";
$result9=mysql_query($query9);

$query10="update nuke_scout_rookieratings set position_id = '1' where position_id = 'PG'";
$result10=mysql_query($query10);

$query11="update nuke_scout_rookieratings set player_id = id";
$result11=mysql_query($query11);


$query231="drop table player";
$result231=mysql_query($query231);

$query232="alter table nuke_scout_rookieratings rename to player";
$result232=mysql_query($query232);

$query233="update settings set setting_value = '0' where setting_id = '2'";
$result233=mysql_query($query233);


echo "Draft-o-Matic setup is complete. Do NOT contact Joe. Seriously. Don't do it."


?>
