<?php

declare(strict_types=1);

$fromOk = "SELECT * FROM `ibl_plr` WHERE pid = ?";
$joinOk = "SELECT * FROM `ibl_team_info` t JOIN `ibl_plr` p ON t.tid = p.tid";
$updateOk = "UPDATE `ibl_votes_EOY` SET mvp_1 = ?";
$insertOk = "INSERT INTO `ibl_draft` (pid) VALUES (?)";
$deleteOk = "DELETE FROM `ibl_settings` WHERE id = ?";
