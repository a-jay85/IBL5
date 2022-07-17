<?php
/***************************************************************************
 *                          lang_faq.php [english]
 *                            -------------------
 *   begin                : Wednesday Oct 3, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: lang_faq.php,v 1.4 2001/12/15 16:42:08 psotfx Exp $
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

//
// To add an entry to your FAQ simply add a line to this file in this format:
// $faq[] = array("question", "answer");
// If you want to separate a section enter $faq[] = array("--","Block heading goes here if wanted");
// Links will be created automatically
//
// DO NOT forget the ; at the end of the line.
// Do NOT put double quotes (") in your FAQ entries, if you absolutely must then escape them ie. \"something\"
//
// The FAQ items will appear on the FAQ page in the same order they are listed in this file
//

$faq[] = array("--", "Sisselogimine ja liitumine");
$faq[] = array("Miks ma ei saa sisse logida?", "Olete liitunud? Selleks, et sisse logida tuleb k�igepealt liituda. On teid foorumilt eemaldatud (sellisel juhul saate te vastava teate)? Kui nii on juhtunud, siis te peaksite �hendust v�tma foorumi administraatoriga, et uurida v�lja eemaldamise p�hjus. Kui olete liitunud ja pole eemaldatud, siis kontrollige veelkord �le oma kasutajanimi ja parool. Tavaliselt peitub viga viimases, kui ei, siis p��rduge foorumi administraatori poole.");
$faq[] = array("Miks ma peaks liituma?", "Te ei pea seda tingimata tegema, foorumi administraatori otsustada, kas saate liitumata postitada teateid. Kuna liitumine annab teile juurdep��su lisav�imalustele, mis ei ole k�lalistele k�ttesaadavad (n�iteks kindlad kasutaja pildid, eras�numite saatmine ja saamine, kasutajatele e-kirjade saatmine,kasutajagruppidesse kuulumine jne). Liitumine v�tab vaid m�ne hetke ja on seda v��rt.");
$faq[] = array("Miks mind logitakse automaatselt v�lja?", "Kui te ei tee linnukest <i>Logi mind automaatselt sisse</i> kasti, siis j��te sisselogituks vaid lehek�lje kasutamise ajaks. See v�ldib teie kasutajakonto kuritarvitamist teiste poolt. Et automaatselt sisse logida teosta vastav valik sisselogimisel, see pole aga soovitav kui kasutate foorumit avalikust arvutist, n�iteks raamatukogus, internetikohvikus, �likooli arvutiklassis, jne.");
$faq[] = array("Kuidas ma saaks v�ltida oma kasutajanime sattumist foorumilolijate nimekirja?", "Oma profiilis leiate valiku <i>Varja minu foorumilolekut</i>, kui l�litate selle <i>sisse</i>, on teid n�ha ainult administraatorile ja iseendale. Teid loetakse kui varjatud kasutajat.");
$faq[] = array("Ma unustasin oma parooli!", "�rge sattuge paanikasse! Kuigi teie parooli ei saa taastada, saab m��rata teile uue parooli. Selleks minge sisselogimis lehele ja valige sealt <u>Olen unustanud oma parooli</u>, j�rgige instruktsioone ja peaksite peagi foorumil tagasi olema.");
$faq[] = array("Liitusin kunagi, kuid ei saa enam sisse logida?!", "T�en�olisemad p�hjused on; sisestasite vale kasutajanime ja parooli (vaadake need �le e-kirjast, mis teile liitumise puhul saadeti) v�i administraator on mingil p�hjusel teie kasutajakonto kustutanud. Proovige uuesti registreeruda v�i v�tke �hendust administraatoriga.");

$faq[] = array("--", "Kasutaja eelistused ja seaded");
$faq[] = array("Kuidas saan ma oma seadeid muuta?", "K�iki teie seaded (juhul kui olete liitunud) on salvestatud andmebaasi. Et neid muuta, vajutage linki <u>Profiil</u> (tavaliselt n�htaval lehe �laosas). Sealt saate muuta k�iki oma seadeid");
$faq[] = array("Aeg pole �ige!", "�snagi t�en�oliselt on aeg �ige, aga viga v�ib olla selles, et ajav��nd erineb sellest, kus te viibite. Kui viga paistab selles olevat, siis muutke oma profiili alt ajav��nd �igeks.");
$faq[] = array("Muutsin ajatsooni, kuid aeg on ikka vale!", "Kui olete veendunud, et ajav��nd on �ige, aga kell on vale, siis v�ib p�hjus peituda suve- ja talveaja erinevuses. Kahjuks ei ole antud foorum v�imeline selle muutusega kaasas k�ima.");
$faq[] = array("Minu keelt pole nimekirjas!", "K�ige t�en�olisemalt ei ole administraator seda keelt paigaldanud (v�i see on veel t�lkimata). P��rduge oma murega administraatori poole");
$faq[] = array("Kuidas saan ma panna oma nime alla pildi?", "Kasutajanime all olevaid pilte v�ib olla kahte t��pi. Esimene on seotud teie tiitliga viitamaks selle, kui palju te olete foorumile teateid postitanud v�i milline on teie staatus foorumil (tavaliselt t�rnid v�i kuubikud). Selle all on suurem pilt, mida tuntakse 'Avatari' nime all, reeglina on see igal kasutajal erinev. Foorumi administraatorile j��b otsustada, kas 'avatarid' on lubatud ja kui on, siis mil moel. Kui sa ei saa 'avatare' kasutada, siis see on administraatori otsust, kuid te v�ite alati tema poole p��rduda sellekohase k�simusega.");
$faq[] = array("Kuidas ma saan muuta oma tiitlit?", "Reeglina ei saa te �htegi tiitli s�nastust otseselt m�jutada (tiitlid ilmuvad teie kasutajanime all postitatud teemades v�i teie infolehel). Enamik foorumeid kasutab tiitleid, et viidata palju kasutaja on postitanud v�i siis tema erilise staatusele, n�iteks moderatooritel ja administratoritel v�ivad olla erilised tiitlid. Ei ole m�tteks parema tiitli saamise eesm�rgil postitada suurt hulka sisu vaeseid teateid - tulemuseks on administraatori/moderaatori sekkumine ja efekt saab olema oodatust vastupidine.");
$faq[] = array("Kui vajutan kasutaja e-posti linki, siis palutakse mul sisse logida?", "Ainult liitunud kasutajad saavad kasutada foorumisse sisse ehitatud e-posti klienti (kui administraator on selle valiku v�imaldanud). P�hjus selleks on soov v�ltida e-posti kliendi kuritarvitamist.");

$faq[] = array("--", "Teadete postitamisega seotud k�simused");
$faq[] = array("Kuidas ma saan foorumisse teadet postitada?", "Vajutage vastavale nupule kas siis foorumi- v�i teemalehel. V�ibolla peate te teate postitamiseks liituma, omale saadaolevaid v�imalusi v�ite te n�ha lehe alumises ��res vasakul paiknevas nimekirjas (<i>Te saate postitada uusi teateid, Te saate k�sitlusel h��letada, jne.<i> nimekiri)");
$faq[] = array("Kuidas ma saan oma teadet muuta v�i kustutada?", "Kui te pole foorumi moderaator v�i administraator, siis saate muuta ja kustuda vaid enda teateid. Te saate teated muuta (m�nel juhul v�ib teadet muuta vaid piiratud aja jooksul) vajutades <i>muuda</i> nuppu asjasse puutuva teate juures.  Kui keegi on juba teatele vastanud, siis leiate v�ikse kirje teate all, kui p��rdute tagasi teate juurde - seal on kirjas mitu korda ja kunas viimati muutmine toimus. See kirje ilmub ainult siis kui keegi on eelnevalt teatele vastanud v�i muutjaks on moderaator/administraator  (nemad peaksid ka j�tma teate, milles seletavad muutmise p�hjusi). T�hele tuleks panna ka asjaolu, et tavakasutajad ei saa teated kustutada, kui selle on vastatud.");
$faq[] = array("Kuidas ma saan oma teatele allkirja lisada?", "Selleks, et teatele allkirja lisada, tuleb viimane k�igepealt luua, seda saab teha oma profiilist. Kui allkiri on olemas, siis saate postitusvormis teha linnuke <i>Lisa allkiri</i> kasti, et lisada teatele allkirja. Allkirja saab ka k�igile teadetele vaikimisi lisada, kui m�rgistate nii vastava valiku oma profiilis (teile j��b iga individuaalse teate postitamise korral v�imalus allkirjast loobuda eemaldades linnuke <i>Lisa allkiri</i> kastist)");
$faq[] = array("Kuidas ma saan algatada k�sitlust?", "K�sitluste algatamine on lihte - kui postitad teema (v�i muudate teema esimest teadet, kui teil on selleks �igus) peaksite te n�gema <i>Lisa k�sitlus</i> vormi, mis asu p�hi postitusvormi all (kui te seda ei n�e, siis arvatavasti puuduvad teil �igused k�sitluse algatamiseks). Te peaksite sisestama k�sitluse pealkirja ja v�hemalt kaks vastusevarianti (selleks, et m��rata vastusevarianti sisestage see vastavale reale ja kasutage<i>Lisa vastusevariant</i> nuppu. K�sitlusele saab ka m��rata ajalimiidi, 0 t�hendab piiramatut aega. Valikuv�imaluste arv v�ib olla piiratud, selle m��rab foorumi administraator");
$faq[] = array("Kuidas ma saan k�sitlust muuta v�i kustutada?", "Nagu ka teadetega, saab k�sitlust muuta v�i kustutada postitaja, moderaator v�i siis administraator. Selleks, et k�sitlust muuta, vajuta teema esimest teadet(k�sitlus on alati sellega seotud). Kui keegi pole veel h��letanud, siis saavad kasutajad muuta v�i kustutada oma k�sitlust ja lisada v�i eemaldada vastusevariante. Samas kui keegi on h��letanud, saavad k�sitlust muuta v�i kustutada vaid moderaatorid ja administraatorid. Selle reegli abil v�lditakse k�sitlustulemuste m�jutamist m�nede variantide eemaldamisega k�sitluse keskel");
$faq[] = array("Miks ma ei p��se foorumile ligi?", "M�ned foorumid v�ivad olla seotud kindlate kasutajate ja gruppidega. Selleks, et kinnises foorumis teateid n�ha, lugeda ja postitada on vaja vastavaid �igusi. Selleks on vajalik foorumi moderaatori v�i administraatori kinnitus, t�psema info saamiseks v�tke nendega �hendust.");
$faq[] = array("Miks ma ei saa k�sitlustel h��letada?", "Ainult liitunud kasutajad saavad k�sitlustel h��letada. Kui olete liitunud, kuid ikkagi ei saa h��letada, siis puuduvad teie kasutajal selleks vastavad �igused.");

$faq[] = array("--", "Kujundamine ja teemade t��bid");
$faq[] = array("Mis on BBCode?", "BBCode on spetsiaalne HTML-i kasutusviis, selle kasutamise v�imalus s�ltub administraatorist (ning seda saab keelata iga postituse kohta eraldi postitusvormist). BBCode on iseenesest sarnane HTML-ile, k�sud suletakse kantsulgudesse [ ja ] koonussulgude  &lt; ja &gt; asemel ning ta pakub paremat kontrolli selle �le mida ja kuidas kuvatakse. Enama informatsiooni saamiseks vaata BBCode-i �petust, mis on k�ttesaadav postitusvormist.");
$faq[] = array("Kas ma saan HTML-i kasutada?", "See s�ltub sellest, kas administraator lubab seda. Isegi kui HTML on lubatud, ei pruugi enamik k�ske t��tada. See on nii <i>turvalisuse tagamiseks</i>, sobimatud HTML k�sud v�iksid v��rata foorumi v�ljan�gemist ja eesm�rke. Kui HTML on lubatud, siis saab seda iga posti kohta eraldi keelata postitusvormist.");
$faq[] = array("Mis on 'emotsioonid'?", "Emotsioonid on v�ikesed graafilised pildid, mida saab kasutada, et n�idata oma tundeid. Emotsioonid kutsutakse esile lihtsa koodiga [n�iteks :) t�hendab r��mu ja :( kurbust]. T�ielik kasutada olev emotsioonide valik on n�ha postitusvormis. �rge emotsioonidega liiale minge, kuna nad v�ivad muuta teate halvasti loetavaks ning paremal juhul eemaldab moderaator liigsed emotsioonid, halvemal juhul terve teie teate.");
$faq[] = array("Kas ma saan teatesse pilte panna?", "Pildid on teadetes n�ha. Kuna aga puudub vahend piltide �leslaadimiseks, siis te peate pildid linkima avalikult k�ttesaadavast serverist (n�iteks http://www.mingi-koht.net/minu-pilt.gif). Te ei saa linkida pilte, mis asuvad teie arvutis (v�ljaarvatud juhul, kui te olete avaliku serverina funktsioneeriva masina taga), samuti ei saa kuvada pilte, mis asuvada parooliga kaitstud keskkondades (n�iteks hotmail-i v�i mail.ee kirjakastis, parooliga kaitstud lehel jne). Pildi kuvamiseks kasutada vastavat BBCode-i [img] k�sku v�i HTML-i (kui see on lubatud).");
$faq[] = array("Mis on teadaanded?", "Teadaanded sisaldavad tavaliselt t�htsat informatsiooni, mida tuleks lugeda esimesel v�imalusel. Teadaanne ilmub iga lehe �laossa, mis asuvad foorumis kuhu teadaanne postitati. See, kes saab ja kes ei saa teadaandeid postitada, s�ltub administraatorist.");
$faq[] = array("Mille poolest erinevad kleepsud?", "Kleepsud kuvatakse ainult foorumi esimesel teadaannete alla ja seda ainult foorumi esimesel lehel. V�ga tihti sisaldavad nad t�htsat informatsiooni ning neid tuleks lugeda. Nagu ka teadaannete puhuk, on foorumi administraator see, kes paneb paika, kellel on �igusi kleepse postitada ja kellel pole..");
$faq[] = array("Miks m�ned teemad on kinni?", "Teemasid pannakse kinni moderaatori v�i administraatori soovil. Kinnistele teemadele ei saa vastata ja k�ik selle teemaga seotud k�sitlused l�ppevad. Teema kinnipanemiseks v�ib olla mitmeid eri p�hjusi.");

$faq[] = array("--", "Kasutaja tasemed ja grupid");
$faq[] = array("Mis t�hendab administraator?", "Administraatorid on inimesed, kellel on antud k�rgeim v�im foorumi �le. Need inimesed kontrollivad k�ik foorumi t�� tahke, muuhulgas �iguste jagamist, kasutajate eemaldamist, kasutajagruppide loomist, moderaatori�iguste andmist jne. Neil on ka moderaatoritele kuuluvad �igused.");
$faq[] = array("Mis t�hendab moderaator?", "Moderaatorid on kasutajad (v�i kasutajate grupid) hoolitseda selle eest, et foorumi t�� sujuks t�rgeteta. Nende v�imuses on teateid muuta ja kustutada ning teemasid poolitada, liigutada, kinni panna ja lahti teha enda poolt modereeritavas foorumis. Reeglina p��avad moderaatorid ohjata <i>teema v�liseid<i> teateid v�i sobimatuid kommentaare.");
$faq[] = array("Mis on kasutajagrupid?", "Kasutajagrupid on �ks viis mida foorumi administraator saab kasutada kasutajate organiseerimiseks. Iga kasutaja v�ib kuuluda mitmesse erinevasse gruppi (m�nel foorumil v�ib see olla teisiti) ja igale grupile saab anda erinevaid juurdep��su �igusi. Sel viisil on administraatoritel lihtne m��rata mitmeid kasutajaid foorumi moderaatoriteks, tagada neile juurdep��s erafoorumitele jne.");
$faq[] = array("Kuidas ma saan mingi kasutajagrupiga liituda?", "Et mingi kasutajagrupiga liituda vajutage kasutajagruppide lingile lehe �laosas (t�pne asukoht s�ltub kasutatavast �abloonist). Sealt saate te vaadata k�iki kasutajagruppe. Mitte k�ik grupid pole <i>Juurdep��suks avatud</i>, m�ned on suletud ja osadel on varjatud liikmelisus. Sel juhul peab grupi moderaator suu soovi heaks kiitma v�i tagasi l�kata.");
$faq[] = array("Kuidas ma saan mingi kasutajagrupi moderaatoriks?", "Kasutajagruppe loob algselt foorumi administraator, tema m��rab ka kindlaks moderaatori(d). Kui soovite luua kasutajagruppi, siis peaksite k�igepealt administraatori pool p��rduma, n�iteks saatke talle eras�num.");

$faq[] = array("--", "Eras�numid (es)");
$faq[] = array("Ma ei saa eras�numeid saata!", "Selleks v�ib olla kolm erinevat p�hjust; Te pole liitunud v�i sisse loginud, administraator on terves foorumis keelanud eras�numite saatmise v�i foorumi administraator hoiab teid s�numeid saamast. Viimasel juhul peaksite te v�lja uurima, miks see nii on.");
$faq[] = array("Ma mulle saadetakse soovimatuid eras�numeid!", "Kui te saate soovimatuid kirju �helt v�i mitmelt kasutajalt, siis teata sellest foorumi administraatori, kelle v�imuses on muuhulgas terve eras�numis�steemi peatamine.");
$faq[] = array("Keegi sellest foorumist on saatnud mulle r�mpsposti v�i ahistava sisuga e-kirja!", "Seda on ��retult kurb kuulda. Selle foorumi e-posti klient omab v�imalusi, et tuvastada sellise teo sooritanud isikuid. Te peaksid terve saadud e-kirja koopia edasi saatma foorumi administraatorile, eriti t�htis on kirja kaasata 'headerid' (need kujutavad endast detailset informatsiooni kirja saatja kohta). Siis saame me midagi ette v�tta, et selline tegu ei korduks.");

//
// These entries should remain in all languages and for all modifications
//
$faq[] = array("--", "phpBB 2-ga seonduv");
$faq[] = array("Kes kirjutas selle teadetetahvli?", "Selle tarkvara (algsel kujul) on loodud, v�lja lastud ja autori�igusega kaitstud <a href=\"http://www.phpbb.com/\" target=\"_blank\">phpBB Group-i poolt</a>. See on k�igile k�ttesaadav GNU �ldise Avaliku Litsentsi alusel ja seda v�ib vabalt jagada, t�psema info saamiseks k�lasta linki");
$faq[] = array("Miks ei ole x v�imalus saadaval?", "See tarkvara kirjutati ja litsentseeriti phpBB Group-i poolt. Kui te tunnete, et midagi vajab lisamist, siis k�lastage aadressi phpbb.com ja vaata, mida phpbb Group sellest arvab. Palun �rge postitage uusi lisav�imaluste palveid phpbb.com foorumile, Group kasutab SourceForge-i uute v�imaluste loomisega kaasneva koormusega toimetulekuks. Palun vaata foorumid l�bi ja veendu selles, et sinu poolt soovitava lisav�imalus suhtes pole juba seisukohta v�etud.");
$faq[] = array("Kelle poole pean ma p��rduma seoses ahistava sisuga e-kirjadega?", "Te peaksite v�tma �hendust foorumi administraatoriga. Kui sa ei leia administraatorit, siis v�ta �hendust moderaatoritega ja uuri nende k�est kuidas esimesega �hendust saada. Kui te ikka ei saa vastust, siis peaksite �hendust v�tma domeeni omanikuga (tehke whois otsing) v�i kui foorum t��tab tasuta teenuselt (n�iteks yahoo, free.fr, f2s.com, jne), siis v�tke �hendust sealse juhatuse v�i kuritarvitamisega tegeleva osakonnaga. Palun v�tke teadmiseks, et phpBB Group-il ei kontrolli olukorda mittemingil moel ja ei saa pidada vastutavaks kuidas, kus v�i kelle poolt seda foorumit kasutatakse. PhpBB Group-iga �hendusev�tmine seoses m�ne �igusliku (cease and desist, liable, defamatory comment, etc.) juhtumiga, mis ei oel otseselt seotud phpbb.com veebilehega v�i konkreetselt phpBB tarkvara puudutava k�simusega, ei oma mingit m�tet. Kui te saadate phpBB Group-ile kirja seoses kolmanda osapoole kohta, siis t�en�oliselt saadetakse teile l�hike vastus v�i ei vastata �ldse.");

//
// This ends the FAQ entries
//
