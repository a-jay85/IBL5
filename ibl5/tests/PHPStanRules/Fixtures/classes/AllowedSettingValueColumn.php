<?php

declare(strict_types=1);

$select = "SELECT setting_value FROM ibl_settings WHERE setting_key = ?";
$upsert = "INSERT INTO ibl_settings (setting_key, setting_value, league) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
