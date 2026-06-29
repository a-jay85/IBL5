<?php

declare(strict_types=1);

const CONCAT_MAX_ID = 100;

function buildConcatenatedQueries(string $name, int $count, int $teamId): array
{
    // KNOWN-BAD: a string variable concatenated into a SELECT/WHERE — injection-shaped.
    $bad1 = "SELECT * FROM ibl_plr WHERE name = '"
        . $name
        . "'";
    // KNOWN-BAD: a string variable concatenated mid-chain, after an int-safe operand.
    $bad2 = "SELECT * FROM ibl_plr WHERE tid = "
        . $teamId
        . " AND city = '"
        . $name
        . "'";
    // KNOWN-GOOD: any integer is injection-inert (digits only).
    $good1 = "SELECT * FROM ibl_plr WHERE pid = " . $count;
    // KNOWN-GOOD: a constant resolves to a literal value known at analysis time.
    $good2 = "SELECT * FROM ibl_plr WHERE pid < " . CONCAT_MAX_ID . " ORDER BY pid";
    // KNOWN-GOOD: fully parameterized literal — not a Concat node at all.
    $good3 = "SELECT * FROM ibl_plr WHERE name = ?";
    // KNOWN-GOOD: non-SQL prose — leftmost literal does not begin with a SQL keyword.
    $good4 = "Hello " . $name . ", welcome";

    return [$bad1, $bad2, $good1, $good2, $good3, $good4];
}
