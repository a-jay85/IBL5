<?php

declare(strict_types=1);

function runDirectQueries(\mysqli $db): void
{
    $db->query('DELETE FROM `ibl_hist`');
    $db->query('INSERT INTO `ibl_hist` VALUES (1)');
}
