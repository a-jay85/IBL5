<?php

declare(strict_types=1);

$backticked = "SELECT `ibl_plr`.`pid` FROM `ibl_plr`";
$aliased = "SELECT p.name FROM `ibl_plr` p";
$bareUnqualified = "SELECT name, pid FROM `ibl_plr`";
$star = "SELECT ibl_plr.* FROM `ibl_plr`";
