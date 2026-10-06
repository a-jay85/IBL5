<?php

declare(strict_types=1);

$cacheSelect = "SELECT `value`, expiration FROM `cache` WHERE cache_key = ?";
$cacheInsert = "INSERT INTO `cache` (cache_key, `value`, expiration) VALUES (?, ?, ?)";
$row = ['value' => 'cached'];
$cached = $row['value'];
