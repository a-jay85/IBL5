<?php

declare(strict_types=1);

$bareSelect = "SELECT value FROM ibl_settings WHERE setting_key = ? AND league = 'ibl'";
$bareSet = "UPDATE ibl_settings SET value = ? WHERE setting_key = ?";
$aliasBacktick = "SELECT s.`value` FROM `ibl_settings` s WHERE s.setting_key = ?";
