<?php
/***************************************************************************
 *                            lang_admin.php [Estonian]
 *                              -------------------
 *     begin                : Sat Dec 16 2000
 *     copyright            : (C) 2001 The phpBB Group
 *     email                : support@phpbb.com
 *
 *     $Id: lang_admin.php,v 1.35.2.3 2002/06/27 20:06:44 thefinn Exp $
 *
 ****************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

//
// Format is same as lang_main
//

//
// Modules, this replaces the keys used
// in the modules[][] arrays in each module file
//
$lang['General'] = '�ldine Adminn';
$lang['Users'] = 'Kasutaja Adminn';
$lang['Groups'] = 'Grupide Adminn';
$lang['Forums'] = 'Foorumi Adminn';
$lang['Styles'] = 'Stiilide Adminn';

$lang['Configuration'] = 'Konfiguratsioon';
$lang['Permissions'] = '�igused';
$lang['Manage'] = 'Juhtimine';
$lang['Disallow'] = 'Keelanimesi';
$lang['Prune'] = 'Puhastamine';
$lang['Mass_Email'] = 'Mass email';
$lang['Ranks'] = 'Tasemed';
$lang['Smilies'] = 'Emotsioonid';
$lang['Ban_Management'] = 'Bannimine';
$lang['Word_Censor'] = 'Roppused';
$lang['Export'] = 'Eksport';
$lang['Create_new'] = 'Loo uus';
$lang['Add_new'] = 'Lisa';
$lang['Backup_DB'] = 'Andmebaasi Varukoopia';
$lang['Restore_DB'] = 'Taasta Andmebaas';

//
// Index
//
$lang['Admin'] = 'Administratsioon';
$lang['Not_admin'] = 'Sul ei ole �igust seda foorumit administreerida';
$lang['Welcome_phpBB'] = 'Teretulemast!';
$lang['Admin_intro'] = 'See leht annab sulle kiire �levaate oma lehe statistikast. K�iki v�imalused leiad sa vasakust men��st.';
$lang['Main_index'] = 'Foorumi pealeht';
$lang['Forum_stats'] = 'Foorumi Statistika';
$lang['Admin_Index'] = 'Adminni pealeht';
$lang['Preview_forum'] = 'Foorumi eelvaade';

$lang['Click_return_admin_index'] = 'Vajuta %ssiia%s, et naasta adminni pealehele';

$lang['Statistic'] = 'Statistika';
$lang['Value'] = 'V��rtus';
$lang['Number_posts'] = 'Postitusi kokku';
$lang['Posts_per_day'] = 'Postitusi p�evas';
$lang['Number_topics'] = 'Teemasi kokku';
$lang['Topics_per_day'] = 'Teemasi p�evas';
$lang['Number_users'] = 'Kasutajaid kokku';
$lang['Users_per_day'] = 'Kasutajaid p�evas';
$lang['Board_started'] = 'Foorum avati';
$lang['Avatar_dir_size'] = 'Avataride m�lumaht';
$lang['Database_size'] = 'Andmebaasi suurus';
$lang['Gzip_compression'] = 'Gzip kompresioon';
$lang['Not_available'] = 'Pole saadaval';

$lang['ON'] = 'SEES'; // This is for GZip compression
$lang['OFF'] = 'V�LJAS';

//
// DB Utils
//
$lang['Database_Utilities'] = 'Andmebaasi v�imalused';

$lang['Restore'] = 'Taasta';
$lang['Backup'] = 'Varukoopia';
$lang['Restore_explain'] = 'See taastab t�ielikult foorumi seisu varukoopia ajal. See on suur operatsioon ja palun �rke lahkuge siit lehelt enne kui see on tegevuse l�petanud!';
$lang['Backup_explain'] = 'Siin sa saad teha varukoopia k�igele phpBB datale. Kui su server seda toetab v�id nad ka pakkida Gzip formaati enne downloadi.';

$lang['Backup_options'] = 'Varukoopia v�imalused';
$lang['Start_backup'] = 'Alusta varukoopia tegemist';
$lang['Full_backup'] = 'T�ielik varukoopia';
$lang['Structure_backup'] = 'Ainult struktuuri varukoopia';
$lang['Data_backup'] = 'Ainult andmete(postitused, teemad, kasutajad) varukoopia';
$lang['Additional_tables'] = 'Lisa tabelid';
$lang['Gzip_compress'] = 'Paki failid Gzip formaati';
$lang['Select_file'] = 'Vali fail';
$lang['Start_Restore'] = 'Alusta Taastamist';

$lang['Restore_success'] = 'Andmebaas on edukalt taastatud. Sinu andmebaas peaks olema samas seisus nagu ta oli su viimase Varukoopia ajal.';
$lang['Backup_download'] = 'Su allalaadimine algab peatselt, palun oota.';
$lang['Backups_not_supported'] = 'Sinu andmebaas ei toeta Varukoopiate tegemist. Vabandame!';

$lang['Restore_Error_uploading'] = 'Tekkis viga faili �leslaadimisel.';
$lang['Restore_Error_filename'] = 'Faili nimega on probleem, palun proovi muud faili v�i nime.';
$lang['Restore_Error_decompress'] = 'Ei saa lahti pakkida Gzip formaati, palun lae �lesse puhas tekst fail-';
$lang['Restore_Error_no_file'] = '�htegi faili ei laetud �les!';

//
// Auth pages
//
$lang['Select_a_User'] = 'Vali kasutaja';
$lang['Select_a_Group'] = 'Valia grupp';
$lang['Select_a_Forum'] = 'Vali foorum';
$lang['Auth_Control_User'] = 'Kasutajate �igused';
$lang['Auth_Control_Group'] = 'Grupide �igused';
$lang['Auth_Control_Forum'] = 'Foorumite �igused';
$lang['Look_up_User'] = 'Otsi kasutaja';
$lang['Look_up_Group'] = 'Otsi grupp';
$lang['Look_up_Forum'] = 'Otsi foorum';

$lang['Group_auth_explain'] = 'Siin sa saad muuta �igusi ja moderaatori staatust tervele kasutajate gruppile. NB! Kui kasutajal on eraldi antud �igus midagi modereerida vms. , siis need �igused j��vad talle endiselt alles. Sind hoiatatakse kui selline juhus tekib.';
$lang['User_auth_explain'] = 'Siin sa saad muuta �igusi ja moderaatori staatust kindlale kasutajale. NB! Kui kasutaja kuulub gruppi, siis selle grupi �igusega v�ib ta endiselt kirjutada foorumitesse, kustutada teemasi jne. Kui selline juhus tekib siis seda �eldakse.';
$lang['Forum_auth_explain'] = 'Siin sa saad muuta foorumi seadeid. ET mis tasemega kasutajad saavad seda vaadata, modereerida jne.';

$lang['Simple_mode'] = 'Tavaline Mode';
$lang['Advanced_mode'] = 'T�psem Mode';
$lang['Moderator_status'] = 'Moderaatori staatus';

$lang['Allowed_Access'] = 'Lubatud juurdep��s';
$lang['Disallowed_Access'] = 'Keelatud juurdep��s';
$lang['Is_Moderator'] = 'On Moderaator';
$lang['Not_Moderator'] = 'Ei ole Moteraator';

$lang['Conflict_warning'] = 'Administreerimis konflikt! HOIATUS!';
$lang['Conflict_access_userauth'] = 'Sellel kasutajal on endiselt juurdep��s sellele foorumile oma kasutajte gruppi kaudu. Sa v�ib-olla tahad muuta grupi �igusi v�i eemaldada see kasutaja sealt grupist. Grupid, mis annavad �igusi(ja foorumid mida nad m�jutavad) on allpool �ra toodud.';
$lang['Conflict_mod_userauth'] = 'Sellel kasutajal on endiselt moderaatori �igused sellele foorumile oma kasutajate grupi kaudu. Sa v�ibolla tahad muuta grupi �igusi v�i eemaldada see kasutaja sealt grupist. Grupid, mis annavad �igusi (ja foorumid mida nad m�jutavad) on allpool �ra toodud.';

$lang['Conflict_access_groupauth'] = 'J�rgmistel kasutajatel on endiselt �igused nende kasutajte grupi kaudu. Sa v�ibolla tahad neid muuta. K�ik on �ra toodud all nimekirjas.';
$lang['Conflict_mod_groupauth'] = 'Antud kasutajatel on endiselt modereerimis �igused osades foorumites. K�ik vajalik on �ra toodud all nimekirjas';

$lang['Public'] = 'Avalik';
$lang['Private'] = 'Isiklik';
$lang['Registered'] = 'Registreerunutele';
$lang['Administrators'] = 'Administraatoritele';
$lang['Hidden'] = 'Peidetud';

// These are displayed in the drop down boxes for advanced
// mode forum auth, try and keep them short!
$lang['Forum_ALL'] = 'K�IK';
$lang['Forum_REG'] = 'REG';
$lang['Forum_PRIVATE'] = 'PRIV';
$lang['Forum_MOD'] = 'MOD';
$lang['Forum_ADMIN'] = 'ADMIN';

$lang['View'] = 'Vaata';
$lang['Read'] = 'Loe';
$lang['Post'] = 'Postita';
$lang['Reply'] = 'Vasta';
$lang['Edit'] = 'Muuda';
$lang['Delete'] = 'Kustuta';
$lang['Sticky'] = 'Kleeps';
$lang['Announce'] = 'Teavita';
$lang['Vote'] = 'H��leta';
$lang['Pollcreate'] = 'Loo h��letus';

$lang['Permissions'] = '�igused';
$lang['Simple_Permission'] = 'Liht�igused';

$lang['User_Level'] = 'Kasutaja level';
$lang['Auth_User'] = 'Kasutaja';
$lang['Auth_Admin'] = 'Administraator';
$lang['Group_memberships'] = 'Katuajate grupi liikmed';
$lang['Usergroup_members'] = 'Sellel grupil on j�rgmised liikmed';

$lang['Forum_auth_updated'] = 'Foorumi �igused muudetud';
$lang['User_auth_updated'] = 'Kasutaja �igused muudetud';
$lang['Group_auth_updated'] = 'Grupi �igused muudetud';

$lang['Auth_updated'] = '�igused on muudetud';
$lang['Click_return_userauth'] = 'Vajuta %ssiia%s et minna tagasi kasutajate �iguste lehele.';
$lang['Click_return_groupauth'] = 'Vajuta %ssiia%s et minna tagasi grupi �iguste lehele.';
$lang['Click_return_forumauth'] = 'Vajuta %ssiia%s et minna tagasi foorumi �iguste lehele.';

//
// Banning
//
$lang['Ban_control'] = 'Bannide juhtimine';
$lang['Ban_explain'] = 'Siin sa saad juhtida kasutajate bannimist. Sa saad bannida kas kasutajanime, ip aadressi jne. Niimoodi keelad tal isegi j�udmise su foorumi avalehele. Et �ra hoida �he kasutaja topelt registreerimist v�id sa ka bannida tema emaili!';
$lang['Ban_explain_warn'] = 'NB! Kui sa bannid mingi ip vahemiku siis baasi lisatakse k�ik IP aadressid eraldi. Palun �rita seda listi hoida v�iksena, veel parem lisa ainult �ks ip aadress.';

$lang['Select_username'] = 'Vali kasutajanimi';
$lang['Select_ip'] = 'Vali ip IP';
$lang['Select_email'] = 'Vali Emaili aadress';

$lang['Ban_username'] = 'Banni �ks v�i rohkem kasutajanimesi';
$lang['Ban_username_explain'] = 'Sa v�id ka korraga bannide mitut kasutajat';

$lang['Ban_IP'] = 'Banni �ks v�i rohkem ip aadressi v�i teenusepakkuja';
$lang['IP_hostname'] = 'IP addressid v�i teenusepakkuja nimed';
$lang['Ban_IP_explain'] = 'Et �ra m�rkida mitmeid ip aadressid v�i teenusepakkujad, eralda need komaga.';

$lang['Ban_email'] = 'Banni �ks v�i mitu e-maili aadressi';
$lang['Ban_email_explain'] = 'Et bannida mitmeid emaile, eralda need komadega.';

$lang['Unban_username'] = 'Eemalda bann �helt v�i mitmelt kasutajalt';
$lang['Unban_username_explain'] = 'Sa v�id eemaldada ka banni mitmelt korraga hoides all ctrl.';

$lang['Unban_IP'] = 'Eemalda bann �helt v�i mitmelt ip aadressilt';
$lang['Unban_IP_explain'] = 'Sa v�id eemaldada ka banni mitmelt korraga hoides all ctrl.';

$lang['Unban_email'] = 'Eemalda bann �helt v�i mitmelt emaili aadresssilt';
$lang['Unban_email_explain'] = 'Sa v�id eemaldada ka banni mitmelt korraga hoides all ctrl.';

$lang['No_banned_users'] = 'Ei ole �htegi bannitud kasutajat';
$lang['No_banned_ip'] = 'Ei ole �htegi bannitud ip-d';
$lang['No_banned_email'] = 'Ei ole �htegi bannitud e-maili';

$lang['Ban_update_sucessful'] = 'Bannitute listi uuendati edukalt!';
$lang['Click_return_banadmin'] = 'Vajuta %ssiia%s et minna tagasi bannide juhtimisele';

//
// Configuration
//
$lang['General_Config'] = '�ldine konfiguratsioon';
$lang['Config_explain'] = 'Alumine form laseb sul muuta �ldist seadistust. Muude asjade muutmiseks kasuta vasakul olevat men��d.';

$lang['Click_return_config'] = 'Vajuta %ssiia%s et minna tagasi �ldiste Seadistuste lehele';

$lang['General_settings'] = '�ldised forumi seadistused';
$lang['Server_name'] = 'Domeeni nimi';
$lang['Server_name_explain'] = 'Domeen, kus see foorum asetseb';
$lang['Script_path'] = 'Foorumi kaust';
$lang['Script_path_explain'] = 'Kaust kus foorum asub domeeni suhtes.';
$lang['Server_port'] = 'Serveri Port';
$lang['Server_port_explain'] = 'Port, kus su server jookseb. Enamasti 80, muuda ainult siis, kui sa tead, et see ei ole nii.';
$lang['Site_name'] = 'Lehek�lje nimi';
$lang['Site_desc'] = 'Lehek�lje kirjeldus';
$lang['Board_disable'] = 'Sulge foorum';
$lang['Board_disable_explain'] = 'See teeb foorumi kasutajatele kinniseks. NB! �ra v�lja logi, sa ei saa enam sisse logida!';
$lang['Acct_activation'] = 'Ava kontode aktivatsioon';
$lang['Acc_None'] = 'puudub'; // These three entries are the type of activation
$lang['Acc_User'] = 'kasutaja';
$lang['Acc_Admin'] = 'admin';

$lang['Abilities_settings'] = 'Kasutajate ja foorumi p�hilised seaded';
$lang['Max_poll_options'] = 'Maksimum arv k�sitluse vastuseid';
$lang['Flood_Interval'] = 'Postituste interval';
$lang['Flood_Interval_explain'] = 'Sekundites, kui kaua peab kasutaja ootama, et saaks teha uue postituse';
$lang['Board_email_form'] = 'Kasutajate emailimine foorumi kaudu';
$lang['Board_email_form_explain'] = 'kasutajad v�ivad saata emaile selle foorumi kaudu';
$lang['Topics_per_page'] = 'Teemasi lehe peale';
$lang['Posts_per_page'] = 'Postitusi lehe peale';
$lang['Hot_threshold'] = 'Postitusi, et teema muutuks populaarseks';
$lang['Default_style'] = 'Vaikimisi Stiil';
$lang['Override_style'] = 'Kasutaja stiilist �le kirjutamine';
$lang['Override_style_explain'] = 'Asenda kasutaja valitud stiil igal juhul vaikimisi stiiliga.';
$lang['Default_language'] = 'Vaikimisi keel';
$lang['Date_format'] = 'Kuup�eva formaat';
$lang['System_timezone'] = 'S�steemi ajatsoon';
$lang['Enable_gzip'] = 'Luba GZip pakkimine';
$lang['Enable_prune'] = 'V�imalda foorumi auto-puhastamine';
$lang['Allow_HTML'] = 'V�imalda HTML';
$lang['Allow_BBCode'] = 'V�imalda BBKood';
$lang['Allowed_tags'] = 'V�imalda HTML tagid';
$lang['Allowed_tags_explain'] = 'Eralda tagid komadega';
$lang['Allow_smilies'] = 'V�imalda emotsioonid';
$lang['Smilies_path'] = 'Emotsioonide kaust';
$lang['Smilies_path_explain'] = 'Asukoht foorumi kausta suhtes. N�ide: images/smiles';
$lang['Allow_sig'] = 'V�imalda allkirjastamine';
$lang['Max_sig_length'] = 'Max. allkirja pikkus';
$lang['Max_sig_length_explain'] = 'Max. arv t�hti kasutaja allkirjas';
$lang['Allow_name_change'] = 'V�imalda kasutajanime muutmist';
$lang['Avatar_settings'] = 'Avatari seadistused';
$lang['Allow_local'] = 'V�imalda galerii avatarid';
$lang['Allow_remote'] = 'V�imalda kaug-avatarid';
$lang['Allow_remote_explain'] = 'Avatarist link teisel lehel. n�ide www.hot.ee/gretler/avatar.jpg';
$lang['Allow_upload'] = 'V�imalda avataride �leslaadimine';
$lang['Max_filesize'] = 'Max. avatari suurus';
$lang['Max_filesize_explain'] = 'Avataride �leslaadimiseks.';
$lang['Max_avatar_size'] = 'Max. avatari dimensioon';
$lang['Max_avatar_size_explain'] = '(K�rgus ja laius pixlites)';
$lang['Avatar_storage_path'] = 'Avataride kaust';
$lang['Avatar_storage_path_explain'] = 'Avataride kaust, n�ide: images/avatars';
$lang['Avatar_gallery_path'] = 'Avataride galerii kaust';
$lang['Avatar_gallery_path_explain'] = 'galerii kaust, n�ide images/avatars/gallery';
$lang['COPPA_settings'] = 'COPPA seaded';
$lang['COPPA_fax'] = 'COPPA Faksi number';
$lang['COPPA_mail'] = 'COPPA E-maili Address';
$lang['COPPA_mail_explain'] = 'See on aadress, kuhu vanemad peaksid saatma t�idetud formi, aga siin foorumis on see maha v�etud';
$lang['Email_settings'] = 'E-maili seaded';
$lang['Admin_email'] = 'Adminni email';
$lang['Email_sig'] = 'E-maili allkiri';
$lang['Email_sig_explain'] = 'See tekst lisatakse k�igile v�ljaminevatele emaili aadressidele';
$lang['Use_SMTP'] = 'Kasuta SMTP e-maili';
$lang['Use_SMTP_explain'] = 'Vali see, kui sa tahad v�i pead kasutama serverit mailide v�lja saatmiseks mail() funktsiooni asemel.';
$lang['SMTP_server'] = 'SMTP Serveri aadress';
$lang['SMTP_username'] = 'SMTP kasutaja';
$lang['SMTP_username_explain'] = 'Ainult siis sisesta kasutaja nimi, kui su server seda n�uab';
$lang['SMTP_password'] = 'SMTP parool';
$lang['SMTP_password_explain'] = 'Ainult siis sisesta parool kui su server seda n�uab';
$lang['Disable_privmsg'] = 'Privaat s�numid';
$lang['Inbox_limits'] = 'Max postitusi INBOXis';
$lang['Sentbox_limits'] = 'Max postitusi SENTBOXis';
$lang['Savebox_limits'] = 'Max postiusi SAVABOXis';
$lang['Cookie_settings'] = 'K�psiste seaded';
$lang['Cookie_settings_explain'] = 'Need detailid kirjeldavad, kuidas k�psised saavad olema saadetud su kasutajate brauseritesse. Need peaksid olema hetkel �iged n�itajad. kui on vajalik, siis muuda neid, aga ettevaatlikult. Ebakorrektne muutmine v�ib p�hjustada selle, et kasutajad ei saa enam sisse logida.';
$lang['Cookie_domain'] = 'K�psiste domeen';
$lang['Cookie_name'] = 'K�psise nimi';
$lang['Cookie_path'] = 'K�psise tee';
$lang['Cookie_secure'] = 'K�psiste turvalisus';
$lang['Cookie_secure_explain'] = 'Kui su server jookseb SSL kaudu, siis vali see valik, muul juhul j�ta valimata.';
$lang['Session_length'] = 'Sessiooni pikkus [ sekundites ]';

//
// Forum Management
//

$lang['Forum_admin'] = 'Foorumi Seaded';
$lang['Forum_admin_explain'] = 'Selle paneeli kaudu saad luua, kustutada, muuta jpm. foorumites';
$lang['Edit_forum'] = 'Muuda Foorumit';
$lang['Create_forum'] = 'Tee uus foorum';
$lang['Create_category'] = 'Tee uus kategooria';
$lang['Remove'] = 'Eemalda';
$lang['Action'] = 'Olek';
$lang['Update_order'] = 'Uuenda';
$lang['Config_updated'] = 'Foorumi seadistused on edukalt uuendatud';
$lang['Edit'] = 'Muuda';
$lang['Delete'] = 'Kustuta';
$lang['Move_up'] = 'liiguta �lespoole';
$lang['Move_down'] = 'liiguta allapoole';
$lang['Resync'] = 'Resync';
$lang['No_mode'] = '�htegi MODE�t ei muudetud';
$lang['Forum_edit_delete_explain'] = 'All olev vorm lubab sul muuta p�hilisi n�itajaid. Muude asjade muutmiseks kasuta vasakul olevat men��d.';
$lang['Move_contents'] = 'liiguta kogu sisu';
$lang['Forum_delete'] = 'Kustuta foorum';
$lang['Forum_delete_explain'] = 'All olev vorm lubab sul foorumi kustutada ja otsustada kuhu sa tahad kogu selle sisu panna';
$lang['Status_locked'] = 'Lukus';
$lang['Status_unlocked'] = 'Avatud';
$lang['Forum_settings'] = '�ldised foorumi seadistused';
$lang['Forum_name'] = 'Foorumi nimi';
$lang['Forum_desc'] = 'Kirjeldus';
$lang['Forum_status'] = 'Foorumi staatus';
$lang['Forum_pruning'] = 'Auto-puhastus';
$lang['prune_freq'] = 'Kontrolli teema vanust iga';
$lang['prune_days'] = 'Eemalda teemad, kuhu pole tehtud uusi postitusi';
$lang['Set_prune_data'] = 'Sa oled auto puhastuse k�ll sisse l�litanud, aga sa ei m�rkinud, kui tihti ja milliste parameetrite j�rgi seda teha tuleb. Palun mine tagasi ja paranda viga';
$lang['Move_and_Delete'] = 'Liiguta ja kustuta';
$lang['Delete_all_posts'] = 'Kustuta k�ik postitused';
$lang['Nowhere_to_move'] = 'pole kuhugi liigutada';
$lang['Edit_Category'] = 'Muuda kategoorja';
$lang['Edit_Category_explain'] = 'Kasuta seda vormi et muuta kategrooja nime';
$lang['Forums_updated'] = 'Foorumi ja kategooria uuendati edukalt.';
$lang['Must_delete_forums'] = 'Sa pead enne kustutama k�ik foorumid, kui saad kustutada seda kategooriat';
$lang['Click_return_forumadmin'] = 'Vajuta %ssiia%s et minna tagasi foorumi seadistuste lehele';

//
// Smiley Management
//
$lang['smiley_title'] = 'Emtsioonide seaded';
$lang['smile_desc'] = 'Siin lehel sa saad seadistada emotsioone mida kasutajad saavad kasutada oma postitustes ja privaat teadetes';
$lang['smiley_config'] = 'Emotsiooni seaded';
$lang['smiley_code'] = 'Emotsiooni kood';
$lang['smiley_url'] = 'Emotsiooni fail';
$lang['smiley_emot'] = 'Emotsiooni nimi';
$lang['smile_add'] = 'Lisa uus emotsioon';
$lang['Smile'] = 'Emotsioon';
$lang['Emotion'] = 'Smaili';
$lang['Select_pak'] = 'Vali PACK(.pak) fail';
$lang['replace_existing'] = 'Asenda hetkel olev emotsioon';
$lang['keep_existing'] = 'J�ta hetkel olemas oleva emot. alles';
$lang['smiley_import_inst'] = 'Sa peaksid unzippima oma smiley paki ja �les laadima k�ik failid �igesse Smiley kataloogi installimiseks. Siis valmia korrektse informatsiooni siin vormis et importida smily pack.';
$lang['smiley_import'] = 'Emotsiooni paki Import';
$lang['choose_smile_pak'] = 'Vali Emot. paki .pak fail';
$lang['import'] = 'Impordi emotsioonid';
$lang['smile_conflicts'] = 'Mida tuleks teha, kui tekivad vastuolud.';
$lang['del_existing_smileys'] = 'kustuta olemas olevad emot. enne importi';
$lang['import_smile_pack'] = 'Impordi emot. pakk.';
$lang['export_smile_pack'] = 'Tee uus emot pakk';
$lang['export_smiles'] = 'Et teha emot. pakk hetkel instaleeritud emotsioonidest klikki %ssiin%s et allalaadida smiles.pak fail. Nimeta see fail kohaselt olles kindel et failile j��b .pak laiend. Siis tee uus zip fail, mis sisaldab k�iki neid emotsioone plus siis see .pak fail.';
$lang['smiley_add_success'] = 'Emotsioonid lisati edukalt.';
$lang['smiley_edit_success'] = 'Emotsioonid uuendati edukalt.';
$lang['smiley_import_success'] = 'Emot. pakk importiti edukalt.';
$lang['smiley_del_success'] = 'Emotsioon kustutati edukalt.';
$lang['Click_return_smileadmin'] = 'Vajuta %ssiia%s, et minna tagasi emotsioonide juhtimis lehele.';

//
// User Management
//
$lang['User_admin'] = 'Kasutaja juhtimine';
$lang['User_admin_explain'] = 'Siin sa saad muuta kasutaja informatsiooni ja osasi spetsiifilisi v�imalusi. Et muuta kasutaja �igusi kasuta Grupide juhtimist ja Kasutajate �iguste paneele.';
$lang['Look_up_user'] = 'Otsi kasutaja �les';
$lang['Admin_user_fail'] = 'Kasutaja profiili muutmine eba�nnestus!';
$lang['Admin_user_updated'] = 'Kasutaja profiil uuendati edukalt!';
$lang['Click_return_useradmin'] = 'Vajuta %ssiia%s, et minna tagasi kasutajate juhtimis lehele.';
$lang['User_delete'] = 'Kustuta see kasutaja';
$lang['User_delete_explain'] = 'Vajuta siia, et kustuta see kasutaja, seda ei saa tagasi muuta.';
$lang['User_deleted'] = 'Kasutaja kustutati edukalt.';
$lang['User_status'] = 'Kasutaja on aktiivne';
$lang['User_allowpm'] = 'Saab saata privaat s�numeid';
$lang['User_allowavatar'] = 'V�ib n�idata avatare';
$lang['Admin_avatar_explain'] = 'Siin sa saad n�ha ja kustutada kasutaja avatari.';
$lang['User_special'] = '"ainult adminnile" v�ljad';
$lang['User_special_explain'] = 'Neid v�lju ei saa kasutajad ise muuta';

//
// Group Management
//
$lang['Group_administration'] = 'Grupide juhtimine';
$lang['Group_admin_explain'] = 'Siin paneelil sa saad juhtida kasutajategruppe, sa saad neid kustutada, luua ja muuta. Sa v�id neid avada ja sulgeda ja palju muud.';
$lang['Error_updating_groups'] = 'Grupide muutmisel tekkis viga.';
$lang['Updated_group'] = 'Grupp uuendati edukalt';
$lang['Added_new_group'] = 'Uus grupp loodi edukalt.';
$lang['Deleted_group'] = 'Grupp kustutai edukalt';
$lang['New_group'] = 'Tee uus grupp.';
$lang['Edit_group'] = 'Muuda grupp';
$lang['group_name'] = 'Grupi nimi';
$lang['group_description'] = 'Grupi kirjeldus';
$lang['group_moderator'] = 'Grupi moderaator';
$lang['group_status'] = 'Grupi staatus';
$lang['group_open'] = 'Ava grupp';
$lang['group_closed'] = 'Suletud grupp';
$lang['group_hidden'] = 'Peidetud grupp';
$lang['group_delete'] = 'Kustuta grupp';
$lang['group_delete_check'] = 'Kustuta see grupp';
$lang['submit_group_changes'] = 'Uuenda';
$lang['reset_group_changes'] = 'Taasta';
$lang['No_group_name'] = 'Sa pead �ra m�rkima sellele grupile ka nime.';
$lang['No_group_moderator'] = 'Sa pead lisama sellele grupile moderaatori';
$lang['No_group_mode'] = 'Sa pead m�rkima sellele grupile oleku, avatud/suletud';
$lang['No_group_action'] = 'K�sklust ei ole antud';
$lang['delete_group_moderator'] = 'Kustuta grupi vana moderaator?';
$lang['delete_moderator_explain'] = 'Kui sa muudad selle grupi moderaatorit siis tee siia kasti linnuke. Muul juhul �ra tee midagi ja sellest liikmest saab selle grupi tavaliige.';
$lang['Click_return_groupsadmin'] = 'Vajuta %ssiia%s, et minna tagasi grupi juhtimis lehele';
$lang['Select_group'] = 'Vali grupp';
$lang['Look_up_group'] = 'Otsi grupp �les';

//
// Prune Administration
//
$lang['Forum_Prune'] = 'Foorumi puhastus';
$lang['Forum_Prune_explain'] = 'See kustutab teemad, milledesse pole sinu poolt m��ratud p�evade jooksul uusi postitusi tehtud. Kui sa ei sisesta p�evade arvu, kustutatakse k�ik teemad. See ei eemalda teemasid, kus on aktiivne k�sitlus, samuti ei kustuta see teadaandeid. Need teemad tuleb k�sitsi kustutada.';
$lang['Do_Prune'] = 'Puhasta';
$lang['All_Forums'] = 'K�ik foorumid';
$lang['Prune_topics_not_posted'] = 'Puhasta teemad, kuhu pole vastatud viimasel ... p�eval';
$lang['Topics_pruned'] = 'Teemad puhastatud';
$lang['Posts_pruned'] = 'Postitused puhastatud';
$lang['Prune_success'] = 'K�ik foorumid puhastati edukalt.';

//
// Word censor
//
$lang['Words_title'] = 'Tsensuur';
$lang['Words_explain'] = 'Siit paneelist saad sa lisada, muuta, ja eemaldada s�nu, mis tsenseeritakse foorumis automaatselt. Lisaks ei saa kasutajad registreerida kasutajanime, mis sisaldab neid s�nu. T�rnid (*) on lubatud s�nav�ljal, nt *pass* tsenseerib ka s�na "hundipassikontroll", pass* tsenseeriks ka s�na "passikontroll", *pass tsenseeriks s�na "hundipass".';
$lang['Word'] = 'S�na';
$lang['Edit_word_censor'] = 'Muuda tsensuuri';
$lang['Replacement'] = 'Asendus';
$lang['Add_new_word'] = 'Lisa uus s�na';
$lang['Update_word'] = 'Uuenda tsensuuri';

$lang['Must_enter_word'] = 'Sa pead sisestama s�na ja selle asenduse';
$lang['No_word_selected'] = 'Muutmiseks pole s�na valitud';

$lang['Word_updated'] = 'Valitud tsensuur uuendatud';
$lang['Word_added'] = 'Tsenseeritav s�na lisatud';
$lang['Word_removed'] = 'Tsenseeritav s�na eemaldatud';

$lang['Click_return_wordadmin'] = 'Vajuta %ssiia%s et minna tagasi Tsensuuri algusesse';

//
// Mass E-mail
//
$lang['Mass_email_explain'] = 'Siit saad sa saata e-maili kas k�igile kasutajatele v�i mingi kindla grupi kasutajatele. Seda tehes saadetakse e-mail administraatori aadressile ja kirja pimekoopia k�igile kasutajatele. Kui sa saadad kirja suurele kasutajagrupile, siis ole kannatlik ja �ra katkesta laadimist poolepealt. Mass e-maili saadetaksegi kaua aega, sulle antakse m�rku, kui see valmis on';
$lang['Compose'] = 'Kirjuta';

$lang['Recipients'] = 'Saajad';
$lang['All_users'] = 'K�ik kasutajad';

$lang['Email_successfull'] = 'Sinu teade on saadetud';
$lang['Click_return_massemail'] = 'Vajuta %ssiia%s et minna Mass e-maili algusesse';

//
// Ranks admin
//
$lang['Ranks_title'] = 'Tasemete  Administratsioon';
$lang['Ranks_explain'] = 'Siin saad sa lisada, muuta, vaadata ja kustutada tasemeid. V�id luua ka eritasemed, mida saad kasutajale omistada kasutajate �iguste paneelis.';

$lang['Add_new_rank'] = 'Lisa uus tase';

$lang['Rank_title'] = 'Taseme pealkiri';
$lang['Rank_special'] = 'M��ra eritase';
$lang['Rank_minimum'] = 'Minimum Postitusi';
$lang['Rank_maximum'] = 'Maximum Postitusi';
$lang['Rank_image'] = 'Taseme pilt (Sama mis phpBB2 juurkaust)';
$lang['Rank_image_explain'] = 'Kasuta seda, et valida v�ike pilt, mis kaasneb selle tasemega.';

$lang['Must_select_rank'] = 'Sa pead valima taseme';
$lang['No_assigned_rank'] = 'Eritasemeid pole';

$lang['Rank_updated'] = 'Tase on edukalt uuendatud';
$lang['Rank_added'] = 'Tase on edukalt lisatud';
$lang['Rank_removed'] = 'Tase on edukalt kustutatud';
$lang['No_update_ranks'] = 'Tase kustutati edukalt, kuid ei uuendatud kasutajakontosid, kes on sellel tasemel. Nendel kasutajakontodel pead sa taseme k�sitsi muutma.';

$lang['Click_return_rankadmin'] = 'Vajuta %ssiia%s, et minna tagasi Tasemete Administratsiooni';

//
// Disallow Username Admin
//
$lang['Disallow_control'] = 'Kasutajanimede keelamine';
$lang['Disallow_explain'] = 'Siia saad sa lisada kasutajanimesid, mida ei lubata kasutada. Keelatud kasutajanimed v�ivad sisaldada t�rne (*). Sa ei saa keelata kasutajanime, mis on juba registreeritud, enne tuleb see nimi kustutada ja siis alles keelata.';

$lang['Delete_disallow'] = 'Kustuta';
$lang['Delete_disallow_title'] = 'Eemalda keelatud kasutajanimi';
$lang['Delete_disallow_explain'] = 'Sa saad keelatud kasutajanime eemaldada, valides selle nimekirjast ja vajutades submit nupule';

$lang['Add_disallow'] = 'Lisa';
$lang['Add_disallow_title'] = 'Lisa keelatud kasutajanimi';
$lang['Add_disallow_explain'] = 'Sa saad kasutajanime keelatamisel kasutada ka t�rni (*), mis vastab �ksk�ik mis t�hele.';

$lang['No_disallowed'] = 'Keelatud kasutajanimesid pole';

$lang['Disallowed_deleted'] = 'Keelatud kasutajanimi eemaldati.';
$lang['Disallow_successful'] = 'Keelatud kasutajanimi lisatud';
$lang['Disallowed_already'] = 'Sisestatud nime ei ole v�imalik keelata. See on kas juba keelatud, esineb tsenseeritud s�nades, v�i on olemas sellenimeline kasutaja';

$lang['Click_return_disallowadmin'] = 'Vajuta %ssiia%s et minna Kasutajanimede keelamise algusesse';

//
// Styles Admin
//
$lang['Styles_admin'] = 'Stiilide Administratsioon';
$lang['Styles_explain'] = 'Siin saad sa lisada, eemaldada ja muuta stiile (p�hjad ja teemad) mida saavad kasutajad valida';
$lang['Styles_addnew_explain'] = 'J�rgnev nimekiri sisaldab k�iki teemasid, mis on saadaval olemasolevatele p�hjadele. Siin nimekirjas olevaid asju pole veel phpBB andmebaasi installitud. Teema installimiseks kliki installeerimise lingil';

$lang['Select_template'] = 'Vali teema';

$lang['Style'] = 'Stiil';
$lang['Template'] = 'P�hi';
$lang['Install'] = 'Installeeri';
$lang['Download'] = 'Lae alla';

$lang['Edit_theme'] = 'Muuda teemat';
$lang['Edit_theme_explain'] = 'Allolevas vormis saab valitud teemade seadeid muuta.';

$lang['Create_theme'] = 'Loo teema';
$lang['Create_theme_explain'] = 'Kasuta allolevad vormi, et luua uus teema valitud teema. V�rve sisestades (milleks sa peaksid kasutama 16 s�steemi arve) ei tohi sa kasutada m�rki #, nt.. CCCCCC on lubatud, #CCCCCC aga mitte';

$lang['Export_themes'] = 'Ekspordi teema';
$lang['Export_explain'] = 'Siin saad sa eksportida teema andmed valitud p�hjale. Vali allolevast nimekirjast p�hi ja skript loob teema konfiguratsiooni faii ning p��ab seda salvestada valitud p�hjade kausta. Kui see ise ei saa faili salvestada, annab see sulle v�imaluse see alla laadida. Et skript saaks antud faili salvestada, pead sa andma kirjutamisloa veebiserveri p�hjade kaustale. Lisainfo jaoks vaata phpBB 2 kasutaja juhendit.';

$lang['Theme_installed'] = 'Valitud teema instlalitud.';
$lang['Style_removed'] = 'Valitud teema andmebaasist eemaldatud. Et seda stiili arvutist t�ielikult kustutada, pead sa kustutama p�hjade kaustas oleva stiilifaili.';
$lang['Theme_info_saved'] = 'Teema info valitud p�hjale on salvestatud. N��d sa peaksid muutma faili theme_info.cfg (v�imalusel ka p�hjade kausta) ainult loetavaks(read-only)';
$lang['Theme_updated'] = 'Valitud teema uuendatud. N��d tuleks eksportida uue teema seaded.';
$lang['Theme_created'] = 'Teema loodud. N��d peaksid sa selle teema eksportima teema konfiguratsioonifaili, et see turvaliselt s�ilitada v�i seda mujal kasutada.';

$lang['Confirm_delete_style'] = 'Oled sa kindel, et soovid antud stiili kustutada?';

$lang['Download_theme_cfg'] = 'Eksportija ei saanud kirutada teema infofaili. Vajuta allolevale nupule, et see fail oma brauseriga alla laadida. Kui fail on alla laetud, siis t�sta see kausta, kus asuvad p�hjad (templates). Siis v�id sa need failid kasutamiseks pakkida v�i mujal kasutada, kui sa soovid.';
$lang['No_themes'] = 'Valitud teemale pole �htegi teemat lisatud. Et luua uut teemat, kliki vasakul olevas paneelis linki Loo uus';
$lang['No_template_dir'] = 'Ei �nnestunud avad p�hjade kausta. See v�ib olla veebiserverile lugematu v�i pole seda olemas';
$lang['Cannot_remove_style'] = 'Sa ei saa valitud stiili eemalda, kuna see on praegu foorumi vaikimisi stiil. Palun  muuda vaikimisi stiili ja proovi uuuesti.';
$lang['Style_exists'] = 'Sellise nimega stiil on juba olemas. Mine tagasi ja vali m�ni muu nimi.';

$lang['Click_return_styleadmin'] = 'Vajuta %ssiia%s et minna tagasi Stiilide Administreerimise lehele';

$lang['Theme_settings'] = 'Teema seaded';
$lang['Theme_element'] = 'Teema Element';
$lang['Simple_name'] = 'Lihtne nimi';
$lang['Value'] = 'V��rtus';
$lang['Save_Settings'] = 'Salvesta seaded';

$lang['Stylesheet'] = 'CSS Stiilileht';
$lang['Background_image'] = 'Taustapilt';
$lang['Background_color'] = 'Taustav�rv';
$lang['Theme_name'] = 'Teema nimi';
$lang['Link_color'] = 'Lingi v�rv';
$lang['Text_color'] = 'Teksti v�rv';
$lang['VLink_color'] = 'K�lastatud lingi v�rv';
$lang['ALink_color'] = 'Aktiivse lingi v�rv';
$lang['HLink_color'] = 'Hover lingi v�rv';
$lang['Tr_color1'] = 'Tabeli rea v�rv 1';
$lang['Tr_color2'] = 'Tabeli rea v�rv 2';
$lang['Tr_color3'] = 'Tabeli rea v�rv 3';
$lang['Tr_class1'] = 'Tabeli rea klass 1';
$lang['Tr_class2'] = 'Tabeli rea klass 2';
$lang['Tr_class3'] = 'Tabeli rea klass 3';
$lang['Th_color1'] = 'Tabeli p�ise v�rv 1';
$lang['Th_color2'] = 'Tabeli p�ise v�rv 2';
$lang['Th_color3'] = 'Tabeli p�ise v�rv 3';
$lang['Th_class1'] = 'Tabeli p�ise klass 1';
$lang['Th_class2'] = 'Tabeli p�ise klass 2';
$lang['Th_class3'] = 'Tabeli p�ise klass 3';
$lang['Td_color1'] = 'Tablei lahtir(celli) v�rv 1';
$lang['Td_color2'] = 'Tablei lahtir(celli) v�rv 2';
$lang['Td_color3'] = 'Tablei lahtir(celli) v�rv 3';
$lang['Td_class1'] = 'Tablei lahtir(celli) klass 1';
$lang['Td_class2'] = 'Tablei lahtir(celli) klass 2';
$lang['Td_class3'] = 'Tablei lahtir(celli) klass 3';
$lang['fontface1'] = 'Kirja t��p 1';
$lang['fontface2'] = 'Kirja t��p 2';
$lang['fontface3'] = 'Kirja t��p 3';
$lang['fontsize1'] = 'Kirja suurus 1';
$lang['fontsize2'] = 'Kirja suurus 2';
$lang['fontsize3'] = 'Kirja suurus 3';
$lang['fontcolor1'] = 'Kirja v�rv 1';
$lang['fontcolor2'] = 'Kirja v�rv 2';
$lang['fontcolor3'] = 'Kirja v�rv 3';
$lang['span_class1'] = 'Ajavahemiku(span) klass 1';
$lang['span_class2'] = 'Ajavahemiku(span) klass 2';
$lang['span_class3'] = 'Ajavahemiku(span) klass 3';
$lang['img_poll_size'] = 'H��letuse pildi suurus [px]';
$lang['img_pm_size'] = 'Privaats�numi Staatuse suurus [px]';

//
// Install Process
//
$lang['Welcome_install'] = 'Teretulemast phpBB 2 Installeerimisele';
$lang['Initial_config'] = 'P�hikonfiguratsioon';
$lang['DB_config'] = 'Andmebaasi konfiguratsioon';
$lang['Admin_config'] = 'Administraatori konfiguratsioon';
$lang['continue_upgrade'] = 'Kui sa oled konfiguratsioonifaili oma arvutisse laadinud, siis v�id sa vajutada\'J�tka Uendamist\' nuppu allpool et j�tkata uuenddamise protsessi.  Palun oota konfiguratsioonifaili �leslaadimisega seni, kuni uuendamine on l�petatud.';
$lang['upgrade_submit'] = 'J�tka uuendamist';

$lang['Installer_Error'] = 'Installeerimise k�igus on tekkinud viga';
$lang['Previous_Install'] = 'Tuvastatud on eelnev installeerimine';
$lang['Install_db_error'] = 'Andmebaasi uuendamisel tekkis viga';

$lang['Re_install'] = 'Su eelnev installatsioon on veel aktiivne. <br /><br />Kui sa tahad phpBB 2-e reinstalleerida, siis vajuta allolevale Jah nupule. Selle tegemine kustutab k�ik eenevad andmed, varukoopiaid ei teha! Administraatori kasutajanimi ja parool, millega sa end foorumisse sisse oled loginud, taastatakse p�rast reinstalleerimist, �htegi teist seadet alles ei j��. <br /><br />M�tle hoolikalt j�rele, enne kui Jah vajutad!';

$lang['Inst_Step_0'] = 'Ait�h, et oled valinud phpBB 2-e. Et seda installeerimist l�petada, m�rgi palun �ra allpool n�utud �ksikasjad. Andmebaas, millesse sa installeerima hakkad, peaks enne olemas olema. Kui sa installeerid admebaasi, mis kasutab ODBC-d, nt MS Access, siis peaksid sa enne j�tkamist looma sellele DSN-i.';

$lang['Start_Install'] = 'Alusta Installeerimist';
$lang['Finish_Install'] = 'L�peta Installeerimine';

$lang['Default_lang'] = 'Foorumi vaikimisi keel';
$lang['DB_Host'] = 'Andmebaasi serveri hostinimi/DSN';
$lang['DB_Name'] = 'Sinu andmebaasi nimi';
$lang['DB_Username'] = 'Andmebaasi kasutajanimi';
$lang['DB_Password'] = 'Andmebaasi parool';
$lang['Database'] = 'Sinu andmebaas';
$lang['Install_lang'] = 'Vali installerimiseks keel';
$lang['dbms'] = 'Andmebaasi t��p';
$lang['Table_Prefix'] = 'Prefiks andmebaasi tabelitele';
$lang['Admin_Username'] = 'Administraatori kasutajanimi';
$lang['Admin_Password'] = 'Administraatori parool';
$lang['Admin_Password_confirm'] = 'Administraatori parool [ Kinnita ]';

$lang['Inst_Step_2'] = 'Administraatori kasutajanimi on loodud.  Peamine installeerimine on valmis. N��d viiakse sind uut installeerimist administreerima. Vaata kindlasti �le �ldine Konfiguratsioon, et teha vajalikud muudatused. Ait�h, et valisid phpBB 2-e.';

$lang['Unwriteable_config'] = 'Su konfiguratsioonifail on hetkel kirjutuskaitstud. Selle koopia laetakse alla, kui sa vajutad allolevale nupule. See fail tuleks panna sammasse kausta, kus on phpBB 2. Kui see on tehtud, peaks sa sisse logima adminstraatori kasutajanime ja parooliga, mille sa said eelmises vormis ning �le vaatama Administratsioonipaneeli (vastav link ilmub iga lehek�lje alla ��rde, kui sa oled end sisse loginud), et kontrollida peamisi seadeid. Ait�h, et valisid phpBB 2-e.';
$lang['Download_config'] = 'Lae alla konfiguratsioon';

$lang['ftp_choose'] = 'Vali allalaadimise meetod.';
$lang['ftp_option'] = '<br />Kuna FTP laiendid on selles PHP versioonis lubatud, v�idakse sulle pakkuda v�imalust proovida konfiguratsiooni faili FTP kaudu paika seada.';
$lang['ftp_instructs'] = 'Sa tegid valiku, et soovid automaatselt vajalikud failid FTP kaudu phpBB 2-e kontole kanda.  Palun sisesta vajalik info, et seda protsessi lihtsustada. Note that the FTP path should be the exact path via ftp to your phpBB2 installation as if you were ftping to it using any normal client.';
$lang['ftp_info'] = 'Sisesta oma FTP info';
$lang['Attempt_ftp'] = 'P��a konfiguratsioonifaili FTP-ga paika panna';
$lang['Send_file'] = 'Saada see fail mulle ja seadistan selle FTP kaudu k�sitsi.';
$lang['ftp_path'] = 'FTP teerada phpBB 2-e juurde';
$lang['ftp_username'] = 'Su FTP kasutajanimi';
$lang['ftp_password'] = 'Su FTP parool';
$lang['Transfer_config'] = 'Alusta laadimist';
$lang['NoFTP_config'] = 'FTP kaudu konfiguratsioonifaili paika panemine eba�nnestus. Palun lae see fail alla ja pane k�sitsi vastavasse kataloogi.';

$lang['Install'] = 'Installeeri';
$lang['Upgrade'] = 'Uuenda';

$lang['Install_Method'] = 'Vali installeerimise meetod';
$lang['Install_No_Ext'] = 'Sinu serveri PHP konfiguratsioon ei toeta valitud andmebaasi t��pi.';
$lang['Install_No_PCRE'] = 'phpBB2 n�uab Perliga sobivat Tavaliste Laiendite Moodulit, mida sinu PHP konfiguratsioon ei paista toetavat.';

//
// That's all Folks!
// -------------------------------------------------
