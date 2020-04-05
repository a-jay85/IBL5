<?php

$username = "iblhoops";
$password = "Underthedome19!";
$database = "iblhoops_v4draft";

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

$queryt="alter table nuke_scout_rookieratings ADD player_id  int(2) auto_increment primary key";
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

$query11="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '0'";
$result11=mysql_query($query11);

$query12="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '1'";
$result12=mysql_query($query12);

$query13="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '2'";
$result13=mysql_query($query13);

$query14="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '3'";
$result14=mysql_query($query14);

$query15="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '4'";
$result15=mysql_query($query15);

$query16="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '5'";
$result16=mysql_query($query16);

$query17="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '6'";
$result17=mysql_query($query17);

$query18="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '7'";
$result18=mysql_query($query18);

$query19="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '8'";
$result19=mysql_query($query19);

$query20="update nuke_scout_rookieratings set player_fga = 'F' where player_fga = '9'";
$result20=mysql_query($query20);

$query21="update nuke_scout_rookieratings set player_fga = 'F' where player_fga between '10' and '19'";
$result21=mysql_query($query21);

$query22="update nuke_scout_rookieratings set player_fga = 'D' where player_fga between '20' and '39'";
$result22=mysql_query($query22);

$query23="update nuke_scout_rookieratings set player_fga = 'C' where player_fga between '40' and '59'";
$result23=mysql_query($query23);

$query24="update nuke_scout_rookieratings set player_fga = 'B' where player_fga between '60' and '79'";
$result24=mysql_query($query24);

$query25="update nuke_scout_rookieratings set player_fga = 'A' where player_fga between '80' and '99'";
$result25=mysql_query($query25);

$query26="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '0'";
$result26=mysql_query($query26);

$query27="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '1'";
$result27=mysql_query($query27);

$query28="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '2'";
$result28=mysql_query($query28);

$query29="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '3'";
$result29=mysql_query($query29);

$query30="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '4'";
$resulta30=mysql_query($query30);

$query31="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '5'";
$result31=mysql_query($query31);

$query32="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '6'";
$result32=mysql_query($query32);

$query33="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '7'";
$result33=mysql_query($query33);

$query34="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '8'";
$result34=mysql_query($query34);

$query35="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp = '9'";
$result35=mysql_query($query35);

$query36="update nuke_scout_rookieratings set player_fgp = 'F' where player_fgp between '10' and '40'";
$result36=mysql_query($query36);

$query37="update nuke_scout_rookieratings set player_fgp = 'D' where player_fgp between '41' and '43'";
$result37=mysql_query($query37);

$query38="update nuke_scout_rookieratings set player_fgp = 'C' where player_fgp between '44' and '46'";
$result38=mysql_query($query38);

$query39="update nuke_scout_rookieratings set player_fgp = 'B' where player_fgp between '47' and '49'";
$result39=mysql_query($query39);

$query40="update nuke_scout_rookieratings set player_fgp = 'A' where player_fgp between '50' and '99'";
$result40=mysql_query($query40);

$query41="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '0'";
$result41=mysql_query($query41);

$query42="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '1'";
$result42=mysql_query($query42);

$query43="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '2'";
$result43=mysql_query($query43);

$query44="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '3'";
$result44=mysql_query($query44);

$query45="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '4'";
$result45=mysql_query($query45);

$query46="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '5'";
$result46=mysql_query($query46);

$query47="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '6'";
$result47=mysql_query($query47);

$query48="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '7'";
$result48=mysql_query($query48);

$query49="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '8'";
$result49=mysql_query($query49);

$query50="update nuke_scout_rookieratings set player_fta = 'F' where player_fta = '9'";
$result50=mysql_query($query50);

$query51="update nuke_scout_rookieratings set player_fta = 'F' where player_fta between '10' and '19'";
$result51=mysql_query($query51);

$query52="update nuke_scout_rookieratings set player_fta = 'D' where player_fta between '20' and '39'";
$result52=mysql_query($query52);

$query53="update nuke_scout_rookieratings set player_fta = 'C' where player_fta between '40' and '59'";
$result53=mysql_query($query53);

$query54="update nuke_scout_rookieratings set player_fta = 'B' where player_fta between '60' and '79'";
$result54=mysql_query($query54);

$query55="update nuke_scout_rookieratings set player_fta = 'A' where player_fta between '80' and '99'";
$result55=mysql_query($query55);

$query56="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '0'";
$result56=mysql_query($query56);

$query57="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '1'";
$result57=mysql_query($query57);

$query58="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '2'";
$result58=mysql_query($query58);

$query59="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '3'";
$result59=mysql_query($query59);

$query60="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '4'";
$result60=mysql_query($query60);

$query61="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '5'";
$result61=mysql_query($query61);

$query62="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '6'";
$result62=mysql_query($query62);

$query63="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '7'";
$result63=mysql_query($query63);

$query64="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '8'";
$result64=mysql_query($query64);

$query65="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp = '9'";
$result65=mysql_query($query65);

$query66="update nuke_scout_rookieratings set player_ftp = 'F' where player_ftp between '10' and '68'";
$result66=mysql_query($query66);

$query67="update nuke_scout_rookieratings set player_ftp = 'D' where player_ftp between '69' and '72'";
$result67=mysql_query($query67);

$query68="update nuke_scout_rookieratings set player_ftp = 'C' where player_ftp between '71' and '76'";
$result68=mysql_query($query68);

$query69="update nuke_scout_rookieratings set player_ftp = 'B' where player_ftp between '77' and '80'";
$result69=mysql_query($query69);

$query70="update nuke_scout_rookieratings set player_ftp = 'A' where player_ftp between '81' and '99'";
$result70=mysql_query($query70);

$query71="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '0'";
$result71=mysql_query($query71);

$query72="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '1'";
$result72=mysql_query($query72);

$query73="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '2'";
$result73=mysql_query($query73);

$query74="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '3'";
$result74=mysql_query($query74);

$query75="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '4'";
$result75=mysql_query($query75);

$query76="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '5'";
$result76=mysql_query($query76);

$query77="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '6'";
$result77=mysql_query($query77);

$query78="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '7'";
$result78=mysql_query($query78);

$query79="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '8'";
$result79=mysql_query($query79);

$query80="update nuke_scout_rookieratings set player_tga = 'F' where player_tga = '9'";
$result80=mysql_query($query80);

$query81="update nuke_scout_rookieratings set player_tga = 'F' where player_tga between '10' and '19'";
$result81=mysql_query($query81);

$query82="update nuke_scout_rookieratings set player_tga = 'D' where player_tga between '20' and '39'";
$result82=mysql_query($query82);

$query83="update nuke_scout_rookieratings set player_tga = 'C' where player_tga between '40' and '59'";
$result83=mysql_query($query83);

$query84="update nuke_scout_rookieratings set player_tga = 'B' where player_tga between '60' and '79'";
$result84=mysql_query($query84);

$query85="update nuke_scout_rookieratings set player_tga = 'A' where player_tga between '80' and '99'";
$result85=mysql_query($query85);

$query86="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '0'";
$result86=mysql_query($query86);

$query87="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '1'";
$result87=mysql_query($query87);

$query88="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '2'";
$result88=mysql_query($query88);

$query89="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '3'";
$result89=mysql_query($query89);

$query90="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '4'";
$result90=mysql_query($query90);

$query91="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '5'";
$result91=mysql_query($query91);

$query92="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '6'";
$result92=mysql_query($query92);

$query93="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '7'";
$result93=mysql_query($query93);

$query94="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '8'";
$result94=mysql_query($query94);

$query95="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp = '9'";
$result95=mysql_query($query95);

$query96="update nuke_scout_rookieratings set player_tgp = 'F' where player_tgp between '10' and '27'";
$result96=mysql_query($query96);

$query97="update nuke_scout_rookieratings set player_tgp = 'D' where player_tgp between '28' and '31'";
$result97=mysql_query($query97);

$query98="update nuke_scout_rookieratings set player_tgp = 'C' where player_tgp between '32' and '35'";
$result98=mysql_query($query98);

$query99="update nuke_scout_rookieratings set player_tgp = 'B' where player_tgp between '36' and '39'";
$result99=mysql_query($query99);

$query100="update nuke_scout_rookieratings set player_tgp = 'A' where player_tgp between '40' and '99'";
$result100=mysql_query($query100);

$query101="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '0'";
$result101=mysql_query($query101);

$query102="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '1'";
$result102=mysql_query($query102);

$query103="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '2'";
$result103=mysql_query($query103);

$query104="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '3'";
$result104=mysql_query($query104);

$query105="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '4'";
$result105=mysql_query($query105);

$query106="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '5'";
$result106=mysql_query($query106);

$query107="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '6'";
$result107=mysql_query($query107);

$query108="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '7'";
$result108=mysql_query($query108);

$query109="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '8'";
$result109=mysql_query($query109);

$query110="update nuke_scout_rookieratings set player_orb = 'F' where player_orb = '9'";
$result110=mysql_query($query110);

$query111="update nuke_scout_rookieratings set player_orb = 'F' where player_orb between '10' and '19'";
$result111=mysql_query($query111);

$query112="update nuke_scout_rookieratings set player_orb = 'D' where player_orb between '20' and '39'";
$result112=mysql_query($query112);

$query113="update nuke_scout_rookieratings set player_orb = 'C' where player_orb between '40' and '59'";
$result113=mysql_query($query113);

$query114="update nuke_scout_rookieratings set player_orb = 'B' where player_orb between '60' and '79'";
$result114=mysql_query($query114);

$query115="update nuke_scout_rookieratings set player_orb = 'A' where player_orb between '80' and '99'";
$result115=mysql_query($query115);

$query116="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '0'";
$result116=mysql_query($query116);

$query117="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '1'";
$result117=mysql_query($query117);

$query118="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '2'";
$result118=mysql_query($query118);

$query119="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '3'";
$result119=mysql_query($query119);

$query120="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '4'";
$result120=mysql_query($query120);

$query121="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '5'";
$result121=mysql_query($query121);

$query122="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '6'";
$result122=mysql_query($query122);

$query123="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '7'";
$result123=mysql_query($query123);

$query124="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '8'";
$result124=mysql_query($query124);

$query125="update nuke_scout_rookieratings set player_drb = 'F' where player_drb = '9'";
$result125=mysql_query($query125);

$query126="update nuke_scout_rookieratings set player_drb = 'F' where player_drb between '10' and '19'";
$result126=mysql_query($query126);

$query127="update nuke_scout_rookieratings set player_drb = 'D' where player_drb between '20' and '39'";
$result127=mysql_query($query127);

$query128="update nuke_scout_rookieratings set player_drb = 'C' where player_drb between '40' and '59'";
$result128=mysql_query($query128);

$query129="update nuke_scout_rookieratings set player_drb = 'B' where player_drb between '60' and '79'";
$result129=mysql_query($query129);

$query130="update nuke_scout_rookieratings set player_drb = 'A' where player_drb between '80' and '99'";
$result130=mysql_query($query130);

$query131="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '0'";
$result131=mysql_query($query131);

$query132="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '1'";
$result132=mysql_query($query132);

$query133="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '2'";
$result133=mysql_query($query133);

$query134="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '3'";
$result134=mysql_query($query134);

$query135="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '4'";
$result135=mysql_query($query135);

$query136="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '5'";
$result136=mysql_query($query136);

$query137="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '6'";
$result137=mysql_query($query137);

$query138="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '7'";
$result138=mysql_query($query138);

$query139="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '8'";
$result139=mysql_query($query139);

$query140="update nuke_scout_rookieratings set player_ast = 'F' where player_ast = '9'";
$result140=mysql_query($query140);

$query141="update nuke_scout_rookieratings set player_ast = 'F' where player_ast between '10' and '19'";
$result141=mysql_query($query141);

$query142="update nuke_scout_rookieratings set player_ast = 'D' where player_ast between '20' and '39'";
$result142=mysql_query($query142);

$query143="update nuke_scout_rookieratings set player_ast = 'C' where player_ast between '40' and '59'";
$result143=mysql_query($query143);

$query144="update nuke_scout_rookieratings set player_ast = 'B' where player_ast between '60' and '79'";
$result144=mysql_query($query144);

$query145="update nuke_scout_rookieratings set player_ast = 'A' where player_ast between '80' and '99'";
$result145=mysql_query($query145);

$query146="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '0'";
$result146=mysql_query($query146);

$query147="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '1'";
$resul147=mysql_query($query147);

$query148="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '2'";
$result148=mysql_query($query148);

$query149="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '3'";
$result149=mysql_query($query149);

$query150="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '4'";
$result150=mysql_query($query150);

$query151="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '5'";
$result151=mysql_query($query151);

$query152="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '6'";
$result152=mysql_query($query152);

$query153="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '7'";
$result153=mysql_query($query153);

$query154="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '8'";
$result154=mysql_query($query154);

$query155="update nuke_scout_rookieratings set player_stl = 'F' where player_stl = '9'";
$result155=mysql_query($query155);

$query156="update nuke_scout_rookieratings set player_stl = 'F' where player_stl between '10' and '19'";
$result156=mysql_query($query156);

$query157="update nuke_scout_rookieratings set player_stl = 'D' where player_stl between '20' and '39'";
$result157=mysql_query($query157);

$query158="update nuke_scout_rookieratings set player_stl = 'C' where player_stl between '40' and '59'";
$result158=mysql_query($query158);

$query159="update nuke_scout_rookieratings set player_stl = 'B' where player_stl between '60' and '79'";
$result159=mysql_query($query159);

$query160="update nuke_scout_rookieratings set player_stl = 'A' where player_stl between '80' and '99'";
$result160=mysql_query($query160);

$query161="update nuke_scout_rookieratings set player_to = 'F' where player_to = '0'";
$result161=mysql_query($query161);

$query162="update nuke_scout_rookieratings set player_to = 'F' where player_to = '1'";
$result162=mysql_query($query162);

$query163="update nuke_scout_rookieratings set player_to = 'F' where player_to = '2'";
$result163=mysql_query($query163);

$query164="update nuke_scout_rookieratings set player_to = 'F' where player_to = '3'";
$result164=mysql_query($query164);

$query165="update nuke_scout_rookieratings set player_to = 'F' where player_to = '4'";
$result165=mysql_query($query165);

$query166="update nuke_scout_rookieratings set player_to = 'F' where player_to = '5'";
$result166=mysql_query($query166);

$query167="update nuke_scout_rookieratings set player_to = 'F' where player_to = '6'";
$result167=mysql_query($query167);

$query168="update nuke_scout_rookieratings set player_to = 'F' where player_to = '7'";
$result168=mysql_query($query168);

$query169="update nuke_scout_rookieratings set player_to = 'F' where player_to = '8'";
$result169=mysql_query($query169);

$query170="update nuke_scout_rookieratings set player_to = 'F' where player_to = '9'";
$result170=mysql_query($query170);

$query171="update nuke_scout_rookieratings set player_to = 'F' where player_to between '10' and '19'";
$result171=mysql_query($query171);

$query172="update nuke_scout_rookieratings set player_to = 'D' where player_to between '20' and '39'";
$result172=mysql_query($query172);

$query173="update nuke_scout_rookieratings set player_to = 'C' where player_to between '40' and '59'";
$result173=mysql_query($query173);

$query174="update nuke_scout_rookieratings set player_to = 'B' where player_to between '60' and '79'";
$result174=mysql_query($query174);

$query175="update nuke_scout_rookieratings set player_to = 'A' where player_to between '80' and '99'";
$result175=mysql_query($query175);

$query176="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '0'";
$result176=mysql_query($query176);

$query177="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '1'";
$result177=mysql_query($query177);

$query178="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '2'";
$result178=mysql_query($query178);

$query179="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '3'";
$result179=mysql_query($query179);

$query180="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '4'";
$result180=mysql_query($query180);

$query181="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '5'";
$result181=mysql_query($query181);

$query182="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '6'";
$result182=mysql_query($query182);

$query183="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '7'";
$result183=mysql_query($query183);

$query184="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '8'";
$result184=mysql_query($query184);

$query185="update nuke_scout_rookieratings set player_blk = 'F' where player_blk = '9'";
$result185=mysql_query($query185);

$query186="update nuke_scout_rookieratings set player_blk = 'F' where player_blk between '10' and '19'";
$result186=mysql_query($query186);

$query187="update nuke_scout_rookieratings set player_blk = 'D' where player_blk between '20' and '39'";
$result187=mysql_query($query187);

$query188="update nuke_scout_rookieratings set player_blk = 'C' where player_blk between '40' and '59'";
$result188=mysql_query($query188);

$query189="update nuke_scout_rookieratings set player_blk = 'B' where player_blk between '60' and '79'";
$result189=mysql_query($query189);

$query190="update nuke_scout_rookieratings set player_blk = 'A' where player_blk between '80' and '99'";
$result190=mysql_query($query190);

$query191="update nuke_scout_rookieratings set player_off = 'F' where player_off = '0'";
$result191=mysql_query($query191);

$query192="update nuke_scout_rookieratings set player_off = 'F' where player_off = '1'";
$result192=mysql_query($query192);

$query193="update nuke_scout_rookieratings set player_off = 'F' where player_off = '2'";
$result193=mysql_query($query193);

$query194="update nuke_scout_rookieratings set player_off = 'F' where player_off = '3'";
$result194=mysql_query($query194);

$query195="update nuke_scout_rookieratings set player_off = 'F' where player_off = '4'";
$resulta195=mysql_query($query195);

$query196="update nuke_scout_rookieratings set player_off = 'F' where player_off = '5'";
$result196=mysql_query($query196);

$query197="update nuke_scout_rookieratings set player_off = 'F' where player_off = '6'";
$result197=mysql_query($query197);

$query198="update nuke_scout_rookieratings set player_off = 'F' where player_off = '7'";
$result198=mysql_query($query198);

$query199="update nuke_scout_rookieratings set player_off = 'F' where player_off = '8'";
$result199=mysql_query($query199);

$query200="update nuke_scout_rookieratings set player_off = 'F' where player_off = '9'";
$result200=mysql_query($query200);

$query201="update nuke_scout_rookieratings set player_off = 'D' where player_off between '10' and '16'";
$result201=mysql_query($query201);

$query202="update nuke_scout_rookieratings set player_off = 'C' where player_off between '17' and '23'";
$result202=mysql_query($query202);

$query203="update nuke_scout_rookieratings set player_off = 'B' where player_off between '24' and '29'";
$result203=mysql_query($query203);

$query204="update nuke_scout_rookieratings set player_off = 'A' where player_off between '30' and '99'";
$result204=mysql_query($query204);

$query205="update nuke_scout_rookieratings set player_def = 'F' where player_def = '0'";
$result205=mysql_query($query205);

$query206="update nuke_scout_rookieratings set player_def = 'F' where player_def = '1'";
$result206=mysql_query($query206);

$query207="update nuke_scout_rookieratings set player_def = 'F' where player_def = '2'";
$result207=mysql_query($query207);

$query208="update nuke_scout_rookieratings set player_def = 'F' where player_def = '3'";
$result208=mysql_query($query208);

$query209="update nuke_scout_rookieratings set player_def = 'F' where player_def = '4'";
$result209=mysql_query($query209);

$query210="update nuke_scout_rookieratings set player_def = 'F' where player_def = '5'";
$result210=mysql_query($query210);

$query211="update nuke_scout_rookieratings set player_def = 'F' where player_def = '6'";
$result211=mysql_query($query211);

$query212="update nuke_scout_rookieratings set player_def = 'F' where player_def = '7'";
$result212=mysql_query($query212);

$query213="update nuke_scout_rookieratings set player_def = 'F' where player_def = '8'";
$result213=mysql_query($query213);

$query214="update nuke_scout_rookieratings set player_def = 'F' where player_def = '9'";
$result214=mysql_query($query214);

$query215="update nuke_scout_rookieratings set player_def = 'D' where player_def between '10' and '16'";
$result215=mysql_query($query215);

$query216="update nuke_scout_rookieratings set player_def = 'C' where player_def between '17' and '23'";
$result216=mysql_query($query216);

$query217="update nuke_scout_rookieratings set player_def = 'B' where player_def between '24' and '29'";
$result217=mysql_query($query217);

$query218="update nuke_scout_rookieratings set player_def = 'A' where player_def between '30' and '99'";
$result218=mysql_query($query218);

$query219="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '0'";
$result219=mysql_query($query219);

$query220="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '1'";
$result220=mysql_query($query220);

$query221="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '2'";
$result221=mysql_query($query221);

$query222="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '3'";
$result222=mysql_query($query222);

$query223="update nuke_scout_rookieratings set player_tsi = 'F' where player_tsi = '4'";
$result223=mysql_query($query223);

$query224="update nuke_scout_rookieratings set player_tsi = 'D' where player_tsi = '5'";
$result224=mysql_query($query224);

$query225="update nuke_scout_rookieratings set player_tsi = 'D' where player_tsi = '6'";
$result225=mysql_query($query225);

$query226="update nuke_scout_rookieratings set player_tsi = 'D' where player_tsi = '7'";
$result226=mysql_query($query226);

$query227="update nuke_scout_rookieratings set player_tsi = 'C' where player_tsi = '8'";
$result227=mysql_query($query227);

$query228="update nuke_scout_rookieratings set player_tsi = 'C' where player_tsi = '9'";
$result228=mysql_query($query228);

$query234="update nuke_scout_rookieratings set player_tsi = 'C' where player_tsi = '10'";
$result234=mysql_query($query234);

$query229="update nuke_scout_rookieratings set player_tsi = 'B' where player_tsi between '11' and '13'";
$result229=mysql_query($query229);

$query230="update nuke_scout_rookieratings set player_tsi = 'A' where player_tsi between '14' and '99'";
$result230=mysql_query($query230);

$query231="drop table player";
$result231=mysql_query($query231);

$query232="alter table nuke_scout_rookieratings rename to player";
$result232=mysql_query($query232);

$query233="update settings set setting_value = '0' where setting_id = '2'";
$result233=mysql_query($query233);


echo "Draft-o-Matic setup is complete. Do NOT contact Joe. Seriously. Don't do it."


?>