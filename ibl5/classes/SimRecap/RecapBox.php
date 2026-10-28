<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * What goes in the admin page's copy-paste box and the `format=txt` download. Both
 * read through here so they cannot drift apart.
 *
 * The box holds every stored game row for the sim. It does not filter on a matching
 * box score: a bad `game_of_that_day` index once hid 42 of sim 725's 49 games
 * (2026-08-02). Unmatched rows still go in the box; the page warns about them via
 * SimSummaryRepository::findOrphanedGameRecaps().
 */
final class RecapBox
{
    /**
     * @return list<array<string, mixed>> Every game row for the sim, in sort order
     */
    public static function gameRecaps(SimSummaryRepository $repo, int $sim): array
    {
        return $repo->findGameRecaps($sim);
    }

    /**
     * @param array<string, mixed> $summary From SimSummaryRepository::find()
     */
    public static function text(SimSummaryRepository $repo, int $sim, array $summary): string
    {
        $intro  = $summary['intro_text'] ?? null;
        $outro  = $summary['outro_text'] ?? null;
        $stored = $summary['recap_text'] ?? null;

        return RecapDocument::postableText(
            is_string($intro) ? $intro : null,
            self::gameRecaps($repo, $sim),
            is_string($outro) ? $outro : null,
            is_string($stored) ? $stored : null
        );
    }
}
