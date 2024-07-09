<?php
/***************************************************************************
 *                            lang_main.php [Russian]
 *                              -------------------
 *     begin                : Sat Dec 16 2000
 *     copyright            : (C) 2001 The phpBB Group
 *     email                : support@phpbb.com
 *
 *     $Id$
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
// Translation performed by Alexey V. Borzov (borz_off)
// borz_off@rdw.ru
//

// setlocale(LC_ALL, 'ru_RU.ISO-8859-5');
$lang['ENCODING'] = 'windows-1251';
$lang['DIRECTION'] = 'ltr';
$lang['LEFT'] = 'left';
$lang['RIGHT'] = 'right';
$lang['DATE_FORMAT'] = 'd.m.Y'; // This should be changed to the default date format for your language, php date() format

//
// Common, these terms are used
// extensively on several pages
//
$lang['Forum'] = '�����';
$lang['Category'] = '���������';
$lang['Topic'] = '����';
$lang['Topics'] = '����';
$lang['Replies'] = '�������';
$lang['Views'] = '����������';
$lang['Post'] = '���������'; // ???
$lang['Posts'] = '���������'; // ???
$lang['Posted'] = '���������';
$lang['Username'] = '���';
$lang['Password'] = '������';
$lang['Email'] = 'Email';
$lang['Poster'] = '�����'; // ???
$lang['Author'] = '�����';
$lang['Time'] = '�����';
$lang['Hours'] = '����';
$lang['Message'] = '���������';

$lang['1_Day'] = '�� ��������� ����';
$lang['7_Days'] = '�� ��������� 7 ����';
$lang['2_Weeks'] = '�� ��������� 2 ������';
$lang['1_Month'] = '�� ��������� �����';
$lang['3_Months'] = '�� ��������� 3 ������';
$lang['6_Months'] = '�� ��������� 6 �������';
$lang['1_Year'] = '�� ��������� ���';

$lang['Go'] = '�������';
$lang['Jump_to'] = '�������';
$lang['Submit'] = '���������';
$lang['Reset'] = '�������';
$lang['Cancel'] = '������';
$lang['Preview'] = '�����. ��������';
$lang['Confirm'] = '�����������';
$lang['Spellcheck'] = '����������';
$lang['Yes'] = '��';
$lang['No'] = '���';
$lang['Enabled'] = '��������';
$lang['Disabled'] = '���������';
$lang['Error'] = '������';

$lang['Next'] = '����.';
$lang['Previous'] = '����.';
$lang['Goto_page'] = '�� ��������';
$lang['Joined'] = '���������������';
$lang['IP_Address'] = '����� IP';

$lang['Select_forum'] = '�������� �����';
$lang['View_latest_post'] = '���������� ��������� ���������';
$lang['View_newest_post'] = '����� ����� ���������';
$lang['Page_of'] = '�������� <b>%d</b> �� <b>%d</b>'; // Replaces with: Page 1 of 2 for example

$lang['ICQ'] = 'ICQ Number';
$lang['AIM'] = 'AIM Address';
$lang['MSNM'] = 'MSN Messenger';
$lang['YIM'] = 'Yahoo Messenger';

$lang['Forum_Index'] = '������ ������� %s';

$lang['Post_new_topic'] = '������ ����� ����';
$lang['Reply_to_topic'] = '�������� �� ����';
$lang['Reply_with_quote'] = '�������� � �������';

$lang['Click_return_topic'] = '%s��������� � ����%s';
$lang['Click_return_login'] = '%s����������� ��� ���%s';
$lang['Click_return_forum'] = '%s��������� � �����%s';
$lang['Click_view_message'] = '%s����������� ���� ���������%s';
$lang['Click_return_modcp'] = '%s��������� � ������ ���������%s';
$lang['Click_return_group'] = '%s��������� � ���������� � �������%s';

$lang['Admin_panel'] = '������� � ����������������� ������';

$lang['Board_disable'] = '��������, ��� ������ ���������. ���������� ����� �������';

//
// Global Header strings
//
$lang['Registered_users'] = '������������������ ������������:';
$lang['Browsing_forum'] = '������ ���� ����� �������������:';
$lang['Online_users_zero_total'] = '������ ����������� �� ������: <b>0</b>, �� ��� ';
$lang['Online_users_total'] = '������ ����������� �� ������: <b>%d</b>, �� ��� ';
$lang['Online_user_total'] = '������ ����������� �� ������: <b>%d</b>, �� ��� ';
$lang['Reg_users_zero_total'] = '������������������: 0, ';
$lang['Reg_users_total'] = '������������������: %d, ';
$lang['Reg_user_total'] = '������������������: %d, ';
$lang['Hidden_users_zero_total'] = '�������: 0 � ';
$lang['Hidden_users_total'] = '�������: %d � ';
$lang['Hidden_user_total'] = '�������: %d � ';
$lang['Guest_users_zero_total'] = '������: 0';
$lang['Guest_users_total'] = '������: %d';
$lang['Guest_user_total'] = '������: %d';
$lang['Record_online_users'] = '������ ����� ����������� (<b>%s</b>) ����� ���� %s'; // first %s = number of users, second %s is the date.

$lang['Admin_online_color'] = '%s�������������%s';
$lang['Mod_online_color'] = '%s���������%s';

$lang['You_last_visit'] = '�� ��������� ��� �������� %s';
$lang['Current_time'] = '������� ����� %s'; // %s replaced by time

$lang['Search_new'] = '����� ��������� � ������ ���������� ���������';
$lang['Search_your_posts'] = '����� ���� ���������';
$lang['Search_unanswered'] = '����� ��������� ��� �������';

$lang['Register'] = '�����������';
$lang['Profile'] = '�������';
$lang['Edit_profile'] = '������������� ��� �������';
$lang['Search'] = '�����';
$lang['Memberlist'] = '������������';
$lang['FAQ'] = 'FAQ';
$lang['BBCode_guide'] = '����������� �� BBCode';
$lang['Usergroups'] = '������';
$lang['Last_Post'] = '��������� ���������';
$lang['Moderator'] = '���������';
$lang['Moderators'] = '����������';

//
// Stats block text
//
$lang['Posted_articles_zero_total'] = '���� ������������ �� �������� �� ������ ���������'; // Number of posts
$lang['Posted_article_total'] = '���� ������������ �������� ���������: <b>%d</b>'; // Number of posts
$lang['Posted_articles_total'] = '���� ������������ �������� ���������: <b>%d</b>'; // Number of posts
$lang['Registered_users_zero_total'] = '� ��� ��� ������������������ �������������'; // # registered users
$lang['Registered_user_total'] = '����� ������������������ �������������: <b>%d</b>'; // # registered users
$lang['Registered_users_total'] = '����� ������������������ �������������: <b>%d</b>'; // # registered users
$lang['Newest_user'] = '��������� ������������������ ������������: <b>%s%s%s</b>'; // username

$lang['No_new_posts_last_visit'] = '��� ����� ��������� � ���������� ���������';
$lang['No_new_posts'] = '��� ����� ���������';
$lang['New_posts'] = '����� ���������';
$lang['New_post'] = '����� ���������';
$lang['No_new_posts_hot'] = '��� ����� ��������� [ ���������� ���� ]';
$lang['New_posts_hot'] = '����� ��������� [ ���������� ���� ]';
$lang['No_new_posts_locked'] = '��� ����� ��������� [ ���� ������� ]';
$lang['New_posts_locked'] = '����� ��������� [ ���� ������� ]';
$lang['Forum_is_locked'] = '����� ������';

//
// Login
//
$lang['Enter_password'] = '������� ���� ��� � ������ ��� ����� � �������';
$lang['Login'] = '����';
$lang['Logout'] = '�����';

$lang['Forgotten_password'] = '������ ������?';

$lang['Log_me_in'] = '������������� ������� ��� ������ ���������';

$lang['Error_login'] = '�� ����� ��������/���������� ��� ������������ ��� �������� ������.';

//
// Index page
//
$lang['Index'] = '�������';
$lang['No_Posts'] = '��� ���������';
$lang['No_forums'] = '�� ���� ����� ��� �������';

$lang['Private_Message'] = '������ ���������';
$lang['Private_Messages'] = '������ ���������';
$lang['Who_is_Online'] = '��� ������ �� ������';

$lang['Mark_all_forums'] = '�������� ��� ������ ��� ���������';
$lang['Forums_marked_read'] = '��� ������ ���� �������� ��� ���������';

//
// Viewforum
//
$lang['View_forum'] = '�������� ������';

$lang['Forum_not_exist'] = '������, ������� �� �������, �� ����������';
$lang['Reached_on_error'] = '�� ������ �� ��� �������� ��-�� ������';

$lang['Display_topics'] = '�������� ����';
$lang['All_Topics'] = '��� ����';

$lang['Topic_Announcement'] = '<b>����������:</b>';
$lang['Topic_Sticky'] = '<b>������:</b>';
$lang['Topic_Moved'] = '<b>����������:</b>';
$lang['Topic_Poll'] = '<b>[ ����� ]</b>';

$lang['Mark_all_topics'] = '�������� ��� ���� ��� ���������';
$lang['Topics_marked_read'] = '��� ���� � ���� ������ ���� �������� ��� ���������';

$lang['Rules_post_can'] = '�� <b>������</b> �������� ����';
$lang['Rules_post_cannot'] = '�� <b>�� ������</b> �������� ����';
$lang['Rules_reply_can'] = '�� <b>������</b> �������� �� ���������';
$lang['Rules_reply_cannot'] = '�� <b>�� ������</b> �������� �� ���������';
$lang['Rules_edit_can'] = '�� <b>������</b> ������������� ���� ���������';
$lang['Rules_edit_cannot'] = '�� <b>�� ������</b> ������������� ���� ���������';
$lang['Rules_delete_can'] = '�� <b>������</b> ������� ���� ���������';
$lang['Rules_delete_cannot'] = '�� <b>�� ������</b> ������� ���� ���������';
$lang['Rules_vote_can'] = '�� <b>������</b> ���������� � �������';
$lang['Rules_vote_cannot'] = '�� <b>�� ������</b> ���������� � �������';
$lang['Rules_moderate'] = '�� <b>������</b> %s������������ ���� �����%s'; // %s replaced by a href

$lang['No_topics_post_one'] = '� ���� ������ ���� ��� ���������<br />ٸ������ <b>������ ����� ����</b>, � ���� ��������� ������ ������.';

//
// Viewtopic
//
$lang['View_topic'] = '�������� ����';

$lang['Guest'] = '�����';
$lang['Post_subject'] = '��������� ���������';
$lang['View_next_topic'] = '��������� ����';
$lang['View_previous_topic'] = '���������� ����';
$lang['Submit_vote'] = '�������������';
$lang['View_results'] = '����������';

$lang['No_newer_topics'] = '� ���� ������ ��� ����� ����� ���';
$lang['No_older_topics'] = '� ���� ������ ��� ����� ������ ���';
$lang['Topic_post_not_exist'] = '����, ������� �� ���������, �� ����������.';
$lang['No_posts_topic'] = '� ���� ���� ��� ���������';

$lang['Display_posts'] = '�������� ���������';
$lang['All_Posts'] = '��� ���������';
$lang['Newest_First'] = '������� � �����';
$lang['Oldest_First'] = '������� �� ������';

$lang['Back_to_top'] = '��������� � ������';

$lang['Read_profile'] = '���������� �������'; // Followed by username of poster
$lang['Send_email'] = '��������� E-mail '; // Followed by username of poster
$lang['Visit_website'] = '�������� ���� ������';
$lang['ICQ_status'] = '������ ICQ';
$lang['Edit_delete_post'] = '��������/������� ��� ���������';
$lang['View_IP'] = '�������� IP ����� ������';
$lang['Delete_post'] = '������� ��� ���������';

$lang['wrote'] = '�����(�)'; // proceeds the username and is followed by the quoted text
$lang['Quote'] = '������'; // comes before bbcode quote output.
$lang['Code'] = '���'; // comes before bbcode code output.

$lang['Edited_time_total'] = '��������� ��� ���������������: %s (%s), ����� ��������������� %d ���'; // Last edited by me on 12 Oct 2001, edited 1 time in total
$lang['Edited_times_total'] = '��������� ��� ���������������: %s (%s), ����� ��������������� %d ���(�)'; // Last edited by me on 12 Oct 2001, edited 2 times in total

$lang['Lock_topic'] = '������� ����';
$lang['Unlock_topic'] = '����� ������� ����';
$lang['Move_topic'] = '��������� ����';
$lang['Delete_topic'] = '������� ����';
$lang['Split_topic'] = '��������� ����';

$lang['Stop_watching_topic'] = '��������� ������� �� ��������';
$lang['Start_watching_topic'] = '������� �� �������� � ����';
$lang['No_longer_watching'] = '�� ������ �� ������� �� �������� � ���� ����';
$lang['You_are_watching'] = '������ �� ������� �� �������� � ���� ����';

$lang['Total_votes'] = '����� �������������';

//
// Posting/Replying (Not private messaging!)
//
$lang['Message_body'] = '���������';
$lang['Topic_review'] = '����� ����';

$lang['No_post_mode'] = '�� ������ ����� ���������';

$lang['Post_a_new_topic'] = '������ ����� ����';
$lang['Post_a_reply'] = '��������';
$lang['Post_topic_as'] = '������ ����������� ����';
$lang['Edit_Post'] = '������������� ���������';
$lang['Options'] = '���������';

$lang['Post_Announcement'] = '����������';
$lang['Post_Sticky'] = '������';
$lang['Post_Normal'] = '�������';

$lang['Confirm_delete'] = '�� �������, ��� ������ ������� ��� ���������?';
$lang['Confirm_delete_poll'] = '�� �������, ��� ������ ������� ���� �����?';

$lang['Flood_Error'] = '�� �� ������ ��������� ��������� ��������� ����� ����� �����������. ����������, ���������� ���� �������.';
$lang['Empty_subject'] = '�� ������ ������� ��������� ���������, ����� ��������� ����� ����';
$lang['Empty_message'] = '�� ������ ������ ����� ���������';
$lang['Forum_locked'] = '���� ����� ������, �� �� ������ ������ ����� ��������� � ������������� ������.';
$lang['Topic_locked'] = '��� ���� �������, �� �� ������ ������ ������ � ������������� ���������.';
$lang['No_post_id'] = '�� ������ ������� ��������� ��� ��������������';
$lang['No_topic_id'] = '�� ������ ������� ���� ��� ������';
$lang['No_valid_mode'] = '�� ������ ������ ��������� ����, �������� � ������������� ���������. ��������� � ���������� ��� ���.';
$lang['No_such_post'] = '��������� �����������. ��������� � ���������� ��� ���.';
$lang['Edit_own_posts'] = '��������, �� ������ ������������� ������ ���� ����������� ���������';
$lang['Delete_own_posts'] = '��������, �� ������ ������� ������ ���� ����������� ���������';
$lang['Cannot_delete_replied'] = '��������, �� �� ������ ������� ���������, �� ������� ���� �������� ������';
$lang['Cannot_delete_poll'] = '��������, �� �� ������ ������� �������� �����';
$lang['Empty_poll_title'] = '�� ������ ������ ��������� ��� ������';
$lang['To_few_poll_options'] = '�� ������ ������ �� ����� ���� ��������� ������';
$lang['To_many_poll_options'] = '�� ���������� ������ ������� ����� ��������� ������';
$lang['Post_has_no_poll'] = '� ���� ��������� ��� ������';
$lang['Already_voted'] = '�� ��� ���������� � ���� ������';
$lang['No_vote_option'] = '�� ������ ������� ������� ������ ��� �����������';

$lang['Add_poll'] = '�������� �����';
$lang['Add_poll_explain'] = '���� �� �� ������ ��������� ����� � ������ ���������, �������� ���� �������';
$lang['Poll_question'] = '������';
$lang['Poll_option'] = '������� ������';
$lang['Add_option'] = '�������� ��� �������';
$lang['Update'] = '��������';
$lang['Delete'] = '�������';
$lang['Poll_for'] = '����� ������ ����';
$lang['Days'] = '����'; // This is used for the Run poll for ... Days + in admin_forums for pruning
$lang['Poll_for_explain'] = '[ ������� 0 ��� �������� ���� ������, ����� ����� �� �������� ]';
$lang['Delete_poll'] = '������� �����';

$lang['Disable_HTML_post'] = '��������� � ���� ��������� HTML';
$lang['Disable_BBCode_post'] = '��������� � ���� ��������� BBCode';
$lang['Disable_Smilies_post'] = '��������� � ���� ��������� ��������';

$lang['HTML_is_ON'] = 'HTML <u>�������</u>';
$lang['HTML_is_OFF'] = 'HTML <u>��������</u>';
$lang['BBCode_is_ON'] = '%sBBCode%s <u>�������</u>';
$lang['BBCode_is_OFF'] = '%sBBCode%s <u>��������</u>';
$lang['Smilies_are_ON'] = '�������� <u>��������</u>';
$lang['Smilies_are_OFF'] = '�������� <u>���������</u>';

$lang['Attach_signature'] = '������������ ������� (������� ����� �������� � �������)';
$lang['Notify'] = '�������� ��� � ��������� ������';
$lang['Delete_post'] = '������� ���������';

$lang['Stored'] = '���� ��������� ���� ������� ���������';
$lang['Deleted'] = '���� ��������� ���� ������� �������';
$lang['Poll_delete'] = '��� ����� ��� ������� �����';
$lang['Vote_cast'] = '��� ����� ��� ����';

$lang['Topic_reply_notification'] = '����������� �� ������ � ����';

$lang['bbcode_b_help'] = '������ �����: [b]�����[/b]  (alt+b)';
$lang['bbcode_i_help'] = '��������� �����: [i]�����[/i]  (alt+i)';
$lang['bbcode_u_help'] = '������������ �����: [u]�����[/u]  (alt+u)';
$lang['bbcode_q_help'] = '������: [quote]�����[/quote]  (alt+q)';
$lang['bbcode_c_help'] = '��� (���������): [code]���[/code]  (alt+c)';
$lang['bbcode_l_help'] = '������: [list]�����[/list] (alt+l)';
$lang['bbcode_o_help'] = '������������ ������: [list=]�����[/list]  (alt+o)';
$lang['bbcode_p_help'] = '�������� ��������: [img]http://image_url[/img]  (alt+p)';
$lang['bbcode_w_help'] = '�������� ������: [url]http://url[/url] ��� [url=http://url]����� ������[/url]  (alt+w)';
$lang['bbcode_a_help'] = '������� ��� �������� ���� bbCode';
$lang['bbcode_s_help'] = '���� ������: [color=red]�����[/color]  ���������: ����� ������������ color=#FF0000';
$lang['bbcode_f_help'] = '������ ������: [size=x-small]��������� �����[/size]';

$lang['Emoticons'] = '��������';
$lang['More_emoticons'] = '�������������� ��������';

$lang['Font_color'] = '���� ������';
$lang['color_default'] = '�� ���������';
$lang['color_dark_red'] = 'Ҹ���-�������';
$lang['color_red'] = '�������';
$lang['color_orange'] = '���������';
$lang['color_brown'] = '����������';
$lang['color_yellow'] = 'Ƹ����';
$lang['color_green'] = '������';
$lang['color_olive'] = '���������';
$lang['color_cyan'] = '�������';
$lang['color_blue'] = '�����';
$lang['color_dark_blue'] = 'Ҹ���-�����';
$lang['color_indigo'] = '������';
$lang['color_violet'] = '����������';
$lang['color_white'] = '�����';
$lang['color_black'] = '׸����';

$lang['Font_size'] = '������ ������';
$lang['font_tiny'] = '����� ���������';
$lang['font_small'] = '���������';
$lang['font_normal'] = '�������';
$lang['font_large'] = '�������';
$lang['font_huge'] = '��������';

$lang['Close_Tags'] = '������� ����';
$lang['Styles_tip'] = '���������: ����� ������ ��������� ����� � ����������� ������';

//
// Private Messaging
//
$lang['Private_Messaging'] = '������ ���������';

$lang['Login_check_pm'] = '����� � ��������� ������ ���������';
$lang['New_pms'] = '����� ���������: %d'; // You have 2 new messages
$lang['New_pm'] = '����� ���������: %d'; // You have 1 new message
$lang['No_new_pm'] = '����� ��������� ���';
$lang['Unread_pms'] = '������������� ���������: %d';
$lang['Unread_pm'] = '������������� ���������: %d';
$lang['No_unread_pm'] = '��� ������������� ���������';
$lang['You_new_pm'] = '��� ������ ����� ������ ���������';
$lang['You_new_pms'] = '��� ������ ����� ������ ���������';
$lang['You_no_new_pm'] = '� ��� ��� ����� ������ ���������';

$lang['Unread_message'] = '������������� ���������';
$lang['Read_message'] = '����������� ���������';

$lang['Read_pm'] = '��������� ���������';
$lang['Post_new_pm'] = '�������� ����� ���������';
$lang['Post_reply_pm'] = '�������� �� ���������';
$lang['Post_quote_pm'] = '�������� � �������';
$lang['Edit_pm'] = '������������� ���������';

$lang['Inbox'] = '��������';
$lang['Outbox'] = '���������';
$lang['Savebox'] = '�����������';
$lang['Sentbox'] = '������������';
$lang['Flag'] = '����';
$lang['Subject'] = '����';
$lang['From'] = '��';
$lang['To'] = '����';
$lang['Date'] = '����';
$lang['Mark'] = '�������';
$lang['Sent'] = '����������';
$lang['Saved'] = '���������';
$lang['Delete_marked'] = '������� ����������';
$lang['Delete_all'] = '������� ���';
$lang['Save_marked'] = '��������� ����������';
$lang['Save_message'] = '��������� ���������';
$lang['Delete_message'] = '������� ���������';

$lang['Display_messages'] = '�������� ���������'; // Followed by number of days/weeks/months
$lang['All_Messages'] = '��� ���������';

$lang['No_messages_folder'] = '� ���� ����� ��� ���������';

$lang['PM_disabled'] = '����������� �������� ������ ��������� �� ���� ������� ���� ���������';
$lang['Cannot_send_privmsg'] = '��������, ��� �� ��������� ���������� ������ ���������';
$lang['No_to_user'] = '�� ������ ������� ��� ���������� ����� ���������';
$lang['No_such_user'] = '��������, �� ������ ������������ �� ����������';

$lang['Disable_HTML_pm'] = '��������� � ���� ��������� HTML';
$lang['Disable_BBCode_pm'] = '��������� � ���� ��������� BBCode';
$lang['Disable_Smilies_pm'] = '��������� � ���� ��������� ��������';

$lang['Message_sent'] = '���� ��������� ���� ����������';

$lang['Click_return_inbox'] = '%s��������� � ����� &laquo;��������&raquo;%s';
$lang['Click_return_index'] = '%s��������� � ������ �������%s';

$lang['Send_a_new_message'] = '��������� ������ ���������';
$lang['Send_a_reply'] = '�������� �� ������ ���������';
$lang['Edit_message'] = '������������� ������ ���������';

$lang['Notification_subject'] = '��� ������ ����� ������ ���������';

$lang['Find_username'] = '����� ������������';
$lang['Find'] = '�����';
$lang['No_match'] = '�� �������';

$lang['No_post_id'] = '�� ������ ID';
$lang['No_such_folder'] = '����� ����� ���';
$lang['No_folder'] = '�� ������� �����';

$lang['Mark_all'] = '�������� ���';
$lang['Unmark_all'] = '����� ���������';

$lang['Confirm_delete_pm'] = '�� �������, ��� ������ ������� ��� ���������?';
$lang['Confirm_delete_pms'] = '�� �������, ��� ������ ������� ��� ���������?';

$lang['Inbox_size'] = '���� ����� &laquo;��������&raquo; ��������� �� %d%%'; // eg. Your Inbox is 50% full
$lang['Sentbox_size'] = '���� ����� &laquo;������������&raquo; ��������� �� %d%%';
$lang['Savebox_size'] = '���� ����� &laquo;�����������&raquo; ��������� �� %d%%';

$lang['Click_view_privmsg'] = '%s������� � ����� &laquo;��������&raquo;%s';

//
// Profiles/Registration
//
$lang['Viewing_user_profile'] = '������� ������������ %s'; // %s is username
$lang['About_user'] = '� ������������ %s'; // ����� '������������' - ����� �� �������������� � �������/������� �����

$lang['Preferences'] = '������ ���������';
$lang['Items_required'] = '���� ���������� * ����������� � ����������, ���� �� ������� ��������';
$lang['Registration_info'] = '��������������� ����������';
$lang['Profile_info'] = '�������';
$lang['Profile_info_warn'] = '��� ���������� ����� � �������� �������';
$lang['Avatar_panel'] = '���������� ��������';
$lang['Avatar_gallery'] = '������� ������';

$lang['Website'] = '����';
$lang['Location'] = '������';
$lang['Contact'] = '��� ��������� �'; // ��� ��������� � Vasya_Poopkin
$lang['Email_address'] = '����� E-mail';
$lang['Email'] = 'E-mail';
$lang['Send_private_message'] = '��������� ������ ���������';
$lang['Hidden_email'] = '[ ����� ]';
$lang['Search_user_posts'] = '������ ��� �������� ����� ������������';
$lang['Interests'] = '��������';
$lang['Occupation'] = '��� �������';
$lang['Poster_rank'] = '������';

$lang['Total_posts'] = '����� ���������';
$lang['User_post_pct_stats'] = '%.2f%% �� ������ �����'; // 15% of total
$lang['User_post_day_stats'] = '%.2f ��������� � ����'; // 1.5 posts per day
$lang['Search_user_posts'] = '����� ��� ��������� ������������ %s'; // Find all posts by username

$lang['No_user_id_specified'] = '��������, ������ ������������ �� ����������';
$lang['Wrong_Profile'] = '�� �� ������ ������������� ����� �������.';

$lang['Only_one_avatar'] = '����� ���� ������ ������ ���� ��� �������';
$lang['File_no_data'] = '���� �� ���������� ���� URL �� �������� ������';
$lang['No_connection_URL'] = '���������� ���������� ���������� � ��������� ���� URL';
$lang['Incomplete_URL'] = '�� ������� �������� URL';
$lang['Wrong_remote_avatar_format'] = '�������� URL �������� �������';
$lang['No_send_account_inactive'] = '��������, �� ������ �� ����� ���� ������, ��� ��� ���� ������� ������ ���������. ���������� � �������������� ������ �� �������������� �����������.';

$lang['Always_smile'] = '�������� ������ ��������';
$lang['Always_html'] = 'HTML ������ �������';
$lang['Always_bbcode'] = 'BBCode ������ �������';
$lang['Always_add_sig'] = '������ ������������ ��� �������';
$lang['Always_notify'] = '������ �������� ��� �� �������';
$lang['Always_notify_explain'] = '����� ���-������ ������� �� ����, � ������� �� ������, ��� ���������� E-mail. ��� ����� ����� ��������� ��� ���������� ���������.';

$lang['Board_style'] = '������� ��� �������';
$lang['Board_lang'] = '����';
$lang['No_themes'] = '� ���� ��� �������� ����';
$lang['Timezone'] = '������� ����';
$lang['Date_format'] = '������ ����';
$lang['Date_format_explain'] = '��������� ��������� ������� <a href="http://www.php.net/date" target="_other">date()</a> ����� PHP';
$lang['Signature'] = '�������';
$lang['Signature_explain'] = '��� �����, ������� ����� ��������� � ����������� ���� ����������. ����� ��� ���������� %d ���������.';
$lang['Public_view_email'] = '������ ���������� ��� ����� E-mail';

$lang['Current_password'] = '������� ������';
$lang['New_password'] = '����� ������';
$lang['Confirm_password'] = '����������� ������';
$lang['Confirm_password_explain'] = '�� ������ ������� ��� ������� ������, ���� ������ �������� ��� ��� �������� ���� ����� E-mail.';
$lang['password_if_changed'] = '���������� ������ ������ ���� �� ������ ��� ��������';
$lang['password_confirm_if_changed'] = '������������ ������ ����� � ��� ������, ���� �� �������� ��� ����.';

$lang['Avatar'] = '�������';
$lang['Avatar_explain'] = '���������� ��������� ����������� ��� ����������� � ��� � ����������. ����� ���� �������� ������ ���� �����������, ������� �� ����� %d ��������, ������� �� ����� %d �������� � ������� �� ����� %d ��.';
$lang['Upload_Avatar_file'] = '��������� ������� � ������ ����������';
$lang['Upload_Avatar_URL'] = '��������� ������� � URL';
$lang['Upload_Avatar_URL_explain'] = '������� URL �� �������� ��������� ���� � ������������, �� ����� ���������� �� ���� ����.';
$lang['Pick_local_Avatar'] = '������� ������� �� �������';
$lang['Link_remote_Avatar'] = '���������� ������� � ������� �������';
$lang['Link_remote_Avatar_explain'] = '������� URL �����������, �� ������� �� ������ ���������.';
$lang['Avatar_URL'] = 'URL ����������� �������';
$lang['Select_from_gallery'] = '������� ������� �� �������';
$lang['View_avatar_gallery'] = '�������� �������';

$lang['Select_avatar'] = '�������� �������';
$lang['Return_profile'] = '��������� � �������';
$lang['Select_category'] = '�������� ���������';

$lang['Delete_Image'] = '������� �����������';
$lang['Current_Image'] = '������� �����������';

$lang['Notify_on_privmsg'] = '���������� � ����� ������ ����������';
$lang['Popup_on_privmsg'] = '��������� ����� ���� ��� ����� ������ ���������';
$lang['Popup_on_privmsg_explain'] = '� ��������� �������� ����� ����������� ����� ���� �������� � ������������ � ������� ������ ������� ���������.';
$lang['Hide_user'] = '�������� ���� ���������� �� ������';

$lang['Profile_updated'] = '��� ������� ��� �������';
$lang['Profile_updated_inactive'] = '��� ������� ��� �������, �� �� �������� ������ ������, ��� ��� ������ ���� ������� ������ ���������. ��������� ��� �������� ����, ����� ������ ��� ����� �������������� ������� ������ ���, ���� ��������� ��������� ��������������, ��������� ���� ��� ������� �������������.';

$lang['Password_mismatch'] = '�������� ������ �� ���������';
$lang['Current_password_mismatch'] = '�������� ���� ������ �� ��������� � ������� �� ����';
$lang['Password_long'] = '��� ������ ������ ���� �� ������� 32 ��������';
$lang['Username_taken'] = '��������, ������������ � ����� ������ ��� ����������';
$lang['Username_invalid'] = '��������, ��� ��� �������� ������������ �������, (�������� ")';
$lang['Username_disallowed'] = '��������, ��� ��� ���� ��������� � �������������';
$lang['Email_taken'] = '��������, ���� ����� E-mail ��� ����� ������ �������������';
$lang['Email_banned'] = '��������, ���� ����� E-mail ��������� � ������ ������';
$lang['Email_invalid'] = '��������, ���� ����� E-mail ����������';
$lang['Invalid_username'] = '����������� ��� ������������ ��� ������, ���������, ���� �������� ������������ ������� (�������� ")';
$lang['Signature_too_long'] = '������� ������� �������';
$lang['Fields_empty'] = '�� ������ ��������� ������������ ����';
$lang['Avatar_filetype'] = '���� ������� ������ ���� .jpg, .gif ��� .png';
$lang['Avatar_filesize'] = '����� ����� ������� ������ ���� �� ����� %d ��';
$lang['Avatar_imagesize'] = '������� ������ ���� �� ������ %d �������� � ������ � %d �������� � ������';

$lang['Welcome_subject'] = '����� ���������� �� ������ %s';
$lang['New_account_subject'] = '����� ������������';
$lang['Account_activated_subject'] = '������� ������ ��������������';

$lang['Account_added'] = '������� �� �����������, ������� ������ ���� �������. �� ������ ����� � �������, ��������� ���� ��� � ������.';
$lang['Account_inactive'] = '������� ������ ���� �������. �� ���� ������ ��������� ����������� ������� ������, ���� ��� ����������� ��� ������ �� �������� ���� �����. ��������� ���� ����� ��� ����� ��������� ����������.';
$lang['Account_inactive_admin'] = '������� ������ ���� �������. �� ���� ������ ��������� ����������� ����� ������� ������ ����������������. �� ��� ��������� E-mail, �, ��� ������ ��� ������������ ���� ������� ������, �� �������� �����������.';
$lang['Account_active'] = '���� ������� ������ ���� ��������������. ������� �� �����������.';
$lang['Account_active_admin'] = '���� ������� ������ ���� ��������������.';
$lang['Reactivate'] = '����� �������������� ������� ������';
$lang['Already_activated'] = '�� ��� �������������� ���� ������� ������';
$lang['COPPA'] = '���� ������� ������ ���� �������, �� ������ ��� ������ ���� ��������, ����� ��������� ���������� ���� ������� ��� �� E-mail.';

$lang['Registration'] = '������� �����������';
$lang['Reg_agreement'] = '���� �������������� � ���������� ����� ������ ��������� ������� ��� ������������� ������������ ��������� ��� ����� �������, ��� ��������� ����������� ����������. ����� ������� �� ��������,  ��� ��������� �� ���� ������� �������� ����� ������ �� �������, � �� ������������� ������� (����� ���������, ����������� � ���������������) � ������������� �� ����� ���� ������������ �� �� ����������.<br /><br /> �� ������������ �� ��������� ��������������, ����������, �������������� ���������, ���������������� ���������, �������� � ������������ ����� � ������ ���������, ������� �������� ��������������� ������. ������� ���������� ����� ��������� ����� �������� � ������ ������������ ���������� �� ������� (��� ���� ��� ��������� ����� ��������� � �����������). IP ������ ���� ��������� ����������� ��� ����������� ���������� ����� ��������. �� ������������ � ���, ��� �������������� ������ ����� ����� �������, ���������������, ��������� ��� ������� ����� ���� � ����� ����� �� ������ ����������. ��� ������������ �� �������� � ���, ��� �������� ���� ���������� ����� ��������� � ���� ������. ���� ��� ���������� �� ����� ������� ������� ����� ��� ������ ����������, ������������� ������� �� ����� ���� ������������ �� �������� �������, ������� ����� �������� � �������������������� ������� � ���.<br /><br /> ��� ������ ���������� cookies ��� �������� ���������� �� ����� ����������. ��� cookie �� �������� ������� ���������� �� �������� ���� � ������ ���� ��� ��������� �������� ������ �������. ��� E-mail ����� ������������ ���� ��� ������������� ����� ����������� � ������ (� ��� ������� ������ ������ ���� �� �������� �������).<br /><br />�������� �� ������ ����������� �� ������������� ��� �������� � ����� ���������.';

$lang['Agree_under_13'] = '� �������� � ����� ��������� � ��� <b>������</b> 13 ���';
$lang['Agree_over_13'] = '� �������� � ����� ��������� � � <b>������</b> 13 ���';
$lang['Agree_not'] = '� �� �������� � ����� ���������';

$lang['Wrong_activation'] = '�������� ���� ���� ����������� �� ��������� � ���������� � ����';
$lang['Send_password'] = '�������� ����� ������';
$lang['Password_updated'] = '����� ������ ��� ������, ��������� �������� ����, ����� ������ ��� ��� ��������������';
$lang['No_email_match'] = '�������� ���� ����� E-mail �� ��������� � ���������� �� ����� ������������';
$lang['New_password_activation'] = '����������� ������ ������';
$lang['Password_activated'] = '���� ������� ������ ���� ����� ��������������. ��� ����� � ������� ����������� ������ �� ����������� ��� ������.';

$lang['Send_email_msg'] = '��������� E-mail';
$lang['No_user_specified'] = '������������ �� ��� ������';
$lang['User_prevent_email'] = '������������ �� ������ �������� E-mail. ���������� ��������� ���/�� ������ ���������';
$lang['User_not_exist'] = '������������ �� ����������';
$lang['CC_email'] = '��������� ����� ��������� ������ ����';
$lang['Email_message_desc'] = '��������� ����� ���������� � ���� �������� ������, �� ��������� � ���� HTML ��� BBCode. � �������� ��������� ������ ����� ������������ ��� ����� E-mail.';
$lang['Flood_email_limit'] = '�� �� ������ ��������� ��� ���� E-mail ����� ����� �����������, ���������� ������� ��� �������.';
$lang['Recipient'] = '����������';
$lang['Email_sent'] = '��������� ���� ����������';
$lang['Send_email'] = '��������� E-mail';
$lang['Empty_subject_email'] = '�� ������ ������� ���� ���������';
$lang['Empty_message_email'] = '�� ������ ������� ����� ��������� ��� ��������';

//
// Memberslist
//
$lang['Select_sort_method'] = '����������� ��';
$lang['Sort'] = '�����������';
$lang['Sort_Top_Ten'] = '������ ����� �������� ����������';
$lang['Sort_Joined'] = '���� �����������';
$lang['Sort_Username'] = '����� ������������';
$lang['Sort_Location'] = '���������������';
$lang['Sort_Posts'] = '���������� ���������';
$lang['Sort_Email'] = '������ E-mail';
$lang['Sort_Website'] = '������ �����';
$lang['Sort_Ascending'] = '�� �����������';
$lang['Sort_Descending'] = '�� ��������';
$lang['Order'] = ''; // �� �����, � ���������� ������������ � ��������� 'Order ascending'

//
// Group control panel
//
$lang['Group_Control_Panel'] = '������ ���������� ��������';
$lang['Group_member_details'] = '���������� � �������� � �������';
$lang['Group_member_join'] = '�������� � ������';

$lang['Group_Information'] = '���������� � ������';
$lang['Group_name'] = '�������� ������';
$lang['Group_description'] = '�������� ������';
$lang['Group_membership'] = '�������� � ������';
$lang['Group_Members'] = '����� ������';
$lang['Group_Moderator'] = '��������� ������';
$lang['Pending_members'] = '��������� � ����� ������';

$lang['Group_type'] = '��� ������';
$lang['Group_open'] = '������ � �������� ���������';
$lang['Group_closed'] = '������ � �������� ���������';
$lang['Group_hidden'] = '������� ������';

$lang['Current_memberships'] = '��������� ������ �����';
$lang['Non_member_groups'] = '�� ��������� ������ �����';
$lang['Memberships_pending'] = '�������� � ����� �����';

$lang['No_groups_exist'] = '��� �� ����� ������';
$lang['Group_not_exist'] = '����� ������ �� ����������';

$lang['Join_group'] = '�������� � ������';
$lang['No_group_members'] = '� ���� ������ ��� �� ������ �����';
$lang['Group_hidden_members'] = '��� ������ ������, �� �� ������ ���������� � ������';
$lang['No_pending_group_members'] = '� ���� ������ ��� ���������� � �����';
$lang['Group_joined'] = '�� ��������� � ���������� � ������. ����� ���� ������� ������� ��������� ������, ��� ����� �������� �����������.';
$lang['Group_request'] = '���� ������ ������� � ���������� � ������.';
$lang['Group_approved'] = '���� ������� ���� �������������.';
$lang['Group_added'] = '�� ���� �������� � ������';
$lang['Already_member_group'] = '�� ��� ��������� ������ ���� ������';
$lang['User_is_member_group'] = '������������ ��� �������� ������ ���� ������';
$lang['Group_type_updated'] = '��� ������ ������� �������';

$lang['Could_not_add_user'] = '���������� ������������ �� ����������';
$lang['Could_not_anon_user'] = '�� �� ������ ������� ���������� ������������ ������ ������';

$lang['Confirm_unsub'] = '�� �������, ��� ������ ����� �� ���� ������?';
$lang['Confirm_unsub_pending'] = '�� �������, ��� ������ ���������� �� ������� � ���� ������? ���� ������� � ���������� �� ���� �� �������������, �� ���������!';

$lang['Unsub_success'] = '�� ������� �������� ��� ������.';

$lang['Approve_selected'] = '�������� ����������';
$lang['Deny_selected'] = '��������� ����������';
$lang['Not_logged_in'] = '�� ������ ����� � �������, ������ ��� �������� � ������.';
$lang['Remove_selected'] = '������� ����������';
$lang['Add_member'] = '�������� ����� ������';
$lang['Not_group_moderator'] = '�� �� ��������� ����������� ������ � �� ������ ��������� ������ ��������';

$lang['Login_to_join'] = '������� � �������, ����� ������ ��� �������� � �������';
$lang['This_open_group'] = '��� ������ � �������� ���������, �� ������ ������ ������� � ����������';
$lang['This_closed_group'] = '��� ������ � �������� ���������, ����� ������������ �� �����������';
$lang['This_hidden_group'] = '��� ������� ������, �������������� ���������� ������������� �� �����������';
$lang['Member_this_group'] = '�� ���� ���� ������';
$lang['Pending_this_group'] = '�� �������� � ����� ���� ������';
$lang['Are_group_moderator'] = '�� ��������� ���� ������';
$lang['None'] = '���';

$lang['Subscribe'] = '�����������';
$lang['Unsubscribe'] = '����������';
$lang['View_Information'] = '����������� ����������';

//
// Search
//
$lang['Search_query'] = '������';
$lang['Search_options'] = '��������� �������';

$lang['Search_keywords'] = '�������� �����';
$lang['Search_keywords_explain'] = '�� ������ ������������ <u>AND</u> ����� ���������� �����, ������� ������ ���� � �����������, <u>OR</u> ��� ����, ������� ����� ���� � �����������, � <u>NOT</u> ��� ����, ������� � ����������� ���� �� ������. ����������� * � �������� ������� ��� ���������� ����������.';
$lang['Search_author'] = '����� �� ������';
$lang['Search_author_explain'] = '����������� * � �������� �������';

$lang['Search_for_any'] = '������ ����� �����/����� � ������ ��������';
$lang['Search_for_all'] = '������ ��� �����';
$lang['Search_title_msg'] = '������ � ��������� ��� � ������� ���������';
$lang['Search_msg_only'] = '������ ������ � ������� ���������';

$lang['Return_first'] = '���������� ������'; // followed by xxx characters
$lang['characters_posts'] = '�������� ���������';

$lang['Search_previous'] = '����� ����������'; // followed by days, weeks, months, year, all

$lang['Sort_by'] = '����������� ��';
$lang['Sort_Time'] = '������� ����������';
$lang['Sort_Post_Subject'] = '��������� ���������';
$lang['Sort_Topic_Title'] = '����';
$lang['Sort_Author'] = '������';
$lang['Sort_Forum'] = '������';

$lang['Display_results'] = '���������� ���������� ���';
$lang['All_available'] = '��� ���������';
$lang['No_searchable_forums'] = '� ��� ��� ������� � ������ �� � ����� �� ������� �� �����';

$lang['No_search_match'] = '���������� ��� ��� ��������� �� �������';
$lang['Found_search_match'] = '����������� ������: %d'; // eg. Search found 1 match
$lang['Found_search_matches'] = '����������� ������: %d'; // eg. Search found 24 matches

$lang['Close_window'] = '������� ����';

//
// Auth related entries
//
// Note the %s will be replaced with one of the following 'user' arrays
$lang['Sorry_auth_announce'] = '��������, ������ %s ����� ��������� ���������� � ���� ������';
$lang['Sorry_auth_sticky'] = '��������, ������ %s ����� ��������� ������ ���� � ���� ������';
$lang['Sorry_auth_read'] = '��������, ������ %s ����� ������ ��������� � ���� ������';
$lang['Sorry_auth_post'] = '��������, ������ %s ����� �������� ���� � ���� ������';
$lang['Sorry_auth_reply'] = '��������, ������ %s ����� �������� �� ��������� � ���� ������';
$lang['Sorry_auth_edit'] = '��������, ������ %s ����� ������������� ��������� � ���� ������';
$lang['Sorry_auth_delete'] = '��������, ������ %s ����� ������� ��������� � ���� ������';
$lang['Sorry_auth_vote'] = '��������, ������ %s ����� ���������� � ������� ����� ������';

// These replace the %s in the above strings
$lang['Auth_Anonymous_Users'] = '<b>�����</b>';
$lang['Auth_Registered_Users'] = '<b>������������������ ������������</b>';
$lang['Auth_Users_granted_access'] = '<b>������������ �� ������������ ������� �������</b>';
$lang['Auth_Moderators'] = '<b>����������</b>';
$lang['Auth_Administrators'] = '<b>��������������</b>';

$lang['Not_Moderator'] = '�� �� ��������� ����������� ����� ������';
$lang['Not_Authorised'] = '��� �������';

$lang['You_been_banned'] = '��� ��� ������ ������ � ������<br />���������� � ���������� ��� �������������� ������� �� �������������� �����������';

//
// Viewonline
//
$lang['Reg_users_zero_online'] = '������ �� ������ ������������������ �������������: 0 � '; // There ae 5 Registered and
$lang['Reg_users_online'] = '������ �� ������ ������������������ �������������: %d � '; // There ae 5 Registered and
$lang['Reg_user_online'] = '������ �� ������ ������������������ �������������: %d � ';
$lang['Hidden_users_zero_online'] = '������� �������������: 0'; // 6 Hidden users online
$lang['Hidden_users_online'] = '������� �������������: %d';
$lang['Hidden_user_online'] = '������� �������������: %d'; // 6 Hidden users online
$lang['Guest_users_online'] = '������ �� ������ ������: %d';
$lang['Guest_users_zero_online'] = '������ �� ������ ������: 0'; // There are 10 Guest users online
$lang['Guest_user_online'] = '������ �� ������ ������: %d';
$lang['No_users_browsing'] = '���� ����� ������ ����� �� �������������';

$lang['Online_explain'] = '��� ������ �������� �� ���������� ������������� �� ��������� ���� �����';

$lang['Forum_Location'] = '����� �� ������';
$lang['Last_updated'] = '��������� ���������';

$lang['Forum_index'] = '������ �������';
$lang['Logging_on'] = '���� � �������';
$lang['Posting_message'] = '���������� ���������';
$lang['Searching_forums'] = '����� �� ������';
$lang['Viewing_profile'] = '�������� �������';
$lang['Viewing_online'] = '�������� &laquo;��� ������ �� ������&raquo;';
$lang['Viewing_member_list'] = '�������� ������ �������������';
$lang['Viewing_priv_msgs'] = '�������� ������ ���������';
$lang['Viewing_FAQ'] = '�������� FAQ';

//
// Moderator Control Panel
//
$lang['Mod_CP'] = '������ ���������';
$lang['Mod_CP_explain'] = '����� �� ������ ��������� �������� ��������� ����� ������. �� ������ ���������, ���������, ���������� ��� ������� ����� ���������� ���.';

$lang['Select'] = '�������';
$lang['Delete'] = '�������';
$lang['Move'] = '�����������';
$lang['Lock'] = '�������';
$lang['Unlock'] = '�������';

$lang['Topics_Removed'] = '��������� ���� ���� ������� ������� �� ���� ������';
$lang['Topics_Locked'] = '��������� ���� ���� �������';
$lang['Topics_Moved'] = '��������� ���� ���� ����������';
$lang['Topics_Unlocked'] = '��������� ���� ���� �������';
$lang['No_Topics_Moved'] = '�� ���� ���������� �� ����� ����';

$lang['Confirm_delete_topic'] = '�� ������������� ������ ������� ��������� ����?';
$lang['Confirm_lock_topic'] = '�� ������������� ������ ������� ��������� ����?';
$lang['Confirm_unlock_topic'] = '�� ������������� ������ ������� ��������� ����?';
$lang['Confirm_move_topic'] = '�� ������������� ������ ����������� ��������� ����?';

$lang['Move_to_forum'] = '����������� � �����';
$lang['Leave_shadow_topic'] = '�������� ������ � ������ ������';

$lang['Split_Topic'] = '���������� ����';
$lang['Split_Topic_explain'] = '� �������������� ���� ����� �� ������ ��������� ���� �� ��� ���� ������� ��������� �� ������, ���� ������ �� ���������� ���������';
$lang['Split_title'] = '��������� ����� ����';
$lang['Split_forum'] = '����� ��� ����� ����';
$lang['Split_posts'] = '�������� ��������� ���������';
$lang['Split_after'] = '�������� ��� ��������� ����� ����������';
$lang['Topic_split'] = '��������� ���� ���� ������� ��������';

$lang['Too_many_error'] = '�� ������� ������� ����� ���������. �� ������ ������� ������ ���� ���������, ����� �������� ��� ��������� ����� ����.';

$lang['None_selected'] = '�� �� ������� �� ����� ���� ��� ���������� ���� ��������. ��������� ����� � ��������.';
$lang['New_forum'] = '����� �����';

$lang['This_posts_IP'] = 'IP ����� ��� ����� ���������';
$lang['Other_IP_this_user'] = '������ IP ������ � ������� ����� ���� ������������';
$lang['Users_this_IP'] = '������������, �������� � ����� IP';
$lang['IP_info'] = '���������� �� IP ������';
$lang['Lookup_IP'] = '���������� ���� ��� IP';

//
// Timezones ... for display on each page
//
$lang['All_times'] = '������� ����: %s'; // This is followed by GMT and the timezone offset

$lang['-12'] = 'GMT - 12';
$lang['-11'] = 'GMT - 11';
$lang['-10'] = 'GMT - 10';
$lang['-9'] = 'GMT - 9';
$lang['-8'] = 'GMT - 8';
$lang['-7'] = 'GMT - 7';
$lang['-6'] = 'GMT - 6';
$lang['-5'] = 'GMT - 5';
$lang['-4'] = 'GMT - 4';
$lang['-3.5'] = 'GMT - 3:30';
$lang['-3'] = 'GMT - 3';
$lang['-2'] = 'GMT - 2';
$lang['-1'] = 'GMT - 1';
$lang['0'] = 'GMT';
$lang['1'] = 'GMT + 1';
$lang['2'] = 'GMT + 2';
$lang['3'] = 'GMT + 3';
$lang['3.5'] = 'GMT + 3:30';
$lang['4'] = 'GMT + 4';
$lang['4.5'] = 'GMT + 4:30';
$lang['5'] = 'GMT + 5';
$lang['5.5'] = 'GMT + 5:30';
$lang['6'] = 'GMT + 6';
$lang['6.5'] = 'GMT + 6:30';
$lang['7'] = 'GMT + 7';
$lang['8'] = 'GMT + 8';
$lang['9'] = 'GMT + 9';
$lang['9.5'] = 'GMT + 9:30';
$lang['10'] = 'GMT + 10';
$lang['11'] = 'GMT + 11';
$lang['12'] = 'GMT + 12';

// ��� ��� ����������� ����, ������ ��� ��� ���� ������
$lang['tz']['-12'] = 'GMT - 12';
$lang['tz']['-11'] = 'GMT - 11';
$lang['tz']['-10'] = 'GMT - 10';
$lang['tz']['-9'] = 'GMT - 9';
$lang['tz']['-8'] = 'GMT - 8';
$lang['tz']['-7'] = 'GMT - 7';
$lang['tz']['-6'] = 'GMT - 6';
$lang['tz']['-5'] = 'GMT - 5';
$lang['tz']['-4'] = 'GMT - 4';
$lang['tz']['-3.5'] = 'GMT - 3:30';
$lang['tz']['-3'] = 'GMT - 3';
$lang['tz']['-2'] = 'GMT - 2';
$lang['tz']['-1'] = 'GMT - 1';
$lang['tz']['0'] = 'GMT (����� �� ��������)';
$lang['tz']['1'] = 'GMT + 1';
$lang['tz']['2'] = 'GMT + 2';
$lang['tz']['3'] = 'GMT + 3 (���������� �����)';
$lang['tz']['3.5'] = 'GMT + 3:30';
$lang['tz']['4'] = 'GMT + 4 (������ ���������� �����)';
$lang['tz']['4.5'] = 'GMT + 4:30';
$lang['tz']['5'] = 'GMT + 5';
$lang['tz']['5.5'] = 'GMT + 5:30';
$lang['tz']['6'] = 'GMT + 6';
$lang['tz']['6.5'] = 'GMT + 6:30';
$lang['tz']['7'] = 'GMT + 7';
$lang['tz']['8'] = 'GMT + 8';
$lang['tz']['9'] = 'GMT + 9';
$lang['tz']['9.5'] = 'GMT + 9:30';
$lang['tz']['10'] = 'GMT + 10';
$lang['tz']['11'] = 'GMT + 11';
$lang['tz']['12'] = 'GMT + 12';

$lang['datetime']['Sunday'] = '�����������';
$lang['datetime']['Monday'] = '�����������';
$lang['datetime']['Tuesday'] = '�������';
$lang['datetime']['Wednesday'] = '�����';
$lang['datetime']['Thursday'] = '�������';
$lang['datetime']['Friday'] = '�������';
$lang['datetime']['Saturday'] = '�������';
$lang['datetime']['Sun'] = '��';
$lang['datetime']['Mon'] = '��';
$lang['datetime']['Tue'] = '��';
$lang['datetime']['Wed'] = '��';
$lang['datetime']['Thu'] = '��';
$lang['datetime']['Fri'] = '��';
$lang['datetime']['Sat'] = '��';
$lang['datetime']['January'] = '������';
$lang['datetime']['February'] = '�������';
$lang['datetime']['March'] = '����';
$lang['datetime']['April'] = '������';
$lang['datetime']['May'] = '���';
$lang['datetime']['June'] = '����';
$lang['datetime']['July'] = '����';
$lang['datetime']['August'] = '������';
$lang['datetime']['September'] = '��������';
$lang['datetime']['October'] = '�������';
$lang['datetime']['November'] = '������';
$lang['datetime']['December'] = '�������';
$lang['datetime']['Jan'] = '���';
$lang['datetime']['Feb'] = '���';
$lang['datetime']['Mar'] = '���';
$lang['datetime']['Apr'] = '���';
$lang['datetime']['May'] = '���';
$lang['datetime']['Jun'] = '���';
$lang['datetime']['Jul'] = '���';
$lang['datetime']['Aug'] = '���';
$lang['datetime']['Sep'] = '���';
$lang['datetime']['Oct'] = '���';
$lang['datetime']['Nov'] = '���';
$lang['datetime']['Desc'] = '���';

//
// Errors (not related to a
// specific failure on a page)
//
$lang['Information'] = '����������';
$lang['Critical_Information'] = '����������� ����������';

$lang['General_Error'] = '����� ������';
$lang['Critical_Error'] = '����������� ������';
$lang['An_error_occured'] = '��������� ������';
$lang['A_critical_error'] = '��������� ����������� ������';

//
// That's all Folks!
// -------------------------------------------------
