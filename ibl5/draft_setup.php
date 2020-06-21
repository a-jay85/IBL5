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


$querys1="alter table nuke_scout_rookieratings ADD player_oo int(11)";
$results1=mysql_query($querys1);

$querys2="update nuke_scout_rookieratings set player_oo = `offo`";
$results2=mysql_query($querys2);

$querys3="alter table nuke_scout_rookieratings drop `offo`";
$results3=mysql_query($querys3);

$querys4="alter table nuke_scout_rookieratings ADD player_do int(11)";
$results4=mysql_query($querys4);

$querys5="update nuke_scout_rookieratings set player_do = `offd`";
$results5=mysql_query($querys5);

$querys6="alter table nuke_scout_rookieratings drop `offd`";
$results6=mysql_query($querys6);

$querys7="alter table nuke_scout_rookieratings ADD player_po int(11)";
$results7=mysql_query($querys7);

$querys8="update nuke_scout_rookieratings set player_po = `offp`";
$results8=mysql_query($querys8);

$querys9="alter table nuke_scout_rookieratings drop `offp`";
$results9=mysql_query($querys9);

$querys10="alter table nuke_scout_rookieratings ADD player_to int(11)";
$results10=mysql_query($querys10);

$querys11="update nuke_scout_rookieratings set player_to = `offt`";
$results11=mysql_query($querys11);

$querys12="alter table nuke_scout_rookieratings drop `offt`";
$results12=mysql_query($querys12);

$querys13="alter table nuke_scout_rookieratings ADD player_od int(11)";
$results13=mysql_query($querys13);

$querys14="update nuke_scout_rookieratings set player_od = `defo`";
$results14=mysql_query($querys14);

$querys15="alter table nuke_scout_rookieratings drop `defo`";
$results15=mysql_query($querys15);

$querys16="alter table nuke_scout_rookieratings ADD player_dd int(11)";
$results16=mysql_query($querys16);

$querys17="update nuke_scout_rookieratings set player_dd = `defd`";
$results17=mysql_query($querys17);

$querys18="alter table nuke_scout_rookieratings drop `defd`";
$results18=mysql_query($querys18);

$querys19="alter table nuke_scout_rookieratings ADD player_pd int(11)";
$results19=mysql_query($querys19);

$querys20="update nuke_scout_rookieratings set player_pd = `defp`";
$results20=mysql_query($querys20);

$querys21="alter table nuke_scout_rookieratings drop `defp`";
$results21=mysql_query($querys21);

$querys22="alter table nuke_scout_rookieratings ADD player_td int(11)";
$results22=mysql_query($querys22);

$querys23="update nuke_scout_rookieratings set player_td = `deft`";
$results23=mysql_query($querys23);

$querys24="alter table nuke_scout_rookieratings drop `deft`";
$results24=mysql_query($querys24);

$querys25="alter table nuke_scout_rookieratings ADD player_tal int(11)";
$results25=mysql_query($querys25);

$querys26="update nuke_scout_rookieratings set player_tal = `tal`";
$results26=mysql_query($querys26);

$querys27="alter table nuke_scout_rookieratings drop `tal`";
$results27=mysql_query($querys27);

$querys28="alter table nuke_scout_rookieratings ADD player_skl int(11)";
$results28=mysql_query($querys28);

$querys29="update nuke_scout_rookieratings set player_skl = `skl`";
$results29=mysql_query($querys29);

$querys30="alter table nuke_scout_rookieratings drop `skl`";
$results30=mysql_query($querys30);

$querys31="alter table nuke_scout_rookieratings ADD player_intan int(11)";
$results31=mysql_query($querys31);

$querys32="update nuke_scout_rookieratings set player_intan = `int`";
$results32=mysql_query($querys32);

$querys33="alter table nuke_scout_rookieratings drop `int`";
$results33=mysql_query($querys33);







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

$queryhhh="alter table nuke_scout_rookieratings ADD player_tvr  varchar(2)";
$resulthhh=mysql_query($queryhhh);

$queryiii="update nuke_scout_rookieratings set player_tvr = tvr";
$resultiii=mysql_query($queryiii);

$queryjjj="alter table nuke_scout_rookieratings drop tvr";
$resultjjj=mysql_query($queryjjj);

$querykkk="alter table nuke_scout_rookieratings ADD player_blk  varchar(2)";
$resultkkk=mysql_query($querykkk);

$querylll="update nuke_scout_rookieratings set player_blk = blk";
$resultlll=mysql_query($querylll);

$querymmm="alter table nuke_scout_rookieratings drop blk";
$resultmmm=mysql_query($querymmm);


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
