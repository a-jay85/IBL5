<?
/***************************************************************************
 *                                settings.inc.php
 *                            -------------------
 *   begin                : Tuesday, May 6, 2008
 *   copyright            : (C) 2008 J. David Baker
 *   email                : me@jdavidbaker.com
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

define(kSettingLeagueName, 1);
define(kSettingPickTimeLimit, 2);
define(kSettingSendMails, 3);
define(kSettingStartTime, 4);
define(kSettingEndTime, 5);
define(kSettingExpiredPick, 6);
define(kSettingRolloverMethod, 7);
define(kSettingAutopickReduction, 8);
define(kSettingTimeZone, 9);
define(kSettingMaxDelay, 10);

// Config constants
define(kExpireSkipPick, 0); // Default, skip pick when expired
define(kExpireMakePick, 1); // Make a BPA selection when expired

// Settings for kSettingSendMails
define(kEmailOff, 0);
define(kEmailAll, 1);
define(kEmailNextPick, 2);

// Settings for kSettingRolloverMethod
define(kRollIntoTomorrow, 0);
define(kFinishToday, 1);

class settings
{
    public function settings()
    {
        $statement = "select * from settings";
        $result = mysql_query($statement);
        while ($row = mysql_fetch_array($result)) {
            $this->setting[$row['setting_id']] = $row['setting_value'];
        }
    }

    public function get_value($id)
    {
        return $this->setting[$id];
    }

    public function set_value($id, $value)
    {
        $id = mysql_real_escape_string($id);
        $value = mysql_real_escape_string($value);
        $statement = "insert into settings (setting_id) values ('$id')";
        mysql_query($statement);
        $statement = "update settings set setting_value = '$value' where setting_id = '$id'";
        mysql_query($statement);
        $this->setting[$id] = $value;
    }
}
