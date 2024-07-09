<?php
/***************************************************************************
 *                         lang_bbcode.php [english]
 *                            -------------------
 *   begin                : Wednesday Oct 3, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: lang_bbcode.php,v 1.3 2001/12/18 01:53:26 psotfx Exp $
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
// To add an entry to your BBCode guide simply add a line to this file in this format:
// $faq[] = array("question", "answer");
// If you want to separate a section enter $faq[] = array("--","Block heading goes here if wanted");
// Links will be created automatically
//
// DO NOT forget the ; at the end of the line.
// Do NOT put double quotes (") in your BBCode guide entries, if you absolutely must then escape them ie. \"something\"
//
// The BBCode guide items will appear on the BBCode guide page in the same order they are listed in this file
//
// If just translating this file please do not alter the actual HTML unless absolutely necessary, thanks :)
//
// In addition please do not translate the colours referenced in relation to BBCode any section, if you do
// users browsing in your language may be confused to find they're BBCode doesn't work :D You can change
// references which are 'in-line' within the text though.
//

$faq[] = array("--", "Sissejuhatus");
$faq[] = array("Mis on BBCode?", "BBCode on eriline HTML-i kasutusviis. selle, kas saad oma postis BBCode-i kasutada m��rab kindlaks administraator. Lisaks saad sa BBCode-i keelata iga postituse korral eraldi postitusvormist. BBCode sarnaneb stiililt HTML-ile, k�sud on suletud &lt; ja &gt; asemel kantsulgudesse [ ] ning see mis ja kuidas kuvatakse on paremini kontrollitav. S�ltuvalt sellest, millist �ablooni sa kasutad, v�id m�rgata, et BBCode-i kasutamine on muudetud palju lihtsamaks postitusvormis olevate nuppudega. Kuid ka sel juhul v�id j�rgnevast juhust kasulikke m�tteid leida.");

$faq[] = array("--", "Teksti vormistamine");
$faq[] = array("Kuidas luua rasvast, kursiivis v�i allajoonitud teksti", "BBCode sisaldab k�ske, mis v�imaldavad kiiresti muuta teksti algelist kujundust. Selleks on j�rgnevad v�imalused: <ul><li>Et muuta teksti rasvaseks sulgege ta <b>[b][/b]</b> k�suga, n�iteks<br /><br /><b>[b]</b>Tere<b>[/b]</b><br /><br />on <b>Tere</b></li><li>Allajoonimiseks kasuta <b>[u][/u]</b> k�sku, n�iteks:<br /><br /><b>[u]</b>Tere hommikut<b>[/u]</b><br /><br />tulemuseks <u>Tere hommikut</u></li><li>Kursiivi jaoks kasutage <b>[i][/i]</b> k�sku, n�iteks<br /><br />See on <b>[i]</b>m�nus!<b>[/i]</b><br /><br />tulemuseks See on <i>m�nus!</i></li></ul>");
$faq[] = array("Kuidas muuta teksti v�rvi ja suurust", "Teksti v�rvi ja suuruse muutmiseks saab kasutada j�rgnevaid k�ske. Pidage meeles, et kuvatava l�plik v�ljan�gemine s�ltub  vaataja brauserist ja seadetest: <ul><li>Teksti v�rvi saab muuta sidudes ta <b>[color=][/color]</b> k�suga. Te v�ite m��rat tuntumaid v�rve nime pidi (inglise keeles) (n�iteks red, blue, yellow, etc.) v�i siis heksadeemilise kolmeosalise (?) koodiga, n�iteks #FFFFFF, #000000. N�ide: et luua punast teksti v�ib kasutada:<br /><br /><b>[color=red]</b>Tere!<b>[/color]</b><br /><br />v�i<br /><br /><b>[color=#FF0000]</b>Tere!<b>[/color]</b><br /><br />m�lemal juhul on tulemuseks <span style=\"color:red\">Tere!</span></li><li>Teksti suuruse muutmine k�ib sarnaselt kasutades <b>[size=][/size]</b> k�sku. See k�sk on s�ltuvuses kasutatavast �abloonist, kuid soovitatav on arvuline v��rtus, mis viitab teksti suurusele pikslites, alustades numbrist 1 (liiga v�ike, et seda isegi n�ha) kuni numbrini 29 (v�ga suur). N�iteks:<br /><br /><b>[size=9]</b>V�IKE<b>[/size]</b><br /><br />on reeglina <span style=\"font-size:9px\">V�IKE</span><br /><br />samas:<br /><br /><b>[size=24]</b>SUUR!<b>[/size]</b><br /><br />on <span style=\"font-size:24px\">SUUR!</span></li></ul>");
$faq[] = array("Kas ma saan siduda erinevaid kujundusk�ske?", "Jah, see on v�imalik - et p��da t�helepanu v�ite kirjutada:<br /><br /><b>[size=18][color=red][b]</b>VAATA SIIA!<b>[/b][/color][/size]</b><br /><br />, mis kuvatakse kui <span style=\"color:red;font-size:18px\"><b>VAATA SIIA!</b></span><br /><br />Samas pole soovitav enamikku teksti sellises vormingus kuvada! Pidage meeles, et teate saatja kohus on hoolitseda selle eest,et k�sud saaks ka kinni pandud. N�iteks see on vale:<br /><br /><b>[b][u]</b>See on vale<b>[/b][/u]</b>");

$faq[] = array("--", "Viitamine ja kindlate m��tmetega tekst");
$faq[] = array("Viitamine teatele vastates", "Tekstile viitamiseks on kaks viisi, s�nalise viitega ja ilma.<ul><li>Kui te kasutate teate juures olevat Vasta viitega nuppu, siis pannakse originaaltekst teie teatesse kaasa kui <b>[quote=\"\"][/quote]</b> osa. See v�imaldab teil viidata m�nele inimesele v�i millele iganes soovite! N�iteks, et viidata tekstile, mida h�rra Tamm kirjutas, siis sisestage:<br /><br /><b>[quote=\"h�rra Tamm\"]</b>h�rra Tamme tekst<b>[/quote]</b><br /><br />automaatselt kuvatakse - h�rra Tamm kirjutas: - enne tema tekstiks m�rgitud osa. Pidage meeles kaldkriipsud \"\" nime �mber, millele te viitate, <b>on</b> vajalikus.</li><li>Teine meetod v�imaldab teil viidata millelegi n� pimesi. Selleks pange tekst <b>[quote][/quote]</b> k�su vahele. Kui vaatate teadet on seal lihtsalt - Viide: ja tekstis viidatava tekst.</li></ul>");
$faq[] = array("Kindlate m��tmetega teksti kuvamine", "Kui peate kuvama koodi v�i midagi muud, mis n�uab kindlat omadust, n�iteks Courier t��pi fonti, peaksite ta panema <b>[code][/code]</b> k�su vahele, n�iteks<br /><br /><b>[code]</b>echo \"See on kood\";<b>[/code]</b><br /><br />K�ik vormistamine, mida kasutatakse<b>[code][/code]</b> taastatakse kui te seda hiljem vaatate.");

$faq[] = array("--", "�ldised nimekirjad");
$faq[] = array("Korrastamata nimekirja loomine", "BBCode v�imaldab kahte sorti nimekirju, korrastatud ja korrastamata. NAd on v�rdv��rsed HTML-i nimekirjadega. Korrastamata nimekiri paigutab nimekirja osad �ksteise alla, m�rgistades nende alguse vastava m�rgiga. Et luua korrastamata nimekirja kasutage <b>[list][/list]</b> k�sku ja ja m��rake iga nimekirja osa algus <b>[*]</b> k�suga. N�iteks oma lemmikv�rvide reastamiseks kasuta:<br /><br /><b>[list]</b><br /><b>[*]</b>Punane<br /><b>[*]</b>Sinine<br /><b>[*]</b>Kollane<br /><b>[/list]</b><br /><br />Tulemuseks oleks j�rgmine nimekiri:<ul><li>Punane</li><li>Sinine</li><li>Kollane</li></ul>");
$faq[] = array("Korrastatud nimekirja loomine", "Teine nimekirja t��p, korrastatud nimekiri, annab sulle v�imaluse kontrollida, mis pannakse nimekirjaosade ette. Korrastatud nimekirja loomiseks kasutage <b>[list=1][/list]</b> k�sku, et luua nummerdatud nimekiri v�i <b>[list=a][/list]</b> k�sku, et luua t�hestiku j�rjekorras olev nimekiri. Nagu ka korrastamata nimekirjas, eraldage osised <b>[*]</b> k�suga. N�iteks:<br /><br /><b>[list=1]</b><br /><b>[*]</b>Mine poodi<br /><b>[*]</b>Osta uus arvuti<br /><b>[*]</b>Vannu, kui arvuti lakkab t��tamast<br /><b>[/list]</b><br /><br />tulemuseks:<ol type=\"1\"><li>Mine poodi</li><li>Osta arvuti</li><li>Vannu, kui arvuti lakkab t��tamast</li></ol>T�hestiku j�rjekorras oleva nimekirja loomiseks kirjuta:<br /><br /><b>[list=a]</b><br /><b>[*]</b>Esimene<br /><b>[*]</b>Teine<br /><b>[*]</b>Kolmas<br /><b>[/list]</b><br /><br />tulemus<ol type=\"a\"><li>Esimene</li><li>Teine</li><li>Kolmas</li></ol>");

$faq[] = array("--", "Linkide loomine");
$faq[] = array("Teisele lehek�ljele linkimine", "PhpBB BBCode toetab erinevaid viise, kuidas luua URI-sid (Uniform Resource Indicators, paremini tuntud kui URL-id).<ul><li>Esimene v�imalus on kasutada <b>[url=][/url]</b> k�sku, k�ik mis j��b p�rast = kuvatakse kui URL-i. N�iteks lingi phpBB.com-i v�ib teha nii:<br /><br /><b>[url=http://www.phpbb.com/]</b>K�lasta phpBB-d!<b>[/url]</b><br /><br />Tulemuseks oleks j�rgmine link, <a href=\"http://www.phpbb.com/\" target=\"_blank\">K�lastage phpBB-d!</a> Link avaneb uues aknas, nii et kasutaja saab j�tkata foorumi uurimist.</li><li>Kui soovite, et URL ise oleks n�ha kui link, siis tehke nii:<br /><br /><b>[url]</b>http://www.phpbb.com/<b>[/url]</b><br /><br />Tulemuseks oleks j�rgmine link, <a href=\"http://www.phpbb.com/\" target=\"_blank\">http://www.phpbb.com/</a></li><li>Lisaks on phpBB-s v�imalus, mida kutsutakse <i>Maagiliseks lingiks</i>, see muudab iga korrektsel kujul oleva URL-i lingiks, ilma et te peaksite mingeid k�ske kasutama v�i isegi 'http://' algusse lisama. N�iteks kirjutades www.phpbb.com oma teatesse on automaatselt tulemuseks <a href=\"http://www.phpbb.com/\" target=\"_blank\">www.phpbb.com</a> kui teie teated vaadatakse.</li><li>Sama kehtib ka e-posti aadressite kohta, te v�ite kas e-posti aadressi t�pselt m��ramata :<br /><br /><b>[email]</b>no.one@domain.adr<b>[/email]</b><br /><br />mille tulemuseks oleks <a href=\"emailto:no.one@domain.adr\">no.one@domain.adr</a> v�i te v�ite lihtsalt kirjutada no.one@domain.adr oma teatesse ja see muudetakse teate vaatamise korral automaatselt lingiks.</li></ul>Nagu ka teiste BBCode-i k�skudega saab ka URL-e siduda teiste k�skudega, n�iteks:<b>[img][/img]</b> (vaata j�rgmist), <b>[b][/b]</b>, jne. Vormingu k�skude puhul on teie �lesandeks p��rata t�helepanu sellele, et k�skudel oleks kah l�pp, n�iteks:<br /><br /><b>[url=http://www.phpbb.com/][img]</b>http://www.phpbb.com/images/phplogo.gif<b>[/url][/img]</b><br /><br /><u>pole</u> �ige ja v�ib viia teie teate kustutamiseni.");

$faq[] = array("--", "Piltide kuvamine teates");
$faq[] = array("Pildi lisamine teatele", "PhpBB BBCode sisaldab k�sku millega lisada teatele pilte. Selle k�su puhul tuleb t�helepanu p��rata kahele v�ga t�htsale asjaolule; paljud kasutajad ei pea piltiderohkeid teateid maitsekateks ja kuvatava pilt peab eelnevalt olemas internetis k�ttesaadav (pole kasu kui pilt on olemas teie arvutis, v�lja arvatud juhul kui teie arvuti on veebiserver!). PhpBB ei suuda praegu pilte talletada (PhpBB j�rgmises versioonis on nee v�imalused loodetavasti olemas). Et pilti kuvada pead te pildile viitava URL-i �mbritsema k�suga <b>[img][/img]</b>. N�iteks:<br /><br /><b>[img]</b>http://www.phpbb.com/images/phplogo.gif<b>[/img]</b><br /><br />Nagu URL-i osast teada saate te siduda pildi <b>[url][/url]</b> k�suga, n�iteks <br /><br /><b>[url=http://www.phpbb.com/][img]</b>http://www.phpbb.com/images/phplogo.gif<b>[/img][/url]</b><br /><br />Tulemus:<br /><br /><a href=\"http://www.phpbb.com/\" target=\"_blank\"><img src=\"http://www.phpbb.com/images/phplogo.gif\" border=\"0\" alt=\"\" /></a><br />");

$faq[] = array("--", "Muud teemad");
$faq[] = array("Kas ma saan luua omi k�ske?", "Ei, phpBB 2.0 ei v�imalda seda. Kohandatavad BBCode k�sud on olemas j�rgmises suuremas foorumi versioonis");

//
// This ends the BBCode guide entries
//
