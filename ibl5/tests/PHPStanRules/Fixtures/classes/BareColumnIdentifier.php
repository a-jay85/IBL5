<?php

declare(strict_types=1);

$select = "SELECT ibl_plr.pid FROM ibl_plr";
$qualified = "SELECT ibl_team_info.team_name FROM ibl_team_info";
$where = "DELETE WHERE ibl_plr.retired = 0";
