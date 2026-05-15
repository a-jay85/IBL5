<?php

declare(strict_types=1);

$fromBare = "SELECT * FROM ibl_plr WHERE pid = ?";
$joinBare = "SELECT * FROM `ibl_team_info` t JOIN ibl_plr p ON t.tid = p.tid";
$updateBare = "UPDATE ibl_votes_EOY SET mvp_1 = ?";
$insertBare = "INSERT INTO ibl_draft (pid) VALUES (?)";
$deleteBare = "DELETE FROM ibl_settings WHERE id = ?";
