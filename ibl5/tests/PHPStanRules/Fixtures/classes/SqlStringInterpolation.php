<?php

declare(strict_types=1);

function buildInterpolatedQueries(string $id, string $col): array
{
    $select = "SELECT * FROM ibl_plr WHERE pid = $id";
    $update = "UPDATE ibl_plr SET name = 'x' WHERE pid = $id";
    $order  = "SELECT * FROM ibl_plr ORDER BY {$col}";

    return [$select, $update, $order];
}
