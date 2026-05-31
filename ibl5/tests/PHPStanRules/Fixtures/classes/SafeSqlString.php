<?php

declare(strict_types=1);

const SAFE_MAX_ID = 100;

function buildSafeQueries(string $player, string $team, int $n, int $round): array
{
    // Concatenation, not interpolation — these are String_ nodes joined by Concat,
    // never an InterpolatedString. (Encodes the SeasonLeaderboards:80 correction.)
    $concat = "SELECT * FROM ibl_plr WHERE pid < " . SAFE_MAX_ID . " ORDER BY pid";
    // Parameterized — plain string literal, no interpolation.
    $param = "SELECT * FROM ibl_plr WHERE pid = ?";
    // Prose that mentions SQL keywords as English words ("from") but opens with an
    // interpolated value, so the literal does not begin with a SQL token.
    $discord = "{$player} today accepted a contract offer from the {$team}";
    // Prose opening with "With" — the CTE keyword is intentionally excluded so that
    // "With pick #..." announcement text is not mistaken for a SQL statement.
    $announce = "With pick #$n in round $round of the draft";

    return [$concat, $param, $discord, $announce];
}
